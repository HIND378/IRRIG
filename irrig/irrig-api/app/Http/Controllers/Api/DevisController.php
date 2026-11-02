<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Devis;
use App\Models\Facture;
use App\Services\Notificateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cycle commercial (diagramme de séquence 8.2) : devis -> commande -> facture.
 */
class DevisController extends Controller
{
    /** GET /api/devis?statut= */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Devis::with('client', 'commercial:id,name', 'commande')->latest();

        if ($user->hasRole('agriculteur')) {
            $query->where('client_id', $user->client_id);
        } elseif (! $user->voitToutesLesAgences()) {
            $query->whereHas('client', fn ($q) => $q->where('agence_id', $user->agence_id));
        }
        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->paginate($request->integer('par_page', 20));
    }

    /** POST /api/devis */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'lignes' => 'required|array|min:1',
            'lignes.*.designation' => 'required|string|max:255',
            'lignes.*.quantite' => 'required|numeric|min:0.01',
            'lignes.*.prix_unitaire' => 'required|numeric|min:0',
        ]);

        $client = Client::findOrFail($data['client_id']);
        abort_if(! $user->voitToutesLesAgences() && (int) $client->agence_id !== (int) $user->agence_id, 403, 'Client d\'une autre agence.');

        $ht = collect($data['lignes'])->sum(fn ($l) => $l['quantite'] * $l['prix_unitaire']);

        $devis = Devis::create([
            'numero' => 'DEV-'.now()->format('Y').'-'.str_pad((string) (Devis::max('id') + 1), 5, '0', STR_PAD_LEFT),
            'client_id' => $client->id,
            'commercial_id' => $user->id,
            'date' => now()->toDateString(),
            'lignes' => $data['lignes'],
            'montant_ht' => round($ht, 2),
            'montant_ttc' => round($ht * (1 + config('irrig.tva')), 2),
            'statut' => 'envoye',
        ]);

        Notificateur::envoyer($client, "IRRIG : votre devis {$devis->numero} ({$devis->montant_ttc} DH TTC) est disponible.");

        return response()->json($devis->load('client'), 201);
    }

    /** GET /api/devis/{devis} */
    public function show(Request $request, Devis $devis)
    {
        $this->verifierAcces($request, $devis);

        return $devis->load('client.agence', 'commercial:id,name', 'commande.factures');
    }

    /** PUT /api/devis/{devis}/accepter : devient une commande + facture d'installation. */
    public function accepter(Request $request, Devis $devis)
    {
        $this->verifierAcces($request, $devis);
        abort_if($devis->statut === 'accepte', 422, 'Devis déjà accepté.');
        abort_if($devis->statut === 'refuse', 422, 'Devis refusé.');

        $commande = DB::transaction(function () use ($devis) {
            $devis->update(['statut' => 'accepte']);
            $commande = Commande::create(['devis_id' => $devis->id, 'date' => now()->toDateString(), 'statut' => 'en_cours']);
            Facture::create([
                'numero' => Facture::prochainNumero(),
                'client_id' => $devis->client_id,
                'commande_id' => $commande->id,
                'montant_ttc' => $devis->montant_ttc,
                'date' => now()->toDateString(),
            ]);

            return $commande;
        });

        return $commande->load('devis', 'factures');
    }

    /** PUT /api/devis/{devis}/refuser : marqué « perdu » avec son motif. */
    public function refuser(Request $request, Devis $devis)
    {
        $this->verifierAcces($request, $devis);
        abort_if($devis->statut === 'accepte', 422, 'Devis déjà accepté.');
        $data = $request->validate(['motif_refus' => 'required|string|max:255']);
        $devis->update(['statut' => 'refuse'] + $data);

        return $devis;
    }

    /** GET /api/devis/{devis}/imprimer : version imprimable (Ctrl+P -> PDF). */
    public function imprimer(Request $request, Devis $devis)
    {
        $this->verifierAcces($request, $devis);

        return response()->view('devis', ['devis' => $devis->load('client.agence', 'commercial')]);
    }

    private function verifierAcces(Request $request, Devis $devis): void
    {
        $user = $request->user();
        $devis->loadMissing('client');
        if ($user->hasRole('agriculteur')) {
            abort_if((int) $devis->client_id !== (int) $user->client_id, 403);

            return;
        }
        abort_if(! $user->voitToutesLesAgences() && (int) $devis->client->agence_id !== (int) $user->agence_id, 403);
    }
}
