<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Facture extends Model
{
    protected $fillable = ['numero', 'client_id', 'commande_id', 'intervention_id', 'montant_ttc', 'statut_paiement', 'date'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'montant_ttc' => 'float',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public static function prochainNumero(): string
    {
        return 'FAC-'.now()->format('Y').'-'.str_pad((string) (static::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
