<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'slug',
    'professional_title',
    'bio',
    'years_experience',
    'specialties',
    'public_languages',
])]
#[Hidden(['avatar_disk', 'avatar_path'])]
class ExpertProfile extends Model
{
    protected function casts(): array
    {
        return [
            'years_experience' => 'integer',
            'specialties' => 'array',
            'public_languages' => 'array',
            'avatar_size' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(Expert::class);
    }
}
