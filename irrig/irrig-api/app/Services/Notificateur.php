<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Log;

/**
 * Envoi des notifications SMS / WhatsApp Business aux agriculteurs.
 *
 * Dans le MVP, les messages sont écrits dans storage/logs/notifications.log
 * (aucun abonnement payant n'est nécessaire pour la démonstration).
 * Pour la mise en production, il suffit de remplacer le corps de envoyer()
 * par un appel HTTP à la passerelle choisie (fournisseur SMS marocain ou
 * API WhatsApp Business Cloud), configurée dans config/irrig.php.
 */
class Notificateur
{
    public static function envoyer(?Client $client, string $message): void
    {
        if (! $client) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/notifications.log'),
        ])->info("[".config('irrig.canal_notification')."] {$client->telephone} : {$message}");
    }
}
