<?php

namespace App\Models;

use App\Enums\Priority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['priority', 'resolution_minutes', 'warning_minutes', 'is_active'])]
class SlaPolicy extends Model
{
    protected $table = 'sla_policies';

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'is_active' => 'boolean',
        ];
    }
}
