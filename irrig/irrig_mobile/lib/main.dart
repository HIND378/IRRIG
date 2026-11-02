import 'package:flutter/material.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'screens/login_screen.dart';
import 'screens/missions_screen.dart';
import 'services/api_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('fr_FR');
  final connecte = await ApiService.instance.token != null;
  runApp(IrrigApp(connecte: connecte));
}

class IrrigApp extends StatelessWidget {
  const IrrigApp({super.key, required this.connecte});
  final bool connecte;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'IRRIG Technicien',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1F6B45)),
        useMaterial3: true,
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
      ),
      home: connecte ? const MissionsScreen() : const LoginScreen(),
    );
  }
}
