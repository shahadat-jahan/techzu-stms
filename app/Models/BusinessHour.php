<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function dayName(): Attribute
    {
        return Attribute::make(fn (): string => match ($this->day_of_week) {
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            default => 'Unknown',
        });
    }

    protected function openTimeInput(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->open_time === null ? null : substr($this->open_time, 0, 5));
    }

    protected function closeTimeInput(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->close_time === null ? null : substr($this->close_time, 0, 5));
    }
}
