<?php

namespace Database\Seeders;

use App\Models\Agence;
use App\Models\Avis;
use App\Models\Client;
use App\Models\Devis;
use App\Models\Equipement;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration : 3 agences, un compte par rôle,
 * 24 clients avec exploitations et équipements, catalogue de pièces,
 * stock par agence, interventions à tous les statuts et avis.
 *
 * Mot de passe de tous les comptes : password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $mdp = 'password';

        // ---------- Agences ----------
        $agences = collect([
            ['ville' => 'Agadir', 'adresse' => 'Zone industrielle Tassila, Agadir', 'telephone' => '+212528000001'],
            ['ville' => 'Rabat', 'adresse' => 'Avenue Hassan II, Rabat', 'telephone' => '+212537000002'],
            ['ville' => 'Casablanca', 'adresse' => 'Boulevard Zerktouni, Casablanca', 'telephone' => '+212522000003'],
        ])->map(fn ($a) => Agence::create($a))->keyBy('ville');

        // ---------- Comptes du siège ----------
        User::create(['name' => 'Karim Directeur', 'email' => 'directeur@irrig.ma', 'role' => 'directeur', 'telephone' => '+212661000000', 'password' => $mdp]);
        User::create(['name' => 'Sara Admin', 'email' => 'admin@irrig.ma', 'role' => 'admin', 'telephone' => '+212661000099', 'password' => $mdp]);

        // ---------- Comptes par agence ----------
        $techniciens = [];
        foreach ($agences as $ville => $agence) {
            $v = strtolower($ville);
            User::create(['name' => "Responsable {$ville}", 'email' => "responsable.{$v}@irrig.ma", 'role' => 'responsable', 'agence_id' => $agence->id, 'password' => $mdp]);
            User::create(['name' => "Commercial {$ville}", 'email' => "commercial.{$v}@irrig.ma", 'role' => 'commercial', 'agence_id' => $agence->id, 'password' => $mdp]);
            User::create(['name' => "Magasinier {$ville}", 'email' => "magasinier.{$v}@irrig.ma", 'role' => 'magasinier', 'agence_id' => $agence->id, 'password' => $mdp]);
            $techniciens[$ville] = [
                User::create(['name' => "Technicien 1 {$ville}", 'email' => "technicien1.{$v}@irrig.ma", 'role' => 'technicien', 'agence_id' => $agence->id, 'specialite' => 'Pompage solaire', 'zone' => 'Nord', 'telephone' => '+21266'.rand(1000000, 9999999), 'password' => $mdp]),
                User::create(['name' => "Technicien 2 {$ville}", 'email' => "technicien2.{$v}@irrig.ma", 'role' => 'technicien', 'agence_id' => $agence->id, 'specialite' => 'Goutte-à-goutte', 'zone' => 'Sud', 'telephone' => '+21266'.rand(1000000, 9999999), 'password' => $mdp]),
            ];
        }

        // ---------- Catalogue de pièces + stock par agence ----------
        $pieces = collect([
            ['GG-TUY-16', 'Tuyau goutte-à-goutte 16 mm (100 m)', 450],
            ['GG-GOU-2L', 'Goutteur autorégulant 2 L/h (lot de 100)', 180],
            ['GG-RAC-16', 'Raccord 16 mm (lot de 50)', 95],
            ['FI-TAM-2', 'Filtre à tamis 2 pouces', 620],
            ['PS-PAN-400', 'Panneau solaire 400 W', 1650],
            ['PS-VAR-3K', 'Variateur pompe solaire 3 kW', 4200],
            ['PS-POM-3K', 'Pompe immergée 3 kW', 6800],
            ['CA-HUM-01', 'Capteur d\'humidité du sol', 890],
            ['PR-PRO-6', 'Programmateur 6 voies', 1250],
            ['EL-CAB-6', 'Câble électrique 6 mm² (50 m)', 540],
        ])->map(fn ($p) => Piece::create(['reference' => $p[0], 'libelle' => $p[1], 'prix' => $p[2]]));

        foreach ($agences as $agence) {
            foreach ($pieces as $i => $piece) {
                Stock::create([
                    'agence_id' => $agence->id,
                    'piece_id' => $piece->id,
                    'quantite' => ($i * 7 + $agence->id * 3) % 25,   // quelques pièces sous le seuil
                    'seuil_alerte' => 5,
                ]);
            }
        }

        // ---------- Clients, exploitations, équipements ----------
        $noms = [
            'Agadir' => ['Ahmed Benali', 'Coopérative Souss Al Khair', 'Fatima Ait Ouahmane', 'Domaine Taroudant Agri', 'Lahcen Idrissi', 'Coopérative Chtouka Bio', 'Mohamed Oubella', 'Rachida El Amrani'],
            'Rabat' => ['Youssef Alaoui', 'Coopérative Zaër', 'Khadija Bennani', 'Ferme Sidi Allal', 'Hassan Tazi', 'Nadia Chraibi', 'Coopérative Gharb Vert', 'Omar Fassi'],
            'Casablanca' => ['Abdellah Berrada', 'Domaine Berrechid', 'Samira Lahlou', 'Coopérative Doukkala', 'Rachid Kettani', 'Meriem Sqalli', 'Ferme Settat Agro', 'Driss Naciri'],
        ];
        $communes = [
            'Agadir' => ['Taroudant', 'Biougra', 'Ait Melloul', 'Oulad Teima'],
            'Rabat' => ['Sidi Allal El Bahraoui', 'Tiflet', 'Rommani', 'Sidi Yahya Zaër'],
            'Casablanca' => ['Berrechid', 'Settat', 'Had Soualem', 'Bouskoura'],
        ];
        $cultures = ['Agrumes', 'Tomates sous serre', 'Olivier', 'Maraîchage', 'Arganier', 'Céréales'];
        $typesEq = Equipement::TYPES;

        $equipements = [];
        $num = 0;
        foreach ($noms as $ville => $liste) {
            foreach ($liste as $k => $nom) {
                $num++;
                $client = Client::create([
                    'agence_id' => $agences[$ville]->id,
                    'nom' => $nom,
                    'type' => str_contains($nom, 'Coopérative') ? 'cooperative' : (str_contains($nom, 'Domaine') || str_contains($nom, 'Ferme') ? 'entreprise' : 'petit_exploitant'),
                    'telephone' => '+2126'.str_pad((string) (10000000 + $num * 1371), 8, '0', STR_PAD_LEFT),
                    'langue' => $k % 2 ? 'fr' : 'ar',
                ]);
                $exploitation = $client->exploitations()->create([
                    'nom' => 'Parcelle '.($k + 1),
                    'commune' => $communes[$ville][$k % 4],
                    'superficie_ha' => 2 + ($num * 3) % 40,
                    'culture' => $cultures[$num % count($cultures)],
                    'latitude' => ['Agadir' => 30.42, 'Rabat' => 33.97, 'Casablanca' => 33.57][$ville] + ($k * 0.03),
                    'longitude' => ['Agadir' => -9.25, 'Rabat' => -6.62, 'Casablanca' => -7.41][$ville] + ($k * 0.04),
                ]);
                foreach ([0, 1] as $e) {
                    $installe = now()->subMonths(4 + ($num * 5 + $e * 7) % 30);
                    $equipements[$ville][] = $exploitation->equipements()->create([
                        'type' => $typesEq[($num + $e * 2) % count($typesEq)],
                        'modele' => 'Série '.chr(65 + ($num % 5)),
                        'numero_serie' => sprintf('IRG-%s-%04d', strtoupper(substr($ville, 0, 3)), $num * 10 + $e),
                        'date_installation' => $installe->toDateString(),
                        'fin_garantie' => $installe->copy()->addYears(2)->toDateString(),
                    ]);
                }
            }
        }

        // Compte du portail client pour le premier client d'Agadir
        $agriculteur = Client::where('nom', 'Ahmed Benali')->first();
        User::create(['name' => 'Ahmed Benali', 'email' => 'agriculteur@irrig.ma', 'role' => 'agriculteur', 'client_id' => $agriculteur->id, 'telephone' => $agriculteur->telephone, 'password' => $mdp]);

        // ---------- Interventions à tous les statuts ----------
        $descriptions = [
            'Le débit des goutteurs a fortement baissé sur la parcelle.',
            'La pompe solaire ne démarre plus le matin.',
            'Fuite importante sur la conduite principale.',
            'Le capteur d\'humidité n\'envoie plus de mesure.',
            'Maintenance préventive annuelle.',
            'Le programmateur n\'ouvre plus la vanne 3.',
        ];
        foreach ($agences as $ville => $agence) {
            foreach ($equipements[$ville] as $i => $eq) {
                if ($i > 11) {
                    break;
                }
                $statut = ['cloturee', 'cloturee', 'cloturee', 'cloturee', 'cloturee', 'planifiee', 'planifiee', 'nouvelle', 'nouvelle', 'en_cours', 'cloturee', 'annulee'][$i];
                $tech = $techniciens[$ville][$i % 2];
                $demande = now()->subDays(25 - $i * 2)->setTime(9, 0);

                $intervention = new Intervention([
                    'equipement_id' => $eq->id,
                    'agence_id' => $agence->id,
                    'technicien_id' => $statut === 'nouvelle' ? null : $tech->id,
                    'type' => $i % 5 === 4 ? 'maintenance' : 'panne',
                    'statut' => $statut,
                    'canal' => ['portail', 'telephone', 'whatsapp', 'agence'][$i % 4],
                    'description' => $descriptions[$i % count($descriptions)],
                    'sous_garantie' => $eq->sous_garantie,
                    'date_planifiee' => in_array($statut, ['planifiee', 'en_cours'], true) ? now()->addDays($i % 3)->setTime(10, 0) : ($statut === 'cloturee' ? $demande->copy()->addDays(1 + $i % 3) : null),
                    'date_cloture' => $statut === 'cloturee' ? $demande->copy()->addDays(1 + $i % 4)->setTime(16, 0) : null,
                    'rapport' => $statut === 'cloturee' ? 'Diagnostic effectué, pièce remplacée, test de fonctionnement concluant.' : null,
                    'signature_client' => $statut === 'cloturee' ? $eq->exploitation->client->nom : null,
                ]);
                $intervention->created_at = $demande;
                $intervention->updated_at = $demande;
                $intervention->save();

                if ($statut === 'cloturee') {
                    $piece = $pieces[$i % $pieces->count()];
                    $intervention->lignesConsommation()->create(['piece_id' => $piece->id, 'quantite' => 1 + $i % 2, 'prix_unitaire' => $piece->prix]);
                    $intervention->load('lignesConsommation');
                    $ht = $intervention->sous_garantie ? 0 : $intervention->coutPieces() + config('irrig.forfait_main_oeuvre');
                    Facture::create([
                        'numero' => Facture::prochainNumero(),
                        'client_id' => $eq->exploitation->client_id,
                        'intervention_id' => $intervention->id,
                        'montant_ttc' => round($ht * 1.2, 2),
                        'statut_paiement' => $ht == 0 ? 'payee' : ($i % 2 ? 'payee' : 'impayee'),
                        'date' => $intervention->date_cloture->toDateString(),
                    ]);
                    if ($i !== 4) {
                        Avis::create(['intervention_id' => $intervention->id, 'note' => [5, 4, 3, 5, 4, 5, 4, 2, 5, 4, 5, 3][$i], 'commentaire' => $i % 3 ? null : 'Technicien ponctuel et efficace.']);
                    }
                }
            }
        }

        // ---------- Quelques devis ----------
        foreach ($agences as $ville => $agence) {
            $commercial = User::where('role', 'commercial')->where('agence_id', $agence->id)->first();
            foreach (Client::where('agence_id', $agence->id)->take(3)->get() as $j => $client) {
                $lignes = [
                    ['designation' => 'Kit goutte-à-goutte 1 ha', 'quantite' => 2, 'prix_unitaire' => 8500],
                    ['designation' => 'Installation et mise en service', 'quantite' => 1, 'prix_unitaire' => 2500],
                ];
                $ht = 2 * 8500 + 2500;
                Devis::create([
                    'numero' => 'DEV-'.now()->format('Y').'-'.str_pad((string) (Devis::max('id') + 1), 5, '0', STR_PAD_LEFT),
                    'client_id' => $client->id,
                    'commercial_id' => $commercial->id,
                    'date' => now()->subDays(10 - $j)->toDateString(),
                    'lignes' => $lignes,
                    'montant_ht' => $ht,
                    'montant_ttc' => $ht * 1.2,
                    'statut' => ['envoye', 'envoye', 'refuse'][$j],
                    'motif_refus' => $j === 2 ? 'Prix jugé trop élevé' : null,
                ]);
            }
        }
    }
}
