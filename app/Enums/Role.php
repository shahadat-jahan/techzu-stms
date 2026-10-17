<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Agent = 'agent';
    case Customer = 'customer';

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isAgent(): bool
    {
        return $this === self::Agent;
    }

    public function isCustomer(): bool
    {
        return $this === self::Customer;
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Agent => 'Agent',
            self::Customer => 'Customer',
        };
    }
}
