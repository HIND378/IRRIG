<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    protected $fillable = ['agence_id', 'piece_id', 'quantite', 'seuil_alerte'];

    protected $casts = [
        'quantite' => 'integer',
        'seuil_alerte' => 'integer',
    ];

    protected $appends = ['sous_seuil'];

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function estSousSeuil(): bool
    {
        return $this->quantite <= $this->seuil_alerte;
    }

    public function getSousSeuilAttribute(): bool
    {
        return $this->estSousSeuil();
    }
}
