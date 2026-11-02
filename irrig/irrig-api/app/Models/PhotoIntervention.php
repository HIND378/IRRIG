<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PhotoIntervention extends Model
{
    protected $table = 'photos_intervention';

    protected $fillable = ['intervention_id', 'chemin_fichier'];

    protected $appends = ['url'];

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->chemin_fichier);
    }
}
