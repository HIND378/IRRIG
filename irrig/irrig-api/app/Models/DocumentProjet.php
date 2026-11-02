<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentProjet extends Model
{
    protected $table = 'documents_projet';

    protected $fillable = ['exploitation_id', 'auteur_id', 'type', 'titre', 'chemin_fichier'];

    protected $appends = ['url'];

    public function exploitation(): BelongsTo
    {
        return $this->belongsTo(Exploitation::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->chemin_fichier);
    }
}
