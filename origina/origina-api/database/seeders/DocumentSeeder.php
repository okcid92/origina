<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $approvedTheme = DB::table('themes')->where('status', 'VALIDATED_DA')->first();

        if (! $approvedTheme) {
            return;
        }

        DB::table('documents')->updateOrInsert(
            [
                'theme_id' => $approvedTheme->id,
                'storage_path' => 'documents/student1/memoire-v1.pdf',
            ],
            [
                'student_id' => $approvedTheme->student_id,
                'original_name' => 'memoire-v1.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 2841200,
                'checksum' => sha1('memoire-v1.pdf'),
                'is_final' => true,
                'submitted_at' => now()->subDays(6),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
