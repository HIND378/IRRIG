<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Avis extends Model
{
    protected $table = 'avis';

    protected $fillable = ['intervention_id', 'note', 'commentaire'];

    protected $casts = ['note' => 'integer'];

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }
}
