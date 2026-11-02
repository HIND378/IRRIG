<?php

namespace App\Console\Commands;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Equipement;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Migration des fichiers Excel des agences (livrable 4).
 *
 * 1. Dans Excel : Fichier > Enregistrer sous > CSV UTF-8 (séparateur ; ou ,)
 * 2. php artisan import:clients storage/app/import/agadir.csv --agence=Agadir
 *    (ajouter --simulation pour contrôler sans rien enregistrer)
 *
 * Colonnes attendues (en-tête, l'ordre est libre) :
 *   nom ; type ; telephone ; commune ; culture ; superficie_ha ;
 *   type_equipement ; numero_serie ; date_installation
 *
 * Règles qualité appliquées : téléphone normalisé +212, doublons fusionnés
 * par téléphone, champs obligatoires, type d'équipement ramené à la liste
 * fermée, dates converties en AAAA-MM-JJ. Les lignes rejetées sont écrites
 * dans un rapport d'anomalies CSV.
 */
class ImportClients extends Command
{
    protected $signature = 'import:clients {fichier : chemin du fichier CSV} {--agence= : ville de l\'agence} {--simulation : contrôle seulement, sans import}';

    protected $description = 'Importe les clients, exploitations et équipements depuis un export CSV des fichiers Excel';

    private const TYPES_EQUIPEMENT = [
        'goutte' => 'goutte_a_goutte', 'g-a-g' => 'goutte_a_goutte', 'gag' => 'goutte_a_goutte',
        'pompe' => 'pompe_solaire', 'solaire' => 'pompe_solaire',
        'capteur' => 'capteur_humidite', 'humidit' => 'capteur_humidite',
        'programm' => 'programmateur',
        'filtr' => 'filtration',
    ];

    public function handle(): int
    {
        $fichier = $this->argument('fichier');
        if (! is_readable($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $agence = Agence::where('ville', $this->option('agence'))->first();
        if (! $agence) {
            $this->error('Agence inconnue. Utilisez --agence='.Agence::pluck('ville')->join('|'));

            return self::FAILURE;
        }

        [$entetes, $lignes] = $this->lireCsv($fichier);
        $anomalies = [];
        $stats = ['lignes' => count($lignes), 'clients_crees' => 0, 'clients_fusionnes' => 0, 'equipements' => 0];

        DB::beginTransaction();
        foreach ($lignes as $n => $valeurs) {
            $ligne = array_combine($entetes, array_pad($valeurs, count($entetes), null));
            $numero = $n + 2; // +1 pour l'en-tête, +1 car Excel commence à 1

            $nom = trim((string) ($ligne['nom'] ?? ''));
            $commune = trim((string) ($ligne['commune'] ?? ''));
            $tel = Client::normaliserTelephone($ligne['telephone'] ?? null);

            if ($nom === '' || $commune === '') {
                $anomalies[] = [$numero, 'Nom ou commune manquant', implode(' | ', $valeurs)];
                continue;
            }
            if (! $tel) {
                $anomalies[] = [$numero, 'Téléphone absent ou invalide', implode(' | ', $valeurs)];
                continue;
            }

            $client = Client::where('telephone', $tel)->first();
            if ($client) {
                $stats['clients_fusionnes']++;
            } else {
                $client = Client::create([
                    'agence_id' => $agence->id,
                    'nom' => mb_convert_case($nom, MB_CASE_TITLE, 'UTF-8'),
                    'type' => $this->typeClient($ligne['type'] ?? '', $nom),
                    'telephone' => $tel,
                ]);
                $stats['clients_crees']++;
            }

            $exploitation = $client->exploitations()->firstOrCreate(
                ['commune' => mb_convert_case($commune, MB_CASE_TITLE, 'UTF-8')],
                [
                    'culture' => $ligne['culture'] ?? null,
                    'superficie_ha' => is_numeric(str_replace(',', '.', (string) ($ligne['superficie_ha'] ?? ''))) ? (float) str_replace(',', '.', $ligne['superficie_ha']) : null,
                ]
            );

            $serie = trim((string) ($ligne['numero_serie'] ?? ''));
            if ($serie !== '') {
                $type = $this->typeEquipement($ligne['type_equipement'] ?? '');
                $date = $this->date($ligne['date_installation'] ?? null);
                if (! $type) {
                    $anomalies[] = [$numero, 'Type d\'équipement non reconnu : '.($ligne['type_equipement'] ?? ''), implode(' | ', $valeurs)];
                } elseif (! $date) {
                    $anomalies[] = [$numero, 'Date d\'installation illisible', implode(' | ', $valeurs)];
                } elseif (Equipement::where('numero_serie', $serie)->exists()) {
                    $anomalies[] = [$numero, "Numéro de série déjà importé : {$serie}", implode(' | ', $valeurs)];
                } else {
                    $exploitation->equipements()->create([
                        'type' => $type,
                        'numero_serie' => $serie,
                        'date_installation' => $date,
                        'fin_garantie' => Carbon::parse($date)->addYears(2)->toDateString(),
                    ]);
                    $stats['equipements']++;
                }
            }
        }

        $this->option('simulation') ? DB::rollBack() : DB::commit();

        $rapport = storage_path('app/anomalies_'.strtolower($agence->ville).'_'.now()->format('Ymd_His').'.csv');
        $f = fopen($rapport, 'w');
        fwrite($f, "\xEF\xBB\xBF"); // BOM pour une ouverture correcte dans Excel
        fputcsv($f, ['ligne', 'anomalie', 'contenu'], ';');
        foreach ($anomalies as $a) {
            fputcsv($f, $a, ';');
        }
        fclose($f);

        $taux = $stats['lignes'] ? round(count($anomalies) / $stats['lignes'] * 100, 1) : 0;
        $this->table(['Indicateur', 'Valeur'], [
            ['Lignes lues', $stats['lignes']],
            ['Clients créés', $stats['clients_crees']],
            ['Doublons fusionnés', $stats['clients_fusionnes']],
            ['Équipements importés', $stats['equipements']],
            ['Lignes en anomalie', count($anomalies)." ({$taux} %)"],
        ]);
        $this->info(($this->option('simulation') ? 'SIMULATION : rien n\'a été enregistré. ' : 'Import terminé. ')."Rapport d'anomalies : {$rapport}");
        if ($taux >= 5) {
            $this->warn('Objectif qualité non atteint (moins de 5 % d\'anomalies) : corriger le fichier et relancer.');
        }

        return self::SUCCESS;
    }

    /** @return array{0: array<string>, 1: array<array<string>>} */
    private function lireCsv(string $fichier): array
    {
        $contenu = file_get_contents($fichier);
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu); // BOM Excel
        $premiere = strtok($contenu, "\n");
        $separateur = substr_count($premiere, ';') >= substr_count($premiere, ',') ? ';' : ',';

        $lignes = array_values(array_filter(
            array_map(fn ($l) => str_getcsv(rtrim($l, "\r"), $separateur), preg_split('/\n/', trim($contenu))),
            fn ($l) => count(array_filter($l, fn ($v) => trim((string) $v) !== '')) > 0
        ));
        $entetes = array_map(fn ($h) => $this->cle($h), array_shift($lignes));

        return [$entetes, $lignes];
    }

    private function cle(string $entete): string
    {
        $c = strtolower(trim($entete));
        $c = strtr($c, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ô' => 'o', 'î' => 'i', 'û' => 'u', 'ç' => 'c', '.' => '']);
        $c = preg_replace('/[^a-z0-9]+/', '_', $c);

        return match (true) {
            str_starts_with($c, 'tel') => 'telephone',
            str_contains($c, 'commune') || str_contains($c, 'douar') => 'commune',
            str_contains($c, 'superficie') => 'superficie_ha',
            str_contains($c, 'serie') => 'numero_serie',
            str_contains($c, 'equipement') || str_contains($c, 'materiel') => 'type_equipement',
            str_contains($c, 'date') => 'date_installation',
            default => trim($c, '_'),
        };
    }

    private function typeClient(string $type, string $nom): string
    {
        $t = strtolower($type.' '.$nom);

        return match (true) {
            str_contains($t, 'coop') => 'cooperative',
            str_contains($t, 'entreprise') || str_contains($t, 'domaine') || str_contains($t, 'sarl') => 'entreprise',
            default => 'petit_exploitant',
        };
    }

    private function typeEquipement(string $valeur): ?string
    {
        $v = strtolower(strtr($valeur, ['é' => 'e', 'à' => 'a']));
        $v = str_replace(' ', '-', $v);
        foreach (self::TYPES_EQUIPEMENT as $motif => $type) {
            if (str_contains($v, $motif)) {
                return $type;
            }
        }

        return null;
    }

    private function date(?string $valeur): ?string
    {
        $valeur = trim((string) $valeur);
        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd/m/y', 'd-m-Y', 'd.m.Y'] as $format) {
            $d = \DateTime::createFromFormat('!'.$format, $valeur);
            if ($d && $d->format($format) === $valeur) {
                return $d->format('Y-m-d');
            }
        }
        if (is_numeric($valeur) && (int) $valeur > 20000) { // date au format numérique Excel
            return Carbon::create(1899, 12, 30)->addDays((int) $valeur)->toDateString();
        }

        return null;
    }
}
