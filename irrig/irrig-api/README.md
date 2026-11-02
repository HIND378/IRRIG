# IRRIG Connect — API REST (Laravel + MySQL)

Back-end de la plateforme : base de données, règles métier et API REST utilisée par le front React et l'application mobile Flutter.

## Prérequis

PHP 8.3+, Composer, MySQL 8 (XAMPP, WAMP, Laragon ou MySQL seul).

## Installation

```bash
cd irrig-api
composer install
cp .env.example .env          # sous Windows : copy .env.example .env
php artisan key:generate
```

Créer la base `irrig` dans MySQL (phpMyAdmin ou `CREATE DATABASE irrig CHARACTER SET utf8mb4;`), vérifier `DB_USERNAME` / `DB_PASSWORD` dans `.env`, puis :

```bash
php artisan migrate --seed    # crée les 16 tables métier + les données de démonstration
php artisan storage:link      # rend les photos et documents accessibles
php artisan serve             # API sur http://localhost:8000/api
```

Pour un téléphone réel sur le même Wi-Fi : `php artisan serve --host=0.0.0.0`.

## Comptes de démonstration

Mot de passe de tous les comptes : `password`

| Rôle | E-mail |
| --- | --- |
| Agriculteur (portail client) | agriculteur@irrig.ma |
| Commercial | commercial.agadir@irrig.ma (aussi .rabat, .casablanca) |
| Responsable d'agence | responsable.agadir@irrig.ma |
| Directeur multi-agences | directeur@irrig.ma |
| Technicien | technicien1.agadir@irrig.ma, technicien2.agadir@irrig.ma |
| Magasinier | magasinier.agadir@irrig.ma |
| Administrateur | admin@irrig.ma |

## Tests automatiques

```bash
php artisan test
```

Le test `tests/Feature/ParcoursInterventionTest.php` vérifie le parcours complet (demande → planification → clôture → stock décrémenté → facture → avis), le refus d'une clôture quand le stock manque, la synchronisation hors ligne sans doublon, les droits de chaque rôle et la validation des transferts par le directeur.

## Migration des fichiers Excel (livrable 4)

1. Dans Excel : *Fichier > Enregistrer sous > CSV UTF-8*.
2. Simulation (contrôle sans rien enregistrer) :

```bash
php artisan import:clients storage/app/import/exemple_agadir.csv --agence=Agadir --simulation
```

3. Import réel : même commande sans `--simulation`.

La commande normalise les téléphones (+212…), fusionne les doublons, ramène les types d'équipement à la liste fermée, convertit les dates et écrit un rapport d'anomalies CSV dans `storage/app/`. Le fichier d'exemple contient volontairement des erreurs (téléphone manquant, commune vide, type « Vanne » inconnu, date « mars 2024 »).

## Structure

| Dossier | Contenu |
| --- | --- |
| `app/Models` | 15 modèles issus du diagramme de classes |
| `app/Http/Controllers/Api` | Un contrôleur par module (clients, interventions, stock, devis, KPI…) |
| `app/Http/Middleware/CheckRole.php` | Contrôle des 7 rôles ; le directeur hérite des droits du responsable |
| `app/Services/InterventionService.php` | Clôture en une transaction : rapport, pièces, stock, photos, facture |
| `app/Services/Notificateur.php` | Notifications SMS/WhatsApp (écrites dans `storage/logs/notifications.log` pour la démo) |
| `app/Console/Commands/ImportClients.php` | Migration des fichiers Excel |
| `database/migrations` | Tables MySQL |
| `database/seeders/DatabaseSeeder.php` | Données de démonstration |
| `routes/api.php` | Toutes les routes REST et leurs rôles |
| `docs/IRRIG.postman_collection.json` | Collection Postman à importer |

## Routes principales

Voir `routes/api.php` (commentées par rôle) ou `php artisan route:list --path=api`.
