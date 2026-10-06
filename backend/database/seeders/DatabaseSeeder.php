<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Cria o usuário de acesso. O volume de cobranças fica em BillingVolumeSeeder.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrador',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }
}
