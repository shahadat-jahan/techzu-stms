<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $stats = $this->dashboardService->getStats($request->user());

        if ($request->wantsJson()) {
            return response()->json($stats);
        }

        return view('dashboard', compact('stats'));
    }
}
