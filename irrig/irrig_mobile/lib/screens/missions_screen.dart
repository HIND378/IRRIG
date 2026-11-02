import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../services/sync_service.dart';
import 'login_screen.dart';
import 'rapport_screen.dart';

/// Liste des missions : lisible même sans réseau (copie SQLite).
class MissionsScreen extends StatefulWidget {
  const MissionsScreen({super.key});

  @override
  State<MissionsScreen> createState() => _MissionsScreenState();
}

class _MissionsScreenState extends State<MissionsScreen> {
  final _sync = SyncService.instance;
  List<Map<String, dynamic>> _missions = [];
  bool _chargement = true;
  String? _message;

  @override
  void initState() {
    super.initState();
    _sync.demarrerSurveillance();
    _sync.addListener(_rafraichir);
    _actualiser();
  }

  @override
  void dispose() {
    _sync.removeListener(_rafraichir);
    super.dispose();
  }

  void _rafraichir() {
    if (mounted) setState(() {});
  }

  /// Télécharge les missions si le réseau est là, sinon affiche la copie locale.
  Future<void> _actualiser() async {
    setState(() => _chargement = true);
    String? message;
    try {
      if (await _sync.enLigne()) {
        await _sync.synchroniser();
        await _sync.telechargerMissions();
      } else {
        message = 'Hors ligne : affichage des missions téléchargées.';
      }
    } catch (e) {
      message = 'Réseau indisponible : affichage des missions téléchargées.';
    }
    final missions = await _sync.missionsLocales();
    if (!mounted) return;
    setState(() {
      _missions = missions;
      _message = message;
      _chargement = false;
    });
  }

  Future<void> _deconnecter() async {
    if (_sync.rapportsEnAttente > 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Des rapports ne sont pas encore envoyés. Reconnectez-vous au réseau avant de vous déconnecter.')),
      );
      return;
    }
    _sync.arreterSurveillance();
    await ApiService.instance.logout();
    if (!mounted) return;
    Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
  }

  @override
  Widget build(BuildContext context) {
    final format = DateFormat('EEE d MMM · HH:mm', 'fr_FR');
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mes missions'),
        actions: [
          IconButton(onPressed: _actualiser, icon: const Icon(Icons.sync), tooltip: 'Synchroniser'),
          IconButton(onPressed: _deconnecter, icon: const Icon(Icons.logout), tooltip: 'Se déconnecter'),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _actualiser,
        child: ListView(
          padding: const EdgeInsets.all(12),
          children: [
            _BandeauSynchro(sync: _sync, message: _message),
            if (_chargement) const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
            if (!_chargement && _missions.isEmpty)
              const Padding(padding: EdgeInsets.all(32), child: Center(child: Text('Aucune mission planifiée.'))),
            ..._missions.map((m) {
              final ex = m['equipement']['exploitation'];
              final date = m['date_planifiee'] == null ? '' : format.format(DateTime.parse(m['date_planifiee']).toLocal());
              final fait = m['rapport_local'] == true;
              return Card(
                child: ListTile(
                  contentPadding: const EdgeInsets.all(12),
                  leading: CircleAvatar(
                    backgroundColor: fait ? Colors.green.shade100 : Colors.blue.shade50,
                    child: Icon(fait ? Icons.check : Icons.build, color: fait ? Colors.green.shade800 : Colors.blue.shade800),
                  ),
                  title: Text('${ex['client']['nom']} — ${ex['commune']}'),
                  subtitle: Text('$date\n${m['description']}', maxLines: 3, overflow: TextOverflow.ellipsis),
                  isThreeLine: true,
                  trailing: fait ? const Text('Rapport\nsaisi', textAlign: TextAlign.center) : const Icon(Icons.chevron_right),
                  onTap: fait
                      ? null
                      : () async {
                          await Navigator.of(context).push(MaterialPageRoute(builder: (_) => RapportScreen(mission: m)));
                          _missions = await _sync.missionsLocales();
                          _rafraichir();
                        },
                ),
              );
            }),
          ],
        ),
      ),
    );
  }
}

class _BandeauSynchro extends StatelessWidget {
  const _BandeauSynchro({required this.sync, this.message});
  final SyncService sync;
  final String? message;

  @override
  Widget build(BuildContext context) {
    final attente = sync.rapportsEnAttente;
    final couleur = attente > 0 ? Colors.orange.shade50 : Colors.green.shade50;
    final heure = sync.derniereSynchro == null ? 'jamais' : DateFormat('HH:mm').format(sync.derniereSynchro!);
    return Card(
      color: couleur,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(attente > 0 ? Icons.cloud_upload_outlined : Icons.cloud_done_outlined),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                [
                  attente > 0 ? '$attente rapport(s) en attente d\'envoi' : 'Tous les rapports sont envoyés',
                  'Dernière synchronisation : $heure',
                  if (message != null) message!,
                  if (sync.derniereErreur != null && attente > 0) 'Envoi automatique au retour du réseau.',
                ].join('\n'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
