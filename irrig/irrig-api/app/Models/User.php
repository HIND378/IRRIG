<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Utilisateur (classe mère du diagramme de classes).
 * Les sous-classes Technicien, Commercial, ResponsableAgence et Directeur
 * sont distinguées par la colonne `role`.
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public const ROLES = [
        'agriculteur', 'commercial', 'responsable', 'directeur',
        'technicien', 'magasinier', 'admin',
    ];

    protected $fillable = [
        'name', 'email', 'telephone', 'role', 'agence_id', 'client_id',
        'specialite', 'zone', 'actif', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Intervention::class, 'technicien_id');
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Le directeur et l'administrateur voient les 3 agences. */
    public function voitToutesLesAgences(): bool
    {
        return $this->hasRole('directeur', 'admin');
    }

    /**
     * Restreint une requête à l'agence de l'utilisateur, sauf pour le directeur/admin.
     * Le directeur peut filtrer une agence précise avec ?agence_id=.
     */
    public function filtrerParAgence(Builder $query, ?int $agenceDemandee = null, string $colonne = 'agence_id'): Builder
    {
        if ($this->voitToutesLesAgences()) {
            return $agenceDemandee ? $query->where($colonne, $agenceDemandee) : $query;
        }

        return $query->where($colonne, $this->agence_id);
    }
}
