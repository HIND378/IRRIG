<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Equipement;
use App\Models\Intervention;
use App\Models\User;
use App\Services\InterventionService;
use App\Services\Notificateur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Module M3 : cycle complet d'une intervention
 * (diagramme de séquence 8.1 et diagramme d'activité 9.2).
 */
class InterventionController extends Controller
{
    public function __construct(private InterventionService $service) {}

    private const RELATIONS = [
        'equipement.exploitation.client', 'agence', 'technicien:id,name,telephone',
        'lignesConsommation.piece', 'photos', 'avis', 'facture',
    ];

    /** GET /api/interventions?statut=&agence_id=&technicien_id= */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Intervention::query()->with(self::RELATIONS)->latest();

        if ($user->hasRole('agriculteur')) {
            $query->whereHas('equipement.exploitation', fn ($q) => $q->where('client_id', $user->client_id));
        } else {
            $user->filtrerParAgence($query, $request->integer('agence_id') ?: null);
        }

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }
        if ($tech = $request->integer('technicien_id')) {
            $query->where('technicien_id', $tech);
        }

        return $query->paginate($request->integer('par_page', 20));
    }

    /** GET /api/interventions/{intervention} */
    public function show(Request $request, Intervention $intervention)
    {
        $this->verifierAcces($request->user(), $intervention);

        return $intervention->load(self::RELATIONS);
    }

    /** POST /api/interventions : demande (portail client, ou saisie d'un appel/WhatsApp par l'agence). */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'equipement_id' => 'required|exists:equipements,id',
            'type' => ['required', Rule::in(['installation', 'maintenance', 'panne'])],
            'description' => 'required|string|max:2000',
            'canal' => ['nullable', Rule::in(['portail', 'telephone', 'whatsapp', 'agence'])],
        ]);

        $equipement = Equipement::with('exploitation.client')->findOrFail($data['equipement_id']);
        $client = $equipement->exploitation->client;

        if ($user->hasRole('agriculteur')) {
            abort_if((int) $client->id !== (int) $user->client_id, 403, "Cet équipement n'appartient pas à votre exploitation.");
            $data['canal'] = 'portail';
        } else {
            abort_if(! $user->voitToutesLesAgences() && (int) $client->agence_id !== (int) $user->agence_id, 403, 'Client d\'une autre agence.');
            $data['canal'] = $data['canal'] ?? 'telephone';
        }

        $intervention = Intervention::create($data + [
            'agence_id' => $client->agence_id,
            'demandeur_id' => $user->id,
            'statut' => 'nouvelle',
            'sous_garantie' => $equipement->sous_garantie,
        ]);

        Notificateur::envoyer($client, "IRRIG : demande n°{$intervention->id} bien reçue. Un technicien vous contactera.");

        return response()->json($intervention->load(self::RELATIONS), 201);
    }

    /** PUT /api/interventions/{intervention}/planifier : affecter un technicien et une date. */
    public function planifier(Request $request, Intervention $intervention)
    {
        $user = $request->user();
        $this->verifierAcces($user, $intervention);
        abort_if(in_array($intervention->statut, ['cloturee', 'annulee'], true), 422, 'Intervention déjà terminée.');

        $data = $request->validate([
            'technicien_id' => 'required|exists:users,id',
            'date_planifiee' => 'required|date|after_or_equal:today',
        ]);

        $technicien = User::findOrFail($data['technicien_id']);
        abort_if($technicien->role !== 'technicien', 422, "L'utilisateur choisi n'est pas un technicien.");
        abort_if((int) $technicien->agence_id !== (int) $intervention->agence_id, 422, "Le technicien doit appartenir à l'agence de l'intervention.");

        $intervention->update($data + ['statut' => 'planifiee']);

        Notificateur::envoyer(
            $intervention->equipement->exploitation->client,
            "IRRIG : passage du technicien {$technicien->name} prévu le ".$intervention->date_planifiee->format('d/m/Y à H\hi').'.'
        );

        return $intervention->load(self::RELATIONS);
    }

    /** PUT /api/interventions/{intervention}/annuler */
    public function annuler(Request $request, Intervention $intervention)
    {
        $this->verifierAcces($request->user(), $intervention);
        abort_if($intervention->statut === 'cloturee', 422, 'Une intervention clôturée ne peut pas être annulée.');
        $intervention->update(['statut' => 'annulee']);

        return $intervention;
    }

    /** GET /api/mes-missions : missions du technicien connecté (téléchargées avant le départ). */
    public function mesMissions(Request $request)
    {
        return Intervention::with([
            'equipement.exploitation.client',
            'equipement.interventions' => fn ($q) => $q->where('statut', 'cloturee')->latest('date_cloture')->limit(3),
        ])
            ->where('technicien_id', $request->user()->id)
            ->whereIn('statut', ['planifiee', 'en_cours'])
            ->orderBy('date_planifiee')
            ->get();
    }

    /** PUT /api/interventions/{intervention}/demarrer */
    public function demarrer(Request $request, Intervention $intervention)
    {
        abort_if((int) $intervention->technicien_id !== (int) $request->user()->id, 403, "Cette mission ne vous est pas affectée.");
        abort_if($intervention->statut !== 'planifiee', 422, 'Seule une intervention planifiée peut démarrer.');
        $intervention->update(['statut' => 'en_cours']);

        return $intervention;
    }

    /** POST /api/interventions/{intervention}/cloturer : rapport + pièces + photos (transaction). */
    public function cloturer(Request $request, Intervention $intervention)
    {
        abort_if((int) $intervention->technicien_id !== (int) $request->user()->id, 403, "Cette mission ne vous est pas affectée.");

        $data = $request->validate([
            'rapport' => 'required|string|max:5000',
            'signature_client' => 'nullable|string|max:255',
            'pieces' => 'array',
            'pieces.*.piece_id' => 'required|exists:pieces,id',
            'pieces.*.quantite' => 'required|integer|min:1',
            'photos' => 'array|max:5',
            'photos.*' => 'nullable',
        ]);
        $data['photos'] = $request->file('photos') ?? ($data['photos'] ?? []);

        return $this->service->cloturer($intervention, $data);
    }

    /** POST /api/interventions/{intervention}/avis : l'agriculteur note l'intervention (fragment « opt »). */
    public function avis(Request $request, Intervention $intervention)
    {
        $user = $request->user();
        $intervention->load('equipement.exploitation');
        abort_if((int) $intervention->equipement->exploitation->client_id !== (int) $user->client_id, 403, 'Intervention d\'un autre client.');
        abort_if($intervention->statut !== 'cloturee', 422, "L'intervention n'est pas encore terminée.");
        abort_if($intervention->avis()->exists(), 422, 'Vous avez déjà donné votre avis.');

        $data = $request->validate([
            'note' => 'required|integer|between:1,5',
            'commentaire' => 'nullable|string|max:1000',
        ]);

        return response()->json(Avis::create($data + ['intervention_id' => $intervention->id]), 201);
    }

    private function verifierAcces(User $user, Intervention $intervention): void
    {
        if ($user->hasRole('agriculteur')) {
            $intervention->loadMissing('equipement.exploitation');
            abort_if((int) $intervention->equipement->exploitation->client_id !== (int) $user->client_id, 403);

            return;
        }
        if ($user->hasRole('technicien')) {
            abort_if((int) $intervention->technicien_id !== (int) $user->id, 403);

            return;
        }
        abort_if(! $user->voitToutesLesAgences() && (int) $intervention->agence_id !== (int) $user->agence_id, 403, 'Intervention d\'une autre agence.');
    }
}
