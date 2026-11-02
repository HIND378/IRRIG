<?php

namespace Tests\Feature;

use App\Models\Equipement;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Parcours complet de la checklist de la séance 2 :
 * demande -> planification -> clôture -> stock décrémenté -> facture -> avis,
 * plus la synchronisation hors ligne sans doublon et le contrôle des rôles.
 *
 * Lancer : php artisan test
 */
class ParcoursInterventionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** Requête authentifiée ; oublie l'utilisateur mis en cache par la requête précédente. */
    private function en(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function connecter(string $email): string
    {
        $this->app['auth']->forgetGuards();

        return $this->withoutToken()->postJson('/api/login', ['email' => $email, 'password' => 'password'])
            ->assertOk()
            ->json('token');
    }

    public function test_parcours_complet_d_une_intervention(): void
    {
        // 1. L'agriculteur dépose une demande depuis le portail
        $tAgri = $this->connecter('agriculteur@irrig.ma');
        $equipementId = $this->en($tAgri)->getJson('/api/mes-equipements')
            ->assertOk()->json('0.equipements.0.id');

        $id = $this->en($tAgri)->postJson('/api/interventions', [
            'equipement_id' => $equipementId,
            'type' => 'panne',
            'description' => 'Plus de pression dans les goutteurs.',
        ])->assertCreated()->assertJsonPath('statut', 'nouvelle')->json('id');

        // 2. Le responsable d'Agadir affecte un technicien
        $tResp = $this->connecter('responsable.agadir@irrig.ma');
        $technicien = User::where('email', 'technicien1.agadir@irrig.ma')->first();
        $this->en($tResp)->putJson("/api/interventions/{$id}/planifier", [
            'technicien_id' => $technicien->id,
            'date_planifiee' => now()->addDay()->toDateTimeString(),
        ])->assertOk()->assertJsonPath('statut', 'planifiee');

        // 3. Le technicien voit la mission et la clôture avec une pièce
        $tTech = $this->connecter('technicien1.agadir@irrig.ma');
        $this->en($tTech)->getJson('/api/mes-missions')->assertOk()->assertJsonFragment(['id' => $id]);

        $agenceId = Intervention::find($id)->agence_id;
        $stock = Stock::where('agence_id', $agenceId)->where('quantite', '>=', 2)->first();
        $avant = $stock->quantite;

        $this->en($tTech)->postJson("/api/interventions/{$id}/cloturer", [
            'rapport' => 'Filtre colmaté, nettoyé et goutteurs remplacés.',
            'pieces' => [['piece_id' => $stock->piece_id, 'quantite' => 2]],
        ])->assertOk()->assertJsonPath('statut', 'cloturee');

        // 4. Le stock a été décrémenté et une facture créée
        $this->assertSame($avant - 2, $stock->fresh()->quantite);
        $this->assertDatabaseHas('factures', ['intervention_id' => $id]);

        // 5. L'agriculteur donne son avis, une seule fois
        $this->en($tAgri)->postJson("/api/interventions/{$id}/avis", ['note' => 5])->assertCreated();
        $this->en($tAgri)->postJson("/api/interventions/{$id}/avis", ['note' => 4])->assertStatus(422);
    }

    public function test_la_cloture_est_refusee_si_le_stock_est_insuffisant(): void
    {
        $intervention = Intervention::where('statut', 'planifiee')->first();
        $tTech = $this->connecter($intervention->technicien->email);
        $stock = Stock::where('agence_id', $intervention->agence_id)->first();

        $this->en($tTech)->postJson("/api/interventions/{$intervention->id}/cloturer", [
            'rapport' => 'Test',
            'pieces' => [['piece_id' => $stock->piece_id, 'quantite' => $stock->quantite + 100]],
        ])->assertStatus(422);

        // transaction annulée : rien n'a changé
        $this->assertSame('planifiee', $intervention->fresh()->statut);
        $this->assertSame($stock->quantite, $stock->fresh()->quantite);
    }

    public function test_la_synchronisation_hors_ligne_ne_cree_pas_de_doublon(): void
    {
        $intervention = Intervention::where('statut', 'planifiee')->first();
        $tTech = $this->connecter($intervention->technicien->email);
        $rapport = [
            'uuid' => (string) Str::uuid(),
            'intervention_id' => $intervention->id,
            'rapport' => 'Saisi sans réseau sur le terrain.',
            'pieces' => [],
        ];

        $this->en($tTech)->postJson('/api/sync', ['rapports' => [$rapport]])
            ->assertOk()->assertJsonPath('resultats.0.statut', 'ok');

        // Deuxième envoi du même rapport (coupure réseau pendant la réponse)
        $this->en($tTech)->postJson('/api/sync', ['rapports' => [$rapport]])
            ->assertOk()->assertJsonPath('resultats.0.statut', 'deja_synchronise');

        $this->assertSame(1, \App\Models\Facture::where('intervention_id', $intervention->id)->count());
    }

    public function test_chaque_role_est_limite_a_ses_actions(): void
    {
        $tTech = $this->connecter('technicien1.rabat@irrig.ma');
        $this->en($tTech)->getJson('/api/kpi/consolides')->assertForbidden();
        $this->en($tTech)->getJson('/api/users')->assertForbidden();

        // Le responsable n'accède pas aux KPI consolidés, le directeur oui
        $this->en($this->connecter('responsable.rabat@irrig.ma'))->getJson('/api/kpi/consolides')->assertForbidden();
        $this->en($this->connecter('directeur@irrig.ma'))->getJson('/api/kpi/consolides')
            ->assertOk()->assertJsonCount(3, 'agences');

        // Généralisation : le directeur peut planifier comme un responsable
        $this->en($this->connecter('directeur@irrig.ma'))->getJson('/api/techniciens')->assertOk();
    }

    public function test_un_transfert_inter_agences_doit_etre_valide_par_le_directeur(): void
    {
        $piece = Piece::first();
        $source = Stock::whereHas('agence', fn ($q) => $q->where('ville', 'Rabat'))->where('piece_id', $piece->id)->first();
        $source->update(['quantite' => 10]);

        $tMag = $this->connecter('magasinier.agadir@irrig.ma');
        $transfertId = $this->en($tMag)->postJson('/api/transferts', [
            'agence_source_id' => $source->agence_id,
            'piece_id' => $piece->id,
            'quantite' => 4,
        ])->assertCreated()->json('id');

        $this->en($tMag)->putJson("/api/transferts/{$transfertId}/valider", ['decision' => 'valide'])->assertForbidden();
        $this->en($this->connecter('directeur@irrig.ma'))
            ->putJson("/api/transferts/{$transfertId}/valider", ['decision' => 'valide'])
            ->assertOk()->assertJsonPath('statut', 'valide');

        $this->assertSame(6, $source->fresh()->quantite);
    }

    public function test_le_telephone_est_normalise(): void
    {
        $this->assertSame('+212612345678', \App\Models\Client::normaliserTelephone('06 12 34 56 78'));
        $this->assertSame('+212612345678', \App\Models\Client::normaliserTelephone('+212 6 12 34 56 78'));
        $this->assertNull(\App\Models\Client::normaliserTelephone('12345'));
    }
}
