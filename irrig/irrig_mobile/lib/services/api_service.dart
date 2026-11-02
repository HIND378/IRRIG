import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config.dart';

class ApiException implements Exception {
  ApiException(this.message, [this.statusCode]);
  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

/// Appels à l'API REST Laravel (jeton Sanctum dans l'en-tête Authorization).
class ApiService {
  ApiService._();
  static final ApiService instance = ApiService._();

  static const _timeout = Duration(seconds: 20);
  String? _token;

  Future<String?> get token async {
    _token ??= (await SharedPreferences.getInstance()).getString('token');
    return _token;
  }

  Future<Map<String, String>> _entetes() async => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (await token != null) 'Authorization': 'Bearer ${await token}',
      };

  dynamic _decoder(http.Response res) {
    final corps = res.body.isEmpty ? null : jsonDecode(res.body);
    if (res.statusCode >= 200 && res.statusCode < 300) return corps;

    var message = 'Erreur ${res.statusCode}';
    if (corps is Map && corps['errors'] is Map) {
      message = (corps['errors'] as Map).values.expand((v) => v as List).join(' ');
    } else if (corps is Map && corps['message'] != null) {
      message = corps['message'].toString();
    }
    throw ApiException(message, res.statusCode);
  }

  /// Connexion : seul le rôle technicien peut utiliser l'application.
  Future<Map<String, dynamic>> login(String email, String password) async {
    final res = await http
        .post(Uri.parse('$apiUrl/login'),
            headers: await _entetes(), body: jsonEncode({'email': email, 'password': password, 'appareil': 'mobile'}))
        .timeout(_timeout);
    final data = _decoder(res) as Map<String, dynamic>;
    final user = data['user'] as Map<String, dynamic>;
    if (user['role'] != 'technicien') {
      throw ApiException("Cette application est réservée aux techniciens.");
    }
    final prefs = await SharedPreferences.getInstance();
    _token = data['token'] as String;
    await prefs.setString('token', _token!);
    await prefs.setString('user', jsonEncode(user));
    return user;
  }

  Future<void> logout() async {
    try {
      await http.post(Uri.parse('$apiUrl/logout'), headers: await _entetes()).timeout(_timeout);
    } catch (_) {
      // hors ligne : on supprime quand même le jeton local
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('token');
    await prefs.remove('user');
    _token = null;
  }

  Future<Map<String, dynamic>?> utilisateurConnecte() async {
    final prefs = await SharedPreferences.getInstance();
    final brut = prefs.getString('user');
    return brut == null ? null : jsonDecode(brut) as Map<String, dynamic>;
  }

  Future<List<dynamic>> mesMissions() async {
    final res = await http.get(Uri.parse('$apiUrl/mes-missions'), headers: await _entetes()).timeout(_timeout);
    return _decoder(res) as List<dynamic>;
  }

  Future<List<dynamic>> pieces() async {
    final res = await http.get(Uri.parse('$apiUrl/pieces'), headers: await _entetes()).timeout(_timeout);
    return _decoder(res) as List<dynamic>;
  }

  Future<void> demarrer(int interventionId) async {
    final res = await http
        .put(Uri.parse('$apiUrl/interventions/$interventionId/demarrer'), headers: await _entetes())
        .timeout(_timeout);
    _decoder(res);
  }

  /// Envoi groupé des rapports saisis hors ligne. Renvoie le résultat par uuid.
  Future<List<dynamic>> synchroniser(List<Map<String, dynamic>> rapports) async {
    final res = await http
        .post(Uri.parse('$apiUrl/sync'), headers: await _entetes(), body: jsonEncode({'rapports': rapports}))
        .timeout(const Duration(seconds: 60));
    return (_decoder(res) as Map<String, dynamic>)['resultats'] as List<dynamic>;
  }
}
