<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Devis extends Model
{
    public const TVA = 0.20;

    protected $table = 'devis';

    protected $fillable = [
        'numero', 'client_id', 'commercial_id', 'date', 'lignes',
        'montant_ht', 'montant_ttc', 'statut', 'motif_refus',
    ];

    protected $casts = [
        'lignes' => 'array',
        'date' => 'date:Y-m-d',
        'montant_ht' => 'float',
        'montant_ttc' => 'float',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    public function commande(): HasOne
    {
        return $this->hasOne(Commande::class);
    }
}
