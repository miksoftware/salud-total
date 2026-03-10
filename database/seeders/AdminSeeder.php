<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (!User::where('email', 'admin@saludtotal.com')->exists()) {
            User::create([
                'name' => 'Administrador',
                'email' => 'admin@saludtotal.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]);
        }
    }
}
