<?php

namespace App\Enums;

enum SlaStatus: string
{
    case Within = 'within';
    case Approaching = 'approaching';
    case Breached = 'breached';
    case Met = 'met';

    public function label(): string
    {
        return match ($this) {
            self::Within => 'Within SLA',
            self::Approaching => 'Approaching Breach',
            self::Breached => 'SLA Breached',
            self::Met => 'SLA Met',
        };
    }
}
