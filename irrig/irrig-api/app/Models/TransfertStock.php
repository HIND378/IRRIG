<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransfertStock extends Model
{
    protected $table = 'transferts_stock';

    protected $fillable = [
        'agence_source_id', 'agence_dest_id', 'piece_id', 'quantite',
        'demandeur_id', 'directeur_id', 'statut', 'date_validation',
    ];

    protected $casts = ['date_validation' => 'datetime'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_source_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_dest_id');
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function directeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'directeur_id');
    }
}
