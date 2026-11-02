<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentProjet;
use App\Models\Exploitation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Module M7 : archivage des projets réalisés (GED).
 */
class DocumentController extends Controller
{
    /** GET /api/exploitations/{exploitation}/documents */
    public function index(Request $request, Exploitation $exploitation)
    {
        $this->verifierAcces($request, $exploitation);

        return $exploitation->documents()->with('auteur:id,name')->latest()->get();
    }

    /** POST /api/exploitations/{exploitation}/documents (multipart : fichier, type, titre) */
    public function store(Request $request, Exploitation $exploitation)
    {
        $this->verifierAcces($request, $exploitation);
        $data = $request->validate([
            'type' => ['required', Rule::in(['plan', 'pv_reception', 'photo', 'devis', 'autre'])],
            'titre' => 'required|string|max:255',
            'fichier' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,docx,xlsx',
        ]);

        $chemin = $request->file('fichier')->store("projets/{$exploitation->id}", 'public');

        $document = $exploitation->documents()->create([
            'type' => $data['type'],
            'titre' => $data['titre'],
            'chemin_fichier' => $chemin,
            'auteur_id' => $request->user()->id,
        ]);

        return response()->json($document, 201);
    }

    private function verifierAcces(Request $request, Exploitation $exploitation): void
    {
        $user = $request->user();
        $exploitation->loadMissing('client');
        abort_if(! $user->voitToutesLesAgences() && (int) $exploitation->client->agence_id !== (int) $user->agence_id, 403);
    }
}
