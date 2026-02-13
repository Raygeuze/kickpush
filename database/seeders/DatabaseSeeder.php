<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //create one admin user with one team
        User::factory()->withPersonalTeam()->create([
            'name' => 'kickpush Admin',
            'email' => env('ADMIN_EMAIL'),
            'is_admin' => true,
        ]);
    }
}
