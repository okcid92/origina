<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_validation_assigns_score_and_unlocks_document_submission(): void
    {
        $student = $this->createUser('student', 'student@example.com');
        $teacher = $this->createUser('teacher', 'teacher@example.com');
        $da = $this->createUser('da', 'da@example.com');

        $themeResponse = $this->postJson('/api/themes/propose', [
            'title' => 'Workflow de validation de theme multi-etapes',
            'description' => 'Validation locale puis academique avec note finale.',
        ], $this->headersFor($student));

        $themeResponse->assertCreated();
        $themeId = $themeResponse->json('theme.id');

        $this->patchJson('/api/themes/' . $themeId . '/validate-cd', [
            'decision' => 'approved',
            'comment' => 'Theme coherent au niveau departemental.',
        ], $this->headersFor($teacher))->assertOk();

        $this->patchJson('/api/themes/' . $themeId . '/validate-da', [
            'decision' => 'approved',
            'comment' => 'Validation academique finalisee.',
            'final_score' => 16.50,
        ], $this->headersFor($da))->assertOk();

        $this->assertDatabaseHas('themes', [
            'id' => $themeId,
            'status' => 'VALIDATED_DA',
        ]);

        $this->assertDatabaseHas('themes', [
            'id' => $themeId,
            'final_score' => 16.50,
        ]);

        $uploadResponse = $this->postJson('/api/documents/upload', [
            'theme_id' => $themeId,
            'original_name' => 'memoire-final.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048000,
            'is_final' => true,
        ], $this->headersFor($student));

        $uploadResponse->assertCreated();
        $this->assertDatabaseHas('documents', [
            'theme_id' => $themeId,
            'student_id' => $student->id,
            'is_final' => 1,
        ]);
    }

    public function test_document_upload_is_blocked_before_da_validation(): void
    {
        $student = $this->createUser('student', 'student-two@example.com');
        $teacher = $this->createUser('teacher', 'teacher-two@example.com');

        $themeId = $this->postJson('/api/themes/propose', [
            'title' => 'Sujet de test avant validation DA',
            'description' => 'Doit rester bloque avant la note finale.',
        ], $this->headersFor($student))->json('theme.id');

        $this->patchJson('/api/themes/' . $themeId . '/validate-cd', [
            'decision' => 'approved',
        ], $this->headersFor($teacher))->assertOk();

        $this->postJson('/api/documents/upload', [
            'theme_id' => $themeId,
            'original_name' => 'memoire-bloque.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024000,
            'is_final' => true,
        ], $this->headersFor($student))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Televersement impossible: le theme doit etre valide par le DA.');
    }

    public function test_rejected_theme_requires_new_title(): void
    {
        $student = $this->createUser('student', 'student-three@example.com');
        $teacher = $this->createUser('teacher', 'teacher-three@example.com');

        $this->postJson('/api/themes/propose', [
            'title' => 'Theme refuse et resoumission interdite',
            'description' => 'Premiere soumission.',
        ], $this->headersFor($student))->assertCreated();

        $themeId = DB::table('themes')->where('student_id', $student->id)->value('id');

        $this->patchJson('/api/themes/' . $themeId . '/validate-cd', [
            'decision' => 'rejected',
            'comment' => 'Titre insuffisamment specifique.',
        ], $this->headersFor($teacher))->assertOk();

        $this->postJson('/api/themes/propose', [
            'title' => 'Theme refuse et resoumission interdite',
            'description' => 'Nouvelle soumission avec le meme titre.',
        ], $this->headersFor($student))
            ->assertStatus(422)
            ->assertJsonPath('is_unique', false);
    }

    public function test_analysis_and_deliberation_are_recorded(): void
    {
        $student = $this->createUser('student', 'student-four@example.com');
        $teacher = $this->createUser('teacher', 'teacher-four@example.com');
        $da = $this->createUser('da', 'da-four@example.com');

        $themeId = $this->postJson('/api/themes/propose', [
            'title' => 'Memoire final pour analyse officielle',
            'description' => 'Flux complet jusqu a la deliberation.',
        ], $this->headersFor($student))->json('theme.id');

        $this->patchJson('/api/themes/' . $themeId . '/validate-cd', [
            'decision' => 'approved',
        ], $this->headersFor($teacher))->assertOk();

        $this->patchJson('/api/themes/' . $themeId . '/validate-da', [
            'decision' => 'approved',
            'final_score' => 14.75,
        ], $this->headersFor($da))->assertOk();

        $documentId = $this->postJson('/api/documents/upload', [
            'theme_id' => $themeId,
            'original_name' => 'memoire-final-officiel.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 3096000,
            'is_final' => true,
        ], $this->headersFor($student))->json('document.id');

        $this->postJson('/api/documents/' . $documentId . '/auto-test', [], $this->headersFor($student))
            ->assertOk()
            ->assertJsonPath('analysis_type', 'autotest');

        $reportId = $this->postJson('/api/documents/' . $documentId . '/analyze', [
            'include_ai' => true,
        ], $this->headersFor($teacher))
            ->assertCreated()
            ->json('report_id');

        $this->postJson('/api/reports/' . $reportId . '/deliberate', [
            'decision' => 'rewrite_required',
            'notes' => 'Rafraichir les references citees dans le rapport.',
        ], $this->headersFor($da))->assertCreated();

        $this->assertDatabaseHas('similarity_reports', [
            'id' => $reportId,
            'document_id' => $documentId,
        ]);

        $this->assertDatabaseHas('deliberations', [
            'similarity_report_id' => $reportId,
            'decision' => 'rewrite_required',
            'committee' => 'da',
        ]);
    }

    private function createUser(string $role, string $email): User
    {
        return User::query()->create([
            'name' => ucfirst($role) . ' Test',
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'department' => 'Informatique',
            'email_verified_at' => now(),
        ]);
    }

    private function headersFor(User $user): array
    {
        return ['X-User-Id' => (string) $user->id];
    }
}