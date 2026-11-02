<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** PieceDetachee du diagramme de classes. */
class Piece extends Model
{
    protected $fillable = ['reference', 'libelle', 'prix'];

    protected $casts = ['prix' => 'float'];

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
