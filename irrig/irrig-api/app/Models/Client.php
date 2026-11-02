<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Client extends Model
{
    protected $fillable = ['agence_id', 'nom', 'type', 'telephone', 'email', 'langue'];

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function exploitations(): HasMany
    {
        return $this->hasMany(Exploitation::class);
    }

    public function equipements(): HasManyThrough
    {
        return $this->hasManyThrough(Equipement::class, Exploitation::class);
    }

    public function devis(): HasMany
    {
        return $this->hasMany(Devis::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    /** Normalise un numéro marocain au format +212XXXXXXXXX (règle qualité du plan de migration). */
    public static function normaliserTelephone(?string $tel): ?string
    {
        if ($tel === null) {
            return null;
        }
        $chiffres = preg_replace('/\D+/', '', $tel);
        if (str_starts_with($chiffres, '00212')) {
            $chiffres = substr($chiffres, 5);
        } elseif (str_starts_with($chiffres, '212')) {
            $chiffres = substr($chiffres, 3);
        } elseif (str_starts_with($chiffres, '0')) {
            $chiffres = substr($chiffres, 1);
        }

        return preg_match('/^[5-7]\d{8}$/', $chiffres) ? '+212'.$chiffres : null;
    }
}
