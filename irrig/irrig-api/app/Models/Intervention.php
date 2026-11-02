<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Intervention extends Model
{
    public const STATUTS = ['nouvelle', 'planifiee', 'en_cours', 'cloturee', 'annulee'];

    protected $fillable = [
        'equipement_id', 'agence_id', 'technicien_id', 'demandeur_id', 'type', 'statut', 'canal',
        'description', 'sous_garantie', 'date_planifiee', 'date_cloture', 'rapport',
        'signature_client', 'uuid_mobile',
    ];

    protected $casts = [
        'sous_garantie' => 'boolean',
        'date_planifiee' => 'datetime',
        'date_cloture' => 'datetime',
    ];

    public function equipement(): BelongsTo
    {
        return $this->belongsTo(Equipement::class);
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technicien_id');
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function lignesConsommation(): HasMany
    {
        return $this->hasMany(LigneConsommation::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PhotoIntervention::class);
    }

    public function avis(): HasOne
    {
        return $this->hasOne(Avis::class);
    }

    public function facture(): HasOne
    {
        return $this->hasOne(Facture::class);
    }

    /** Coût des pièces consommées (indicateur « coût d'intervention »). */
    public function coutPieces(): float
    {
        return (float) $this->lignesConsommation->sum(fn ($l) => $l->quantite * $l->prix_unitaire);
    }
}
