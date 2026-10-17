<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketNumberGeneratorService
{
    /**
     * Single ticket number. Must run inside a transaction (row lock).
     */
    public function generate(int $year): string
    {
        return $this->reserve($year, 1)[0];
    }

    /**
     * Reserve a block of consecutive ticket numbers with ONE lock + ONE update.
     * Must run inside a transaction: the sequence row stays locked until commit,
     * so concurrent callers cannot receive the same numbers.
     *
     * @return list<string>
     */
    public function reserve(int $year, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        // Only insert if the year is not present
        DB::table('ticket_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
        ]);

        // Lock the row to prevent race condition
        $sequence = DB::table('ticket_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $first = $sequence->last_number + 1;
        $last = $sequence->last_number + $count;

        DB::table('ticket_sequences')
            ->where('year', $year)
            ->update(['last_number' => $last]);

        return array_map(
            fn (int $number): string => sprintf('TKT-%d-%06d', $year, $number),
            range($first, $last)
        );
    }
}
