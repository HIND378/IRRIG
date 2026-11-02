<?php

/**
 * Paramètres métier d'IRRIG Connect.
 */
return [
    // Forfait de main d'œuvre facturé hors garantie (DH HT)
    'forfait_main_oeuvre' => (float) env('IRRIG_FORFAIT_MAIN_OEUVRE', 300),

    // Taux de TVA appliqué aux devis et factures
    'tva' => 0.20,

    // Canal de notification des agriculteurs : sms ou whatsapp
    'canal_notification' => env('IRRIG_CANAL_NOTIFICATION', 'sms'),
];
