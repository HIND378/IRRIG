<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Equipement;
use App\Models\Exploitation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Module M1/M2 : clients, exploitations, parc installé.
 */
class ClientController extends Controller
{
    /** GET /api/clients?q=&agence_id= */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Client::query()->with('agence')->withCount('exploitations');
        $user->filtrerParAgence($query, $request->integer('agence_id') ?: null);

        if ($q = $request->string('q')->trim()->value()) {
            $query->where(fn ($w) => $w->where('nom', 'like', "%{$q}%")->orWhere('telephone', 'like', "%{$q}%"));
        }

        return $query->orderBy('nom')->paginate($request->integer('par_page', 20));
    }

    /** POST /api/clients */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'type' => ['required', Rule::in(['petit_exploitant', 'cooperative', 'entreprise'])],
            'telephone' => 'required|string',
            'email' => 'nullable|email',
            'langue' => ['nullable', Rule::in(['fr', 'ar'])],
            'agence_id' => 'nullable|exists:agences,id',
        ]);

        $data['telephone'] = Client::normaliserTelephone($data['telephone']);
        abort_if($data['telephone'] === null, 422, 'Numéro de téléphone invalide (format marocain attendu).');
        abort_if(Client::where('telephone', $data['telephone'])->exists(), 422, 'Un client existe déjà avec ce numéro.');

        // un commercial crée toujours le client dans sa propre agence
        $data['agence_id'] = $user->voitToutesLesAgences() ? ($data['agence_id'] ?? null) : $user->agence_id;
        abort_if(! $data['agence_id'], 422, "L'agence du client est obligatoire.");

        return response()->json(Client::create($data), 201);
    }

    /** GET /api/clients/{client} : fiche complète avec exploitations et équipements. */
    public function show(Request $request, Client $client)
    {
        $this->verifierAgence($request, $client);

        return $client->load([
            'agence',
            'exploitations.equipements.interventions' => fn ($q) => $q->latest()->limit(5),
            'devis' => fn ($q) => $q->latest(),
            'factures' => fn ($q) => $q->latest(),
        ]);
    }

    /** PUT /api/clients/{client} */
    public function update(Request $request, Client $client)
    {
        $this->verifierAgence($request, $client);
        $data = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'type' => ['sometimes', Rule::in(['petit_exploitant', 'cooperative', 'entreprise'])],
            'telephone' => 'sometimes|string',
            'email' => 'nullable|email',
            'langue' => ['nullable', Rule::in(['fr', 'ar'])],
        ]);
        if (isset($data['telephone'])) {
            $data['telephone'] = Client::normaliserTelephone($data['telephone']);
            abort_if($data['telephone'] === null, 422, 'Numéro de téléphone invalide.');
        }
        $client->update($data);

        return $client;
    }

    /** POST /api/clients/{client}/exploitations */
    public function ajouterExploitation(Request $request, Client $client)
    {
        $this->verifierAgence($request, $client);
        $data = $request->validate([
            'nom' => 'nullable|string|max:255',
            'commune' => 'required|string|max:255',
            'superficie_ha' => 'nullable|numeric|min:0',
            'culture' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        return response()->json($client->exploitations()->create($data), 201);
    }

    /** POST /api/exploitations/{exploitation}/equipements */
    public function ajouterEquipement(Request $request, Exploitation $exploitation)
    {
        $this->verifierAgence($request, $exploitation->client);
        $data = $request->validate([
            'type' => ['required', Rule::in(Equipement::TYPES)],
            'modele' => 'nullable|string|max:255',
            'numero_serie' => 'required|string|unique:equipements,numero_serie',
            'date_installation' => 'required|date',
            'fin_garantie' => 'nullable|date|after_or_equal:date_installation',
        ]);

        return response()->json($exploitation->equipements()->create($data), 201);
    }

    /** GET /api/mes-equipements : équipements de l'agriculteur connecté (portail client). */
    public function mesEquipements(Request $request)
    {
        $client = $request->user()->client;
        abort_if(! $client, 404, 'Aucun client associé à ce compte.');

        return $client->exploitations()->with('equipements')->get();
    }

    private function verifierAgence(Request $request, Client $client): void
    {
        $user = $request->user();
        abort_if(! $user->voitToutesLesAgences() && (int) $client->agence_id !== (int) $user->agence_id, 403, 'Client d\'une autre agence.');
    }
}
