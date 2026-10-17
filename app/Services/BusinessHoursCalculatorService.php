<?php

namespace App\Services;

use App\Models\BusinessHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class BusinessHoursCalculatorService
{
    public const CACHE_KEY = 'business_hours';

    public function addBusinessMinutes(CarbonInterface $start, int $minutes): CarbonImmutable
    {
        $businessHours = $this->businessHours();
        $timezone = Config::get('support.business_timezone', 'Asia/Dhaka');

        $cursor = CarbonImmutable::instance($start)->tz($timezone)->startOfMinute();
        $remaining = $minutes;

        while ($remaining > 0) {
            $hours = $businessHours[$cursor->dayOfWeek] ?? null;

            if ($hours === null || ! $hours['is_open']) {
                $cursor = $this->openingOfNextBusinessDay($cursor, $businessHours, $timezone);

                continue;
            }

            $open = $cursor->setTimeFromTimeString($hours['open_time']);
            $close = $cursor->setTimeFromTimeString($hours['close_time']);

            if ($cursor->lessThan($open)) {
                $cursor = $open;
            }

            if ($cursor->greaterThanOrEqualTo($close)) {
                $cursor = $this->openingOfNextBusinessDay($cursor, $businessHours, $timezone);

                continue;
            }

            $minutesUntilClose = (int) $cursor->diffInMinutes($close);
            $consumed = min($remaining, $minutesUntilClose);

            $cursor = $cursor->addMinutes($consumed);
            $remaining -= $consumed;

            if ($remaining > 0) {
                $cursor = $this->openingOfNextBusinessDay($cursor, $businessHours, $timezone);
            }
        }

        // Business minutes are counted on the business clock, but the result is
        // returned in UTC because that is what the application persists and compares.
        return $cursor->tz('UTC');
    }

    /**
     * @return array<int, array{is_open: bool, open_time: string, close_time: string}>
     */
    private function businessHours(): array
    {
        // Plain arrays are cached rather than models because a serialized model
        // cannot be reliably revived from a shared cache store.
        $hours = Cache::remember(self::CACHE_KEY, 60, fn (): array => BusinessHour::all()
            ->keyBy('day_of_week')
            ->map(fn (BusinessHour $day): array => [
                'is_open' => $day->is_open,
                'open_time' => $day->open_time,
                'close_time' => $day->close_time,
            ])
            ->all());

        if ($hours === []) {
            throw new RuntimeException('No business hours are configured.');
        }

        // openingOfNextBusinessDay() scans forward until it finds an open weekday,
        // so a configuration with no open weekday would make that scan never end.
        if (array_filter($hours, fn (array $day): bool => $day['is_open']) === []) {
            throw new RuntimeException('No business days are configured as open.');
        }

        return $hours;
    }

    /**
     * @param  array<int, array{is_open: bool, open_time: string, close_time: string}>  $businessHours
     */
    private function openingOfNextBusinessDay(CarbonImmutable $date, array $businessHours, string $timezone): CarbonImmutable
    {
        $next = $date->addDay()->startOfDay();

        while (true) {
            $hours = $businessHours[$next->dayOfWeek] ?? null;

            if ($hours !== null && $hours['is_open']) {
                return $next->setTimeFromTimeString($hours['open_time']);
            }

            $next = $next->addDay();
        }
    }
}
