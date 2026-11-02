/// Adresse de l'API Laravel.
///
/// - Émulateur Android : 10.0.2.2 désigne l'ordinateur qui fait tourner `php artisan serve`.
/// - Téléphone réel sur le même Wi-Fi : remplacer par l'adresse IP de l'ordinateur
///   et lancer l'API avec `php artisan serve --host=0.0.0.0`.
///
/// On peut aussi la passer au lancement :
///   flutter run --dart-define=API_URL=http://192.168.1.20:8000/api
const String apiUrl = String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000/api');
