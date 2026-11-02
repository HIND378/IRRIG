import 'package:path/path.dart';
import 'package:sqflite/sqflite.dart';

/// Base SQLite locale du téléphone.
///
/// - missions : copie des missions téléchargées avant le départ (consultables sans réseau)
/// - pieces   : copie du catalogue de pièces
/// - rapports : rapports saisis sur le terrain, en attente d'envoi (synchro = 0)
class DatabaseHelper {
  DatabaseHelper._();
  static final DatabaseHelper instance = DatabaseHelper._();

  Database? _db;

  Future<Database> get database async => _db ??= await _ouvrir();

  Future<Database> _ouvrir() async {
    final chemin = join(await getDatabasesPath(), 'irrig_mobile.db');
    return openDatabase(
      chemin,
      version: 1,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE missions (
            id INTEGER PRIMARY KEY,
            json TEXT NOT NULL,
            date_planifiee TEXT
          )''');
        await db.execute('''
          CREATE TABLE pieces (
            id INTEGER PRIMARY KEY,
            reference TEXT,
            libelle TEXT,
            prix REAL
          )''');
        await db.execute('''
          CREATE TABLE rapports (
            uuid TEXT PRIMARY KEY,
            intervention_id INTEGER NOT NULL,
            rapport TEXT NOT NULL,
            pieces TEXT NOT NULL,          -- JSON [{piece_id, quantite}]
            photos TEXT NOT NULL,          -- JSON [chemins des photos sur le téléphone]
            signature_client TEXT,
            date_cloture TEXT NOT NULL,
            synchro INTEGER NOT NULL DEFAULT 0,  -- 0 = en attente, 1 = envoyé
            erreur TEXT
          )''');
      },
    );
  }

  Future<void> viderEtRemplir(String table, List<Map<String, dynamic>> lignes) async {
    final db = await database;
    await db.transaction((txn) async {
      await txn.delete(table);
      for (final l in lignes) {
        await txn.insert(table, l);
      }
    });
  }
}
