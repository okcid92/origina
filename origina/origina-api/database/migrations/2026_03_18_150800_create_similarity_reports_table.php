<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('similarity_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->decimal('global_similarity', 5, 2)->default(0);
            $table->decimal('ai_score', 5, 2)->nullable();
            $table->string('risk_level', 20)->default('low');
            $table->json('matched_sources')->nullable();
            $table->json('highlighted_segments')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'risk_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('similarity_reports');
    }
};
