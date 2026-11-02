import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import 'api_service.dart';
import 'database_helper.dart';

/// Cœur du mode hors ligne (diagramme de séquence 8.1) :
/// 1. téléchargement des missions avant le départ,
/// 2. enregistrement local des rapports sur le terrain,
/// 3. envoi automatique dès que le réseau revient.
class SyncService extends ChangeNotifier {
  SyncService._();
  static final SyncService instance = SyncService._();

  final _db = DatabaseHelper.instance;
  final _api = ApiService.instance;
  StreamSubscription<List<ConnectivityResult>>? _ecoute;
  bool _envoiEnCours = false;

  int rapportsEnAttente = 0;
  DateTime? derniereSynchro;
  String? derniereErreur;

  /// Surveille le réseau : à chaque retour de connexion, on envoie les rapports en attente.
  void demarrerSurveillance() {
    _ecoute ??= Connectivity().onConnectivityChanged.listen((etat) {
      if (!etat.contains(ConnectivityResult.none)) synchroniser();
    });
    compterEnAttente();
  }

  void arreterSurveillance() {
    _ecoute?.cancel();
    _ecoute = null;
  }

  Future<bool> enLigne() async => !(await Connectivity().checkConnectivity()).contains(ConnectivityResult.none);

  // ---------- 1. Avant le départ ----------

  /// Télécharge les missions et le catalogue. Sans réseau, on garde la copie locale.
  Future<void> telechargerMissions() async {
    final missions = await _api.mesMissions();
    await _db.viderEtRemplir(
      'missions',
      missions
          .map((m) => {'id': m['id'], 'json': jsonEncode(m), 'date_planifiee': m['date_planifiee']})
          .toList(),
    );
    final pieces = await _api.pieces();
    await _db.viderEtRemplir(
      'pieces',
      pieces.map((p) => {'id': p['id'], 'reference': p['reference'], 'libelle': p['libelle'], 'prix': p['prix']}).toList(),
    );
    derniereSynchro = DateTime.now();
    notifyListeners();
  }

  Future<List<Map<String, dynamic>>> missionsLocales() async {
    final db = await _db.database;
    final lignes = await db.query('missions', orderBy: 'date_planifiee');
    final envoyes = (await db.query('rapports', columns: ['intervention_id'])).map((r) => r['intervention_id']).toSet();
    return lignes
        .map((l) => jsonDecode(l['json'] as String) as Map<String, dynamic>)
        .map((m) => {...m, 'rapport_local': envoyes.contains(m['id'])})
        .toList();
  }

  Future<List<Map<String, dynamic>>> piecesLocales() async {
    final db = await _db.database;
    return db.query('pieces', orderBy: 'libelle');
  }

  // ---------- 2. Sur le terrain (fonctionne sans réseau) ----------

  Future<void> enregistrerRapport({
    required int interventionId,
    required String rapport,
    required List<Map<String, int>> pieces,
    required List<String> photos,
    String? signatureClient,
  }) async {
    final db = await _db.database;
    await db.insert('rapports', {
      'uuid': const Uuid().v4(), // identifiant unique : le serveur ignore un double envoi
      'intervention_id': interventionId,
      'rapport': rapport,
      'pieces': jsonEncode(pieces),
      'photos': jsonEncode(photos),
      'signature_client': signatureClient,
      'date_cloture': DateTime.now().toIso8601String(),
      'synchro': 0,
    });
    await compterEnAttente();
    unawaited(synchroniser()); // tente l'envoi tout de suite s'il y a du réseau
  }

  Future<void> compterEnAttente() async {
    final db = await _db.database;
    final r = await db.rawQuery('SELECT COUNT(*) AS n FROM rapports WHERE synchro = 0');
    rapportsEnAttente = (r.first['n'] as int?) ?? 0;
    notifyListeners();
  }

  Future<List<Map<String, dynamic>>> rapportsLocaux() async {
    final db = await _db.database;
    return db.query('rapports', orderBy: 'date_cloture DESC');
  }

  // ---------- 3. Retour du réseau ----------

  Future<void> synchroniser() async {
    if (_envoiEnCours || !await enLigne()) return;
    _envoiEnCours = true;
    try {
      final db = await _db.database;
      final enAttente = await db.query('rapports', where: 'synchro = 0');
      if (enAttente.isEmpty) return;

      final lot = <Map<String, dynamic>>[];
      for (final r in enAttente) {
        lot.add({
          'uuid': r['uuid'],
          'intervention_id': r['intervention_id'],
          'rapport': r['rapport'],
          'signature_client': r['signature_client'],
          'date_cloture': (r['date_cloture'] as String).substring(0, 19).replaceFirst('T', ' '),
          'pieces': jsonDecode(r['pieces'] as String),
          'photos': await _photosBase64(jsonDecode(r['photos'] as String) as List<dynamic>),
        });
      }

      final resultats = await _api.synchroniser(lot);
      for (final res in resultats) {
        final ok = res['statut'] == 'ok' || res['statut'] == 'deja_synchronise';
        await db.update(
          'rapports',
          {'synchro': ok ? 1 : 0, 'erreur': ok ? null : res['message']},
          where: 'uuid = ?',
          whereArgs: [res['uuid']],
        );
      }
      derniereSynchro = DateTime.now();
      derniereErreur = null;
    } catch (e) {
      derniereErreur = e.toString(); // réseau coupé pendant l'envoi : on réessaiera
    } finally {
      _envoiEnCours = false;
      await compterEnAttente();
    }
  }

  Future<List<String>> _photosBase64(List<dynamic> chemins) async {
    final resultat = <String>[];
    for (final c in chemins) {
      final fichier = File(c as String);
      if (await fichier.exists()) resultat.add(base64Encode(await fichier.readAsBytes()));
    }
    return resultat;
  }
}
