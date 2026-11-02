<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Piece;
use App\Models\Stock;
use App\Models\TransfertStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Module M4 : catalogue, stock par agence, réception et transferts inter-agences.
 */
class StockController extends Controller
{
    /** GET /api/pieces : catalogue (utilisé par le technicien et le commercial). */
    public function pieces()
    {
        return Piece::orderBy('libelle')->get();
    }

    /** POST /api/pieces : ajouter une pièce au catalogue (crée une ligne de stock à 0 dans chaque agence). */
    public function ajouterPiece(Request $request)
    {
        $data = $request->validate([
            'reference' => 'required|string|unique:pieces,reference',
            'libelle' => 'required|string|max:255',
            'prix' => 'required|numeric|min:0',
        ]);

        $piece = DB::transaction(function () use ($data) {
            $piece = Piece::create($data);
            foreach (\App\Models\Agence::pluck('id') as $agenceId) {
                Stock::create(['agence_id' => $agenceId, 'piece_id' => $piece->id, 'quantite' => 0]);
            }

            return $piece;
        });

        return response()->json($piece, 201);
    }

    /** GET /api/stocks?agence_id=&alerte=1 */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Stock::with('piece', 'agence');
        $user->filtrerParAgence($query, $request->integer('agence_id') ?: null);

        if ($request->boolean('alerte')) {
            $query->whereColumn('quantite', '<=', 'seuil_alerte');
        }

        return $query->get()->sortBy(fn ($s) => [$s->agence->ville, $s->piece->libelle])->values();
    }

    /** PUT /api/stocks/{stock} : ajustement après inventaire ou changement de seuil. */
    public function update(Request $request, Stock $stock)
    {
        $this->verifierAgence($request, $stock->agence_id);
        $data = $request->validate([
            'quantite' => 'sometimes|integer|min:0',
            'seuil_alerte' => 'sometimes|integer|min:0',
        ]);
        $stock->update($data);

        return $stock->load('piece');
    }

    /** POST /api/stocks/{stock}/reception : réception de pièces fournisseur. */
    public function reception(Request $request, Stock $stock)
    {
        $this->verifierAgence($request, $stock->agence_id);
        $data = $request->validate(['quantite' => 'required|integer|min:1']);
        $stock->increment('quantite', $data['quantite']);

        return $stock->fresh('piece');
    }

    /** GET /api/transferts */
    public function transferts(Request $request)
    {
        $user = $request->user();
        $query = TransfertStock::with('source', 'destination', 'piece', 'demandeur:id,name', 'directeur:id,name')->latest();
        if (! $user->voitToutesLesAgences()) {
            $query->where(fn ($q) => $q->where('agence_source_id', $user->agence_id)->orWhere('agence_dest_id', $user->agence_id));
        }

        return $query->get();
    }

    /** POST /api/transferts : demande de transfert vers l'agence du demandeur. */
    public function demanderTransfert(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'agence_source_id' => 'required|exists:agences,id',
            'agence_dest_id' => 'nullable|exists:agences,id',
            'piece_id' => 'required|exists:pieces,id',
            'quantite' => 'required|integer|min:1',
        ]);
        $data['agence_dest_id'] = $user->voitToutesLesAgences() ? ($data['agence_dest_id'] ?? null) : $user->agence_id;
        abort_if(! $data['agence_dest_id'], 422, "L'agence de destination est obligatoire.");
        abort_if((int) $data['agence_source_id'] === (int) $data['agence_dest_id'], 422, 'Les deux agences doivent être différentes.');

        $transfert = TransfertStock::create($data + ['demandeur_id' => $user->id, 'statut' => 'demande']);

        return response()->json($transfert->load('source', 'destination', 'piece'), 201);
    }

    /** PUT /api/transferts/{transfert}/valider : réservé au directeur multi-agences. */
    public function validerTransfert(Request $request, TransfertStock $transfert)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['valide', 'refuse'])]]);
        abort_if($transfert->statut !== 'demande', 422, 'Ce transfert a déjà été traité.');

        DB::transaction(function () use ($transfert, $data, $request) {
            if ($data['decision'] === 'valide') {
                $source = Stock::where('agence_id', $transfert->agence_source_id)
                    ->where('piece_id', $transfert->piece_id)->lockForUpdate()->first();
                abort_if(! $source || $source->quantite < $transfert->quantite, 422, "Stock insuffisant dans l'agence source.");

                $source->decrement('quantite', $transfert->quantite);
                Stock::firstOrCreate(
                    ['agence_id' => $transfert->agence_dest_id, 'piece_id' => $transfert->piece_id],
                    ['quantite' => 0]
                )->increment('quantite', $transfert->quantite);
            }

            $transfert->update([
                'statut' => $data['decision'],
                'directeur_id' => $request->user()->id,
                'date_validation' => now(),
            ]);
        });

        return $transfert->fresh(['source', 'destination', 'piece', 'directeur:id,name']);
    }

    private function verifierAgence(Request $request, int $agenceId): void
    {
        $user = $request->user();
        abort_if(! $user->voitToutesLesAgences() && (int) $user->agence_id !== $agenceId, 403, 'Stock d\'une autre agence.');
    }
}
