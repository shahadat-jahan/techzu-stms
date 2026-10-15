<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSlaPolicyRequest;
use App\Models\SlaPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SlaPolicyController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageSlaSettings');

        $policies = SlaPolicy::orderBy('resolution_minutes')->get();

        return view('admin.sla-policies.index', compact('policies'));
    }

    public function update(UpdateSlaPolicyRequest $request, SlaPolicy $slaPolicy): RedirectResponse
    {
        $slaPolicy->update($request->validated());

        return back()->with('success', "SLA policy for {$slaPolicy->priority->label()} updated successfully.");
    }
}
