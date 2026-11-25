<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewCustomers');

        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = trim((string) $request->query('q', ''));

        $results = User::where('role', Role::Customer->value)
            ->searchCustomers($query)
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json($results);
    }
}
