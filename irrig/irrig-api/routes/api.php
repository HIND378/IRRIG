<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DevisController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\InterventionController;
use App\Http\Controllers\Api\KpiController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST IRRIG Connect  (préfixe /api)
|--------------------------------------------------------------------------
| Rôles : agriculteur, commercial, responsable, directeur, technicien,
| magasinier, admin. Le directeur passe toutes les routes 'responsable'
| (généralisation Directeur -> Responsable d'agence).
*/

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Catalogue de pièces (lecture pour tous les rôles internes)
    Route::get('/pieces', [StockController::class, 'pieces'])
        ->middleware('role:technicien,magasinier,commercial,responsable,admin');

    // ---------- Portail client (agriculteur) ----------
    Route::middleware('role:agriculteur')->group(function () {
        Route::get('/mes-equipements', [ClientController::class, 'mesEquipements']);
        Route::post('/interventions/{intervention}/avis', [InterventionController::class, 'avis']);
    });

    // ---------- Interventions : consultation partagée (filtrée par rôle dans le contrôleur) ----------
    Route::get('/interventions', [InterventionController::class, 'index'])
        ->middleware('role:agriculteur,commercial,responsable,technicien');
    Route::get('/interventions/{intervention}', [InterventionController::class, 'show'])
        ->middleware('role:agriculteur,commercial,responsable,technicien');
    Route::post('/interventions', [InterventionController::class, 'store'])
        ->middleware('role:agriculteur,commercial,responsable');

    Route::get('/factures', [FactureController::class, 'index'])
        ->middleware('role:agriculteur,commercial,responsable');
    Route::get('/devis', [DevisController::class, 'index'])
        ->middleware('role:agriculteur,commercial,responsable');
    Route::get('/devis/{devis}', [DevisController::class, 'show'])
        ->middleware('role:agriculteur,commercial,responsable');
    Route::get('/devis/{devis}/imprimer', [DevisController::class, 'imprimer'])
        ->middleware('role:agriculteur,commercial,responsable');

    // ---------- Commercial : clients, exploitations, devis ----------
    Route::get('/clients', [ClientController::class, 'index'])
        ->middleware('role:commercial,responsable,admin'); // admin : lier un compte agriculteur à sa fiche
    Route::middleware('role:commercial,responsable')->group(function () {
        Route::post('/clients', [ClientController::class, 'store']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::put('/clients/{client}', [ClientController::class, 'update']);
        Route::post('/clients/{client}/exploitations', [ClientController::class, 'ajouterExploitation']);
        Route::post('/exploitations/{exploitation}/equipements', [ClientController::class, 'ajouterEquipement']);

        Route::post('/devis', [DevisController::class, 'store']);
        Route::put('/devis/{devis}/accepter', [DevisController::class, 'accepter']);
        Route::put('/devis/{devis}/refuser', [DevisController::class, 'refuser']);
        Route::put('/factures/{facture}/payer', [FactureController::class, 'payer']);
    });

    // ---------- Responsable d'agence (et directeur) ----------
    Route::middleware('role:responsable')->group(function () {
        Route::get('/techniciens', [AdminController::class, 'techniciens']);
        Route::put('/interventions/{intervention}/planifier', [InterventionController::class, 'planifier']);
        Route::put('/interventions/{intervention}/annuler', [InterventionController::class, 'annuler']);
        Route::get('/kpi', [KpiController::class, 'agence']);
        Route::get('/exploitations/{exploitation}/documents', [DocumentController::class, 'index']);
        Route::post('/exploitations/{exploitation}/documents', [DocumentController::class, 'store']);
    });

    // ---------- Directeur multi-agences ----------
    Route::middleware('role:directeur')->group(function () {
        Route::get('/kpi/consolides', [KpiController::class, 'consolides']);
        Route::post('/agences', [AdminController::class, 'storeAgence']);
        Route::put('/agences/{agence}', [AdminController::class, 'updateAgence']);
        Route::put('/transferts/{transfert}/valider', [StockController::class, 'validerTransfert']);
    });
    Route::get('/agences', [AdminController::class, 'agences'])
        ->middleware('role:responsable,magasinier,admin');

    // ---------- Technicien (application mobile) ----------
    Route::middleware('role:technicien')->group(function () {
        Route::get('/mes-missions', [InterventionController::class, 'mesMissions']);
        Route::put('/interventions/{intervention}/demarrer', [InterventionController::class, 'demarrer']);
        Route::post('/interventions/{intervention}/cloturer', [InterventionController::class, 'cloturer']);
        Route::post('/sync', [SyncController::class, 'store']);
    });

    // ---------- Magasinier : stock ----------
    Route::middleware('role:magasinier,responsable')->group(function () {
        Route::get('/stocks', [StockController::class, 'index']);
        Route::put('/stocks/{stock}', [StockController::class, 'update']);
        Route::post('/stocks/{stock}/reception', [StockController::class, 'reception']);
        Route::post('/pieces', [StockController::class, 'ajouterPiece']);
        Route::get('/transferts', [StockController::class, 'transferts']);
        Route::post('/transferts', [StockController::class, 'demanderTransfert']);
    });

    // ---------- Administrateur ----------
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
    });
});
