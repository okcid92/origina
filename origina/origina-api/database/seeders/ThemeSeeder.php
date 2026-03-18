<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ThemeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $student1 = User::where('email', 'student1@origina.local')->first();
        $student2 = User::where('email', 'student2@origina.local')->first();
        $teacher = User::where('email', 'teacher@origina.local')->first();

        if (! $student1 || ! $student2 || ! $teacher) {
            return;
        }

        $themes = [
            [
                'student_id' => $student1->id,
                'title' => 'Detection de plagiat multilingue par NLP',
                'description' => 'Evaluation des approches semantiques et traduction inverse.',
                'status' => 'approved',
                'moderated_by' => $teacher->id,
                'moderation_comment' => 'Theme valide pour demarrage.',
                'moderated_at' => now()->subDays(10),
            ],
            [
                'student_id' => $student2->id,
                'title' => 'Analyse de similarite dans les memoires',
                'description' => 'Comparaison de techniques lexicales et contextuelles.',
                'status' => 'pending',
                'moderated_by' => null,
                'moderation_comment' => null,
                'moderated_at' => null,
            ],
        ];

        foreach ($themes as $theme) {
            DB::table('themes')->updateOrInsert(
                [
                    'student_id' => $theme['student_id'],
                    'title' => $theme['title'],
                ],
                array_merge($theme, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
