<?php

use App\Enums\Role;

use App\Models\User;

test('registration with role=admin creates customer user', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin', // This should be ignored
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->first();
    expect($user->role)->toBe(Role::Customer);
});

test('registration with role=agent creates customer user', function () {
    $response = $this->post('/register', [
        'name' => 'Test User 2',
        'email' => 'test2@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'agent', // This should be ignored
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test2@example.com')->first();
    expect($user->role)->toBe(Role::Customer);
});

test('registration without role creates customer user', function () {
    $response = $this->post('/register', [
        'name' => 'Test User 3',
        'email' => 'test3@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test3@example.com')->first();
    expect($user->role)->toBe(Role::Customer);
});