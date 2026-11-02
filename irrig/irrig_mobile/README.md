# IRRIG Technicien — application mobile Flutter

Application des techniciens : missions téléchargées avant le départ, rapport saisi sur le terrain **sans réseau**, envoi automatique au retour de la connexion (sans doublon grâce à l'`uuid` du rapport).

## Installation

Ce dossier contient le code source (`lib/`, `pubspec.yaml`). Les dossiers des plateformes (`android/`, `ios/`) se génèrent avec Flutter :

```bash
cd irrig_mobile
flutter create . --project-name irrig_mobile --org ma.irrig --platforms android,ios
flutter pub get
```

`flutter create .` ne remplace pas les fichiers existants de `lib/`.

### Réglages Android (une seule fois)

Dans `android/app/src/main/AndroidManifest.xml` :

1. Avant la balise `<application>`, ajouter :

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.CAMERA" />
```

2. Dans la balise `<application ...>`, ajouter l'attribut suivant (l'API de développement est en HTTP, pas HTTPS) :

```xml
android:usesCleartextTraffic="true"
```

En production, l'API est en HTTPS et cet attribut doit être retiré.

## Lancement

1. Démarrer l'API : `php artisan serve --host=0.0.0.0`
2. Lancer l'application :

```bash
# émulateur Android (10.0.2.2 = l'ordinateur)
flutter run

# téléphone réel sur le même Wi-Fi
flutter run --dart-define=API_URL=http://<IP-de-l-ordinateur>:8000/api
```

Compte de test : `technicien1.agadir@irrig.ma` / `password`

## Tester le mode hors ligne (démonstration)

1. Se connecter, la liste des missions se télécharge.
2. Activer le **mode avion**.
3. Ouvrir une mission, saisir le rapport, une pièce, une photo, puis « Clôturer et enregistrer ».
   Le bandeau affiche « 1 rapport(s) en attente d'envoi ».
4. Désactiver le mode avion : le rapport part tout seul, le bandeau passe au vert.
5. Côté web (responsable d'Agadir), l'intervention est clôturée et le stock de la pièce a baissé.

## Organisation du code

| Fichier | Rôle |
| --- | --- |
| `lib/config.dart` | Adresse de l'API |
| `lib/services/api_service.dart` | Appels REST (connexion, missions, pièces, `/sync`) |
| `lib/services/database_helper.dart` | Base SQLite locale : missions, pièces, rapports en attente |
| `lib/services/sync_service.dart` | Téléchargement, enregistrement local, synchronisation automatique |
| `lib/screens/login_screen.dart` | Connexion (réservée au rôle technicien) |
| `lib/screens/missions_screen.dart` | Liste des missions + état de la synchronisation |
| `lib/screens/rapport_screen.dart` | Détail de la mission et saisie du rapport |
