<?php

use App\Enums\Role;

use App\Models\User;

it('middleware allows admin to access admin route', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = $this->get('/admin/test');
    $response->assertStatus(200);
});

it('middleware allows agent to access agent route', function () {
    $user = User::factory()->agent()->create();
    $this->actingAs($user);

    $response = $this->get('/agent/test');
    $response->assertStatus(200);
});

it('middleware allows admin to access agent route', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = $this->get('/agent/test');
    $response->assertStatus(200);
});

it('middleware denies customer from accessing admin route', function () {
    $user = User::factory()->create(); // Customer
    $this->actingAs($user);

    $response = $this->get('/admin/test');
    $response->assertStatus(403);
});

it('middleware denies customer from accessing agent route', function () {
    $user = User::factory()->create(); // Customer
    $this->actingAs($user);

    $response = $this->get('/agent/test');
    $response->assertStatus(403);
});

it('middleware denies agent from accessing admin route', function () {
    $user = User::factory()->agent()->create();
    $this->actingAs($user);

    $response = $this->get('/admin/test');
    $response->assertStatus(403);
});

it('middleware redirects unauthenticated user', function () {
    $response = $this->get('/admin/test');
    $response->assertRedirect('/login');
});