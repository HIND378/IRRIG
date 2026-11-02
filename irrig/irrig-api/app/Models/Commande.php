<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commande extends Model
{
    protected $fillable = ['devis_id', 'date', 'statut'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }
}
