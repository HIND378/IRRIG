<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\Devis;
use App\Models\Intervention;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Module M6 : tableaux de bord.
 * Les calculs sont faits en PHP pour fonctionner aussi bien sous MySQL que SQLite.
 */
class KpiController extends Controller
{
    /** GET /api/kpi?agence_id= : indicateurs d'une agence (responsable = la sienne). */
    public function agence(Request $request)
    {
        $user = $request->user();
        $agenceId = $user->voitToutesLesAgences()
            ? ($request->integer('agence_id') ?: Agence::value('id'))
            : $user->agence_id;

        return response()->json($this->calculer(Agence::findOrFail($agenceId), $this->periode($request)));
    }

    /** GET /api/kpi/consolides : les 3 agences côte à côte (directeur). */
    public function consolides(Request $request)
    {
        $periode = $this->periode($request);
        $agences = Agence::orderBy('ville')->get()->map(fn ($a) => $this->calculer($a, $periode));

        $toutes = $agences->pluck('interventions_cloturees')->sum();
        $notes = $agences->filter(fn ($a) => $a['satisfaction'] !== null);

        return response()->json([
            'periode' => ['du' => $periode[0]->toDateString(), 'au' => $periode[1]->toDateString()],
            'agences' => $agences,
            'total' => [
                'interventions_cloturees' => $toutes,
                'chiffre_affaires_ttc' => round($agences->sum('chiffre_affaires_ttc'), 2),
                'satisfaction' => $notes->isEmpty() ? null : round($notes->avg('satisfaction'), 2),
                'alertes_stock' => $agences->sum('alertes_stock'),
            ],
        ]);
    }

    /** Période : ?mois=2026-10 (par défaut le mois en cours). */
    private function periode(Request $request): array
    {
        $mois = $request->query('mois');
        $debut = $mois && preg_match('/^\d{4}-\d{2}$/', $mois)
            ? Carbon::createFromFormat('Y-m-d H:i:s', $mois.'-01 00:00:00')
            : now()->startOfMonth();

        return [$debut, $debut->copy()->endOfMonth()];
    }

    private function calculer(Agence $agence, array $periode): array
    {
        [$debut, $fin] = $periode;

        $cloturees = Intervention::with('lignesConsommation', 'avis')
            ->where('agence_id', $agence->id)
            ->where('statut', 'cloturee')
            ->whereBetween('date_cloture', [$debut, $fin])
            ->get();

        $delais = $cloturees->map(fn ($i) => $i->created_at->diffInHours($i->date_cloture) / 24);
        $notes = $cloturees->pluck('avis.note')->filter();

        $caDevis = Devis::where('statut', 'accepte')
            ->whereBetween('updated_at', [$debut, $fin])
            ->whereHas('client', fn ($q) => $q->where('agence_id', $agence->id))
            ->sum('montant_ttc');

        $enCours = Intervention::where('agence_id', $agence->id)
            ->whereIn('statut', ['nouvelle', 'planifiee', 'en_cours'])
            ->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        return [
            'agence_id' => $agence->id,
            'agence' => $agence->ville,
            // les 4 indicateurs du MVP
            'interventions_cloturees' => $cloturees->count(),
            'delai_moyen_jours' => $delais->isEmpty() ? null : round($delais->avg(), 1),
            'cout_moyen_pieces' => $cloturees->isEmpty() ? null : round($cloturees->avg(fn ($i) => $i->coutPieces()), 2),
            'satisfaction' => $notes->isEmpty() ? null : round($notes->avg(), 2),
            // indicateurs complémentaires
            'nombre_avis' => $notes->count(),
            'chiffre_affaires_ttc' => round((float) $caDevis, 2),
            'interventions_en_attente' => [
                'nouvelle' => (int) ($enCours['nouvelle'] ?? 0),
                'planifiee' => (int) ($enCours['planifiee'] ?? 0),
                'en_cours' => (int) ($enCours['en_cours'] ?? 0),
            ],
            'alertes_stock' => Stock::where('agence_id', $agence->id)->whereColumn('quantite', '<=', 'seuil_alerte')->count(),
        ];
    }
}
