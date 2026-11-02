<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipement extends Model
{
    public const TYPES = ['goutte_a_goutte', 'pompe_solaire', 'capteur_humidite', 'programmateur', 'filtration'];

    protected $fillable = ['exploitation_id', 'type', 'modele', 'numero_serie', 'date_installation', 'fin_garantie'];

    protected $casts = [
        'date_installation' => 'date:Y-m-d',
        'fin_garantie' => 'date:Y-m-d',
    ];

    protected $appends = ['sous_garantie'];

    public function exploitation(): BelongsTo
    {
        return $this->belongsTo(Exploitation::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function getSousGarantieAttribute(): bool
    {
        return $this->fin_garantie !== null && $this->fin_garantie->copy()->endOfDay()->isFuture();
    }
}
