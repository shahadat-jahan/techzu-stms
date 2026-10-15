<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['day_of_week', 'is_open', 'open_time', 'close_time'])]
class BusinessHour extends Model
{
    protected $table = 'business_hours';

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_open' => 'boolean',
        ];
    }
}
