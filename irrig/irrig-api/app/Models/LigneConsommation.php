<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneConsommation extends Model
{
    protected $table = 'lignes_consommation';

    protected $fillable = ['intervention_id', 'piece_id', 'quantite', 'prix_unitaire'];

    protected $casts = [
        'quantite' => 'integer',
        'prix_unitaire' => 'float',
    ];

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }
}
