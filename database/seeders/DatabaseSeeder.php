<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Topic;
use App\Models\Day;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //create one admin user with one team
        $admin = User::factory()->withPersonalTeam()->create([
            'name' => 'Kickpush Admin',
            'email' => env('ADMIN_EMAIL'),
            'is_admin' => true,
        ]);

        $topic = new \App\Models\Topic();
        $topic->topic = 'Kickflip';
        $topic->description ='Anywhere, over anything - into and out of grinds not included';
        $topic->created_by = $admin->id;
        $topic->save();

        // Create a day
        $newDay = new \App\Models\Day();
        $newDay->topic_id = $topic->id;
        $newDay->date = now()->toDateTimeString();
        $newDay->save();
    }
}
