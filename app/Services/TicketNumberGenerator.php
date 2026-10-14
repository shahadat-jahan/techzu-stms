<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketNumberGenerator
{
    /**
     * Ticket numbers must stay unique under concurrency: the sequence row is
     * locked for update inside a transaction so parallel creations serialize.
     */
    public function generate(?int $year = null): string
    {
        $year ??= now()->year;

        return DB::transaction(function () use ($year) {
            DB::table('ticket_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
            ]);

            $sequence = DB::table('ticket_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $nextNumber = $sequence->last_number + 1;

            DB::table('ticket_sequences')
                ->where('year', $year)
                ->update(['last_number' => $nextNumber]);

            return sprintf('TKT-%d-%06d', $year, $nextNumber);
        });
    }
}
