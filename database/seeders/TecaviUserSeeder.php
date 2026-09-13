<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TecaviUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => config('services.tecavi.email')],
            [
                'name' => 'Tecavi',
                'password' => Hash::make((string) config('services.tecavi.password')),
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(['usuario']);
    }
}
