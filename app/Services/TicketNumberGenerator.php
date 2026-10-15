<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketNumberGenerator
{
    public function generate(int $year): string
    {
        // Only Insert if the year is not present
        DB::table('ticket_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
        ]);

        //Lock the row to prevent race condition
        $sequence = DB::table('ticket_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $nextNumber = $sequence->last_number + 1;

        DB::table('ticket_sequences')
            ->where('year', $year)
            ->update(['last_number' => $nextNumber]);

        return sprintf('TKT-%d-%06d', $year, $nextNumber);
    }
}
