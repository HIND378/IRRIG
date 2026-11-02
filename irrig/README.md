# IRRIG Connect — réalisation du projet (TP digitalisation, séance 2)

Plateforme de transformation digitale d'IRRIG : un seul back-end, trois interfaces.

| Dossier | Technologie | Pour qui |
| --- | --- | --- |
| `irrig-api/` | Laravel + MySQL, API REST (jetons Sanctum) | Toutes les interfaces |
| `irrig-web/` | React (Vite) | Back-office des agences + portail client (arabe / français) |
| `irrig_mobile/` | Flutter + SQLite | Techniciens sur le terrain, mode hors ligne |

## Démarrage rapide (démonstration)

**Terminal 1 — API**

```bash
cd irrig-api
composer install
cp .env.example .env
php artisan key:generate
# créer la base MySQL « irrig », puis :
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

**Terminal 2 — front web**

```bash
cd irrig-web
npm install
cp .env.example .env
npm run dev
```

Ouvrir http://localhost:5173. La page de connexion propose un bouton par rôle (mot de passe `password`).

**Mobile (facultatif)** : voir `irrig_mobile/README.md`.

## Scénario de démonstration (5 minutes)

1. **Agriculteur** : portail en arabe, « طلب جديد » (nouvelle demande) sur sa pompe solaire.
2. **Responsable Agadir** : la demande apparaît dans « Demandes d'intervention » → Planifier → choix du technicien.
3. **Technicien 1 Agadir** : sur le mobile (ou la page web « Mes missions »), mode avion, saisie du rapport avec une pièce, retour du réseau → envoi automatique.
4. **Magasinier Agadir** : la pièce a baissé dans le stock ; ligne rouge si elle passe sous le seuil → demande de transfert depuis Rabat.
5. **Directeur** : valide le transfert, puis montre le tableau de bord consolidé des 3 agences.
6. **Agriculteur** : « Mes demandes » → intervention terminée → 5 étoiles.
7. **Commercial** : nouveau devis → Imprimer (PDF) → Accepter → commande et facture créées.

## Correspondance avec la conception (séance 1)

| Élément de conception | Où dans le code |
| --- | --- |
| Diagramme de classes | `irrig-api/database/migrations`, `irrig-api/app/Models` |
| Généralisation Directeur → Responsable | `CheckRole.php` (API) et `App.jsx` (front) |
| Séquence 8.1 (intervention, hors ligne) | `InterventionController`, `SyncController`, `irrig_mobile/lib/services/sync_service.dart` |
| Séquence 8.2 (devis → commande) | `DevisController`, page `Devis.jsx` |
| Activité 9.2 (garantie, stock, réseau) | `InterventionService` (gratuit sous garantie, stock insuffisant refusé), transferts validés par le directeur |
| Déploiement 9.3 | API REST unique, MySQL, stockage des fichiers, passerelle SMS (`Notificateur`) |
