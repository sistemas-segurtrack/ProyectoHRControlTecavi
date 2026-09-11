<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => config('services.admin.email')],
            [
                'name' => 'Desarrollo Segurtrack',
                'password' => Hash::make((string) config('services.admin.password')),
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(['admin']);
    }
}
