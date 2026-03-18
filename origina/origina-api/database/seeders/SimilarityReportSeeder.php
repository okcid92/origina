<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SimilarityReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $document = DB::table('documents')->orderByDesc('id')->first();
        $teacher = User::where('email', 'teacher@origina.local')->first();

        if (! $document || ! $teacher) {
            return;
        }

        DB::table('similarity_reports')->updateOrInsert(
            [
                'document_id' => $document->id,
            ],
            [
                'global_similarity' => 18.40,
                'ai_score' => 22.15,
                'risk_level' => 'medium',
                'matched_sources' => json_encode([
                    ['source' => 'memoire-2024.pdf', 'score' => 11.2],
                    ['source' => 'article-web-1', 'score' => 7.2],
                ], JSON_UNESCAPED_UNICODE),
                'highlighted_segments' => json_encode([
                    ['start' => 420, 'end' => 580],
                    ['start' => 1100, 'end' => 1310],
                ], JSON_UNESCAPED_UNICODE),
                'analyzed_at' => now()->subDays(5),
                'generated_by' => $teacher->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
