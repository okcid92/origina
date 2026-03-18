<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliberationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $report = DB::table('similarity_reports')->orderByDesc('id')->first();
        $da = User::where('email', 'da@origina.local')->first();

        if (! $report || ! $da) {
            return;
        }

        DB::table('deliberations')->updateOrInsert(
            [
                'similarity_report_id' => $report->id,
                'committee' => 'da',
            ],
            [
                'decided_by' => $da->id,
                'decision' => 'revision_required',
                'notes' => 'Demander references complementaires pour les passages signales.',
                'decided_at' => now()->subDays(4),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
