<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table): void {
            $table->foreignId('validated_cd_by')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_cd_at')->nullable()->after('validated_cd_by');
            $table->foreignId('validated_da_by')->nullable()->after('validated_cd_at')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_da_at')->nullable()->after('validated_da_by');
            $table->decimal('final_score', 5, 2)->nullable()->after('validated_da_at');
            $table->timestamp('final_score_assigned_at')->nullable()->after('final_score');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('validated_cd_by');
            $table->dropColumn('validated_cd_at');
            $table->dropConstrainedForeignId('validated_da_by');
            $table->dropColumn('validated_da_at');
            $table->dropColumn('final_score');
            $table->dropColumn('final_score_assigned_at');
        });
    }
};