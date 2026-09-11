<?php

use App\Models\User;

test('profile page is displayed as read-only', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information cannot be updated', function () {
    $user = User::factory()->create();
    $originalName = $user->name;
    $originalEmail = $user->email;

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response->assertMethodNotAllowed();

    $user->refresh();

    expect($user->name)->toBe($originalName);
    expect($user->email)->toBe($originalEmail);
});

test('user cannot delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});
