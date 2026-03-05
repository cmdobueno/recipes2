<?php

use App\Models\User;

it('redirects guests to the panel login page', function () {
    $this->get('/admin-recipes')->assertRedirect('/admin-recipes/login');
});

it('forbids non-admin users from accessing user management', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/admin-recipes/users')
        ->assertForbidden();
});

it('allows admin users to access user management', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin-recipes/users')
        ->assertSuccessful();
});
