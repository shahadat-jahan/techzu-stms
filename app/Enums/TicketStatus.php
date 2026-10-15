<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingForCustomer = 'waiting_for_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::WaitingForCustomer => 'Waiting for Customer',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Open, self::InProgress, self::WaitingForCustomer => true,
            self::Resolved, self::Closed => false,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Open => in_array($next, [
                self::InProgress,
                self::WaitingForCustomer,
                self::Resolved,
                self::Closed,
            ], true),
            self::InProgress => in_array($next, [
                self::Open,
                self::WaitingForCustomer,
                self::Resolved,
                self::Closed,
            ], true),
            self::WaitingForCustomer => in_array($next, [
                self::Open,
                self::InProgress,
                self::Resolved,
                self::Closed,
            ], true),
            self::Resolved => in_array($next, [
                self::Closed,
                self::InProgress,
            ], true),
            self::Closed => in_array($next, [
                self::Open,
            ], true),
        };
    }
}
