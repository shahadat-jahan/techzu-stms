<?php

use App\Models\BusinessHour;
use App\Models\User;

it('admin cannot set all business days to closed', function () {
    $admin = User::factory()->admin()->create();

    // Set 6 days to closed first
    BusinessHour::where('day_of_week', '!=', 1)->update(['is_open' => false]);

    $lastOpenDay = BusinessHour::where('day_of_week', 1)->first();

    $response = $this->actingAs($admin)
        ->patch(route('admin.business-hours.update', $lastOpenDay), [
            'is_open' => false,
        ]);

    $response->assertSessionHasErrors(['is_open']);
    expect($lastOpenDay->fresh()->is_open)->toBeTrue();
});
