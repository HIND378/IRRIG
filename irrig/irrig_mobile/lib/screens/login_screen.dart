import 'package:flutter/material.dart';

import '../services/api_service.dart';
import 'missions_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController(text: 'technicien1.agadir@irrig.ma');
  final _mdp = TextEditingController();
  bool _chargement = false;
  String? _erreur;

  Future<void> _connecter() async {
    setState(() {
      _chargement = true;
      _erreur = null;
    });
    try {
      await ApiService.instance.login(_email.text.trim(), _mdp.text);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const MissionsScreen()));
    } catch (e) {
      setState(() => _erreur = e is ApiException ? e.message : 'Connexion impossible. Vérifiez le réseau.');
    } finally {
      if (mounted) setState(() => _chargement = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Icon(Icons.water_drop, size: 64, color: Color(0xFF1F6B45)),
                const SizedBox(height: 8),
                Text('IRRIG Technicien', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
                const SizedBox(height: 4),
                const Text('Connectez-vous avant de partir sur le terrain.', textAlign: TextAlign.center),
                const SizedBox(height: 28),
                TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'E-mail')),
                const SizedBox(height: 14),
                TextField(controller: _mdp, obscureText: true, decoration: const InputDecoration(labelText: 'Mot de passe')),
                if (_erreur != null) ...[
                  const SizedBox(height: 12),
                  Text(_erreur!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _chargement ? null : _connecter,
                  style: FilledButton.styleFrom(padding: const EdgeInsets.all(16)),
                  child: Text(_chargement ? 'Connexion…' : 'Se connecter'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
