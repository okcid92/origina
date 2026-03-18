<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('mon926732');

        $users = [
            [
                'name' => 'Admin Origina',
                'email' => 'admin@origina.local',
                'role' => 'admin',
                'department' => 'Direction',
            ],
            [
                'name' => 'Chef Departement Info',
                'email' => 'teacher@origina.local',
                'role' => 'teacher',
                'department' => 'Informatique',
            ],
            [
                'name' => 'Direction des Etudes',
                'email' => 'da@origina.local',
                'role' => 'da',
                'department' => 'Scolarite',
            ],
            [
                'name' => 'Commission VAR',
                'email' => 'var@origina.local',
                'role' => 'var',
                'department' => 'VAR',
            ],
            [
                'name' => 'Etudiant Test 1',
                'email' => 'student1@origina.local',
                'role' => 'student',
                'department' => 'Informatique',
            ],
            [
                'name' => 'Etudiant Test 2',
                'email' => 'student2@origina.local',
                'role' => 'student',
                'department' => 'Informatique',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => $password,
                    'role' => $user['role'],
                    'department' => $user['department'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
