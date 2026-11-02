<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Services\InterventionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/sync : réception groupée des rapports saisis hors ligne
 * par l'application mobile, dès que le réseau revient.
 *
 * Chaque rapport porte un `uuid` généré sur le téléphone : si le même
 * rapport arrive deux fois (coupure pendant l'envoi), il n'est pas dupliqué.
 */
class SyncController extends Controller
{
    public function __construct(private InterventionService $service) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'rapports' => 'required|array|max:50',
            'rapports.*.uuid' => 'required|uuid',
            'rapports.*.intervention_id' => 'required|integer',
            'rapports.*.rapport' => 'required|string|max:5000',
            'rapports.*.signature_client' => 'nullable|string|max:255',
            'rapports.*.date_cloture' => 'nullable|date',
            'rapports.*.pieces' => 'array',
            'rapports.*.pieces.*.piece_id' => 'required|integer|exists:pieces,id',
            'rapports.*.pieces.*.quantite' => 'required|integer|min:1',
            'rapports.*.photos' => 'array|max:5',
            'rapports.*.photos.*' => 'string',
        ]);

        $resultats = [];

        foreach ($data['rapports'] as $r) {
            // Rapport déjà reçu lors d'un envoi précédent : on l'ignore.
            if (Intervention::where('uuid_mobile', $r['uuid'])->exists()) {
                $resultats[] = ['uuid' => $r['uuid'], 'statut' => 'deja_synchronise'];
                continue;
            }

            $intervention = Intervention::find($r['intervention_id']);
            if (! $intervention || (int) $intervention->technicien_id !== (int) $request->user()->id) {
                $resultats[] = ['uuid' => $r['uuid'], 'statut' => 'refuse', 'message' => 'Mission introuvable ou non affectée.'];
                continue;
            }
            if ($intervention->statut === 'cloturee') {
                $resultats[] = ['uuid' => $r['uuid'], 'statut' => 'deja_synchronise'];
                continue;
            }

            try {
                $intervention->update(['uuid_mobile' => $r['uuid']]);
                $this->service->cloturer($intervention, $r);
                $resultats[] = ['uuid' => $r['uuid'], 'statut' => 'ok'];
            } catch (ValidationException $e) {
                $intervention->update(['uuid_mobile' => null]);
                $resultats[] = ['uuid' => $r['uuid'], 'statut' => 'erreur', 'message' => collect($e->errors())->flatten()->first()];
            }
        }

        return response()->json(['resultats' => $resultats]);
    }
}
