<?php

namespace App\Services;

use App\Models\Facture;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Règles métier du module interventions, partagées entre la clôture
 * en ligne (web/mobile) et la synchronisation des rapports hors ligne.
 */
class InterventionService
{
    /**
     * Clôture une intervention : rapport, pièces consommées, photos, facture.
     * Tout se fait dans UNE transaction : si une pièce manque, rien n'est enregistré.
     *
     * @param  array{rapport:string, pieces?:array, photos?:array, signature_client?:string, date_cloture?:string}  $data
     */
    public function cloturer(Intervention $intervention, array $data): Intervention
    {
        if ($intervention->statut === 'cloturee') {
            return $intervention->fresh(['lignesConsommation.piece', 'photos', 'facture']);
        }
        if ($intervention->statut === 'annulee') {
            throw ValidationException::withMessages(['statut' => 'Cette intervention est annulée.']);
        }

        DB::transaction(function () use ($intervention, $data) {
            foreach ($data['pieces'] ?? [] as $p) {
                $stock = Stock::where('agence_id', $intervention->agence_id)
                    ->where('piece_id', $p['piece_id'])
                    ->lockForUpdate()
                    ->first();

                $piece = Piece::findOrFail($p['piece_id']);

                if (! $stock || $stock->quantite < $p['quantite']) {
                    throw ValidationException::withMessages([
                        'pieces' => "Stock insuffisant pour « {$piece->libelle} » dans l'agence.",
                    ]);
                }

                $stock->decrement('quantite', $p['quantite']);

                $intervention->lignesConsommation()->create([
                    'piece_id' => $piece->id,
                    'quantite' => $p['quantite'],
                    'prix_unitaire' => $piece->prix,
                ]);
            }

            foreach ($data['photos'] ?? [] as $photo) {
                $chemin = $this->enregistrerPhoto($photo, $intervention->id);
                if ($chemin) {
                    $intervention->photos()->create(['chemin_fichier' => $chemin]);
                }
            }

            $intervention->update([
                'rapport' => $data['rapport'],
                'signature_client' => $data['signature_client'] ?? null,
                'statut' => 'cloturee',
                'date_cloture' => $data['date_cloture'] ?? now(),
            ]);

            // Facturation : gratuite sous garantie, sinon pièces + forfait main d'œuvre
            $intervention->load('lignesConsommation', 'equipement.exploitation');
            $montantHt = $intervention->sous_garantie ? 0 : $intervention->coutPieces() + config('irrig.forfait_main_oeuvre');
            Facture::create([
                'numero' => Facture::prochainNumero(),
                'client_id' => $intervention->equipement->exploitation->client_id,
                'intervention_id' => $intervention->id,
                'montant_ttc' => round($montantHt * 1.2, 2),
                'statut_paiement' => $montantHt == 0 ? 'payee' : 'impayee',
                'date' => now()->toDateString(),
            ]);
        });

        Notificateur::envoyer(
            $intervention->equipement->exploitation->client,
            'IRRIG : votre intervention n°'.$intervention->id.' est terminée. Donnez votre avis sur le portail client.'
        );

        return $intervention->fresh(['lignesConsommation.piece', 'photos', 'facture']);
    }

    /** Accepte une image envoyée en fichier (web) ou en base64 (application mobile). */
    private function enregistrerPhoto(mixed $photo, int $interventionId): ?string
    {
        $dossier = "interventions/{$interventionId}";

        if ($photo instanceof \Illuminate\Http\UploadedFile) {
            return $photo->store($dossier, 'public');
        }

        if (is_string($photo) && $photo !== '') {
            $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $photo);
            $binaire = base64_decode($base64, true);
            if ($binaire === false) {
                return null;
            }
            $chemin = $dossier.'/'.Str::uuid().'.jpg';
            Storage::disk('public')->put($chemin, $binaire);

            return $chemin;
        }

        return null;
    }
}
