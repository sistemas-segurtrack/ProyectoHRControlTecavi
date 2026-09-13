<?php

use App\Models\User;

test('la raiz redirige al login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('un usuario autenticado que cae en la raiz termina en rutas', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('login'));

    // La raiz reenvia a /login, y /login (middleware `guest`) reenvia a su
    // vez a un usuario ya autenticado -- confirma que el usuario no se queda
    // varado en el login.
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect('/modulos/rutas');
});
