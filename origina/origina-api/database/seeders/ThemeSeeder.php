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
        $student1 = User::where('email', 'dickoalou04@gmail.com')->first();
        $student2 = User::where('email', 'student2@origina.local')->first();
        $teacher = User::where('email', 'teacher@origina.local')->first();
        $da = User::where('email', 'da@origina.local')->first();

        if (! $student1 || ! $student2 || ! $teacher || ! $da) {
            return;
        }

        $themes = [
            [
                'student_id' => $student1->id,
                'title' => 'Detection de plagiat multilingue par NLP',
                'description' => 'Evaluation des approches semantiques et traduction inverse.',
                'status' => 'VALIDATED_DA',
                'moderated_by' => $teacher->id,
                'moderation_comment' => 'Theme valide pour demarrage.',
                'moderated_at' => now()->subDays(10),
                'validated_cd_by' => $teacher->id,
                'validated_cd_at' => now()->subDays(11),
                'validated_da_by' => $da->id,
                'validated_da_at' => now()->subDays(10),
                'final_score' => 16.25,
                'final_score_assigned_at' => now()->subDays(10),
            ],
            [
                'student_id' => $student2->id,
                'title' => 'Analyse de similarite dans les memoires',
                'description' => 'Comparaison de techniques lexicales et contextuelles.',
                'status' => 'PENDING',
                'moderated_by' => null,
                'moderation_comment' => null,
                'moderated_at' => null,
                'validated_cd_by' => null,
                'validated_cd_at' => null,
                'validated_da_by' => null,
                'validated_da_at' => null,
                'final_score' => null,
                'final_score_assigned_at' => null,
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
