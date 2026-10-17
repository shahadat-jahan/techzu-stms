<?php

use App\Models\BusinessHour;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // Business hours are baseline reference data that SLA calculation depends on.
        foreach ([0, 1, 2, 3, 4] as $day) {
            BusinessHour::create([
                'day_of_week' => $day,
                'is_open' => true,
                'open_time' => '09:00:00',
                'close_time' => '18:00:00',
            ]);
        }

        foreach ([5, 6] as $day) {
            BusinessHour::create([
                'day_of_week' => $day,
                'is_open' => false,
                'open_time' => '09:00:00',
                'close_time' => '18:00:00',
            ]);
        }
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function businessClock(CarbonInterface $moment): string
{
    return $moment->tz(config('support.business_timezone'))->format('Y-m-d H:i:s');
}
