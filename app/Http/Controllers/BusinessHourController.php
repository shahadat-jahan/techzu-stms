<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessHourRequest;
use App\Models\BusinessHour;
use App\Services\BusinessHoursCalculatorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class BusinessHourController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageSlaSettings');

        $businessHours = BusinessHour::orderBy('day_of_week')->get();

        return view('admin.business-hours.index', compact('businessHours'));
    }

    public function update(UpdateBusinessHourRequest $request, BusinessHour $businessHour): RedirectResponse
    {
        $businessHour->update($request->validated());

        Cache::forget(BusinessHoursCalculatorService::CACHE_KEY);

        return back()->with('success', 'Business hours updated successfully.');
    }
}
