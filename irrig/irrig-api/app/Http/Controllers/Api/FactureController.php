<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Illuminate\Http\Request;

class FactureController extends Controller
{
    /** GET /api/factures : l'agriculteur ne voit que les siennes. */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Facture::with('client:id,nom', 'intervention:id,type', 'commande.devis:id,numero')->latest('date');

        if ($user->hasRole('agriculteur')) {
            $query->where('client_id', $user->client_id);
        } elseif (! $user->voitToutesLesAgences()) {
            $query->whereHas('client', fn ($q) => $q->where('agence_id', $user->agence_id));
        }

        return $query->paginate($request->integer('par_page', 20));
    }

    /** PUT /api/factures/{facture}/payer */
    public function payer(Request $request, Facture $facture)
    {
        $user = $request->user();
        $facture->loadMissing('client');
        abort_if(! $user->voitToutesLesAgences() && (int) $facture->client->agence_id !== (int) $user->agence_id, 403);
        $facture->update(['statut_paiement' => 'payee']);

        return $facture;
    }
}
