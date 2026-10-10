<?php

namespace App\Models;

use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'event_types', 'is_premium', 'config', 'sort_order', 'is_active'])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'event_types' => 'array',
            'is_premium' => 'boolean',
            'config' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
