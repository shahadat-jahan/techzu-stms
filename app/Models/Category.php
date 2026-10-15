<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'is_active'])]
class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_category');
    }
}