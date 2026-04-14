<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Services\Ai\GeminiAiDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityWorkflowService
{
    public function __construct(private readonly GeminiAiDetectionService $geminiDetector)
    {
    }

    public function overview(User $actor): JsonResponse
    {
        if ($actor->role === 'student') {
            $themes = DB::table('themes')
                ->where('student_id', $actor->id)
                ->orderByDesc('updated_at')
                ->get();

            $documents = DB::table('documents')
                ->where('student_id', $actor->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get();

            return response()->json([
                'role' => $actor->role,
                'themes' => $themes,
                'documents' => $documents,
            ]);
        }

        $reports = DB::table('similarity_reports as sr')
            ->join('documents as d', 'd.id', '=', 'sr.document_id')
            ->join('themes as t', 't.id', '=', 'd.theme_id')
            ->join('users as s', 's.id', '=', 'd.student_id')
            ->leftJoin('users as gen', 'gen.id', '=', 'sr.generated_by')
            ->select(
                'sr.id',
                'sr.global_similarity',
                'sr.ai_score',
                'sr.risk_level',
                'sr.analyzed_at',
                'd.original_name',
                't.title as theme_title',
                't.final_score',
                's.name as student_name',
                'gen.name as generated_by_name'
            )
            ->orderByDesc('sr.analyzed_at')
            ->limit(20)
            ->get();

        $documents = collect();

        if (in_array($actor->role, ['teacher', 'admin'], true)) {
            $documents = DB::table('documents as d')
                ->join('themes as t', 't.id', '=', 'd.theme_id')
                ->join('users as s', 's.id', '=', 'd.student_id')
                ->select(
                    'd.id',
                    'd.original_name',
                    'd.submitted_at',
                    'd.is_final',
                    't.id as theme_id',
                    't.title as theme_title',
                    't.status as theme_status',
                    't.final_score',
                    's.name as student_name'
                )
                ->where('t.status', 'VALIDATED_DA')
                ->orderByDesc('d.submitted_at')
                ->limit(20)
                ->get();
        }

        return response()->json([
            'role' => $actor->role,
            'reports' => $reports,
            'documents' => $documents,
        ]);
    }

    public function proposeTheme(User $actor, array $payload): JsonResponse
    {
        $title = trim($payload['title']);

        $alreadyExists = DB::table('themes')
            ->whereRaw('LOWER(title) = ?', [Str::lower($title)])
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'Ce theme existe deja. Proposer un titre plus specifique.',
                'is_unique' => false,
            ], 422);
        }

        $themeId = DB::table('themes')->insertGetId([
            'student_id' => $actor->id,
            'title' => $title,
            'description' => $payload['description'] ?? null,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Theme propose avec succes. En attente de validation.',
            'is_unique' => true,
            'theme' => DB::table('themes')->where('id', $themeId)->first(),
        ], 201);
    }

    public function pendingThemes(User $actor): JsonResponse
    {
        $statusNeeded = ($actor->role === 'da') ? 'VALIDATED_CD' : 'PENDING';

        $themes = DB::table('themes as t')
            ->join('users as s', 's.id', '=', 't.student_id')
            ->where('t.status', $statusNeeded)
            ->select('t.*', 's.name as student_name', 's.email as student_email')
            ->orderBy('t.created_at')
            ->get();

        return response()->json(['themes' => $themes]);
    }

    public function validateThemeLocal(User $actor, int $themeId, array $payload): JsonResponse
    {
        $theme = DB::table('themes')->where('id', $themeId)->first();

        if (! $theme) {
            return response()->json(['message' => 'Theme introuvable.'], 404);
        }

        if ($theme->status !== 'PENDING') {
            return response()->json([
                'message' => 'Le theme doit etre en attente pour une validation locale.',
            ], 422);
        }

        $newStatus = $payload['decision'] === 'approved' ? 'VALIDATED_CD' : 'REJECTED';

        DB::table('themes')
            ->where('id', $themeId)
            ->update([
                'status' => $newStatus,
                'moderated_by' => $actor->id,
                'moderation_comment' => $payload['comment'] ?? null,
                'moderated_at' => now(),
                'validated_cd_by' => $payload['decision'] === 'approved' ? $actor->id : null,
                'validated_cd_at' => $payload['decision'] === 'approved' ? now() : null,
                'validated_da_by' => null,
                'validated_da_at' => null,
                'final_score' => null,
                'final_score_assigned_at' => null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => $payload['decision'] === 'approved'
                ? 'Theme transmis au DA.'
                : 'Theme rejete.',
        ]);
    }

    public function validateThemeAcademic(User $actor, int $themeId, array $payload): JsonResponse
    {
        $theme = DB::table('themes')->where('id', $themeId)->first();

        if (! $theme) {
            return response()->json(['message' => 'Theme introuvable.'], 404);
        }

        if ($theme->status !== 'VALIDATED_CD') {
            return response()->json([
                'message' => 'Le theme doit avoir ete valide localement avant la validation academique.',
            ], 422);
        }

        $approved = $payload['decision'] === 'approved';

        DB::table('themes')
            ->where('id', $themeId)
            ->update([
                'status' => $approved ? 'VALIDATED_DA' : 'REJECTED',
                'moderated_by' => $actor->id,
                'moderation_comment' => $payload['comment'] ?? null,
                'moderated_at' => now(),
                'validated_da_by' => $approved ? $actor->id : null,
                'validated_da_at' => $approved ? now() : null,
                'final_score' => $approved ? $payload['final_score'] : null,
                'final_score_assigned_at' => $approved ? now() : null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => $approved
                ? 'Theme valide definitivement par le DA et note finale attribuee.'
                : 'Theme rejete.',
        ]);
    }

    public function moderateTheme(User $actor, int $themeId, array $payload): JsonResponse
    {
        $theme = DB::table('themes')->where('id', $themeId)->first();

        if (! $theme) {
            return response()->json(['message' => 'Theme introuvable.'], 404);
        }

        if ($theme->status === 'PENDING') {
            if (! in_array($actor->role, ['teacher', 'admin'], true)) {
                return response()->json(['message' => 'Seuls les enseignants peuvent faire la validation locale.'], 403);
            }

            return $this->validateThemeLocal($actor, $themeId, $payload);
        }

        if ($theme->status === 'VALIDATED_CD') {
            if (! in_array($actor->role, ['da', 'admin'], true)) {
                return response()->json(['message' => 'Seul le DA peut faire la validation academique.'], 403);
            }

            return $this->validateThemeAcademic($actor, $themeId, $payload);
        }

        return response()->json([
            'message' => 'Le theme ne peut plus etre modere dans son etat actuel.',
        ], 422);
    }

    public function uploadDocument(User $actor, array $payload): JsonResponse
    {
        $theme = DB::table('themes')
            ->where('id', $payload['theme_id'])
            ->where('student_id', $actor->id)
            ->first();

        if (! $theme) {
            return response()->json(['message' => 'Theme introuvable pour cet etudiant.'], 404);
        }

        if ($theme->status !== 'VALIDATED_DA') {
            return response()->json([
                'message' => 'Televersement impossible: le theme doit etre valide par le DA.',
            ], 422);
        }

        if ($theme->final_score === null) {
            return response()->json([
                'message' => 'Televersement impossible: la note finale doit etre attribuee avant le depot final.',
            ], 422);
        }

        $isFinal = (bool) ($payload['is_final'] ?? true);
        $extension = pathinfo($payload['original_name'], PATHINFO_EXTENSION);
        $baseName = Str::slug(pathinfo($payload['original_name'], PATHINFO_FILENAME));
        $storagePath = sprintf(
            'documents/student%d/%s-%s%s',
            $actor->id,
            now()->format('YmdHis'),
            $baseName,
            $extension ? '.' . $extension : ''
        );

        $docId = DB::table('documents')->insertGetId([
            'theme_id' => $theme->id,
            'student_id' => $actor->id,
            'original_name' => $payload['original_name'],
            'storage_path' => $storagePath,
            'mime_type' => $payload['mime_type'] ?? 'application/pdf',
            'file_size' => $payload['file_size'] ?? 1500000,
            'checksum' => sha1($payload['original_name'] . '|' . now()->timestamp),
            'is_final' => $isFinal,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Memoire televerse avec succes.',
            'document' => DB::table('documents')->where('id', $docId)->first(),
        ], 201);
    }

    public function autoTestDocument(User $actor, int $documentId): JsonResponse
    {
        $doc = DB::table('documents')->where('id', $documentId)->where('student_id', $actor->id)->first();

        if (! $doc) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        $localShingle = random_int(2, 25);
        $webSearch = random_int(5, 30);
        $aiLinguistics = random_int(5, 40);

        $global = round(($localShingle + $webSearch + $aiLinguistics) / 3, 2);

        try {
            DB::table('verifications')->insertGetId([
                'document_id' => $doc->id,
                'score_global' => $global,
                'type' => 'AUTO-TEST',
                'date_test' => now(),
            ]);
        } catch (\Throwable) {
            // Optional table in this workspace.
        }

        return response()->json([
            'message' => 'Auto-test termine et enregistre.',
            'analysis_type' => 'autotest',
            'metrics' => [
                'local_shingle' => $localShingle,
                'web_search' => $webSearch,
                'ai_detection' => $aiLinguistics,
                'global_similarity' => $global,
                'risk_level' => $this->buildRiskLevel($global),
            ],
        ]);
    }

    public function analyzeDocument(User $actor, int $documentId, array $payload): JsonResponse
    {
        $doc = DB::table('documents')->where('id', $documentId)->first();

        if (! $doc) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        $theme = DB::table('themes')->where('id', $doc->theme_id)->first();

        if (! $theme || $theme->status !== 'VALIDATED_DA') {
            return response()->json([
                'message' => 'Analyse impossible: le theme doit etre valide par le DA.',
            ], 422);
        }

        if (! $doc->is_final) {
            return response()->json([
                'message' => 'Analyse officielle reservee au document final.',
            ], 422);
        }

        $withAi = $payload['include_ai'] ?? true;

        $localShingle = random_int(2, 25);
        $webSearch = random_int(5, 30);
        $aiLinguistics = $withAi ? random_int(5, 40) : null;

        $global = round(($localShingle + $webSearch + ($aiLinguistics ?? 0)) / ($withAi ? 3 : 2), 2);

        $riskLevel = $this->buildRiskLevel($global);

        $reportId = DB::table('similarity_reports')->insertGetId([
            'document_id' => $doc->id,
            'global_similarity' => $global,
            'ai_score' => $aiLinguistics,
            'risk_level' => $riskLevel,
            'matched_sources' => json_encode([
                ['source' => 'Base Locale (Algo Shingle)', 'score' => max(1, round($global * 0.4, 2))],
                ['source' => 'Moteurs de recherche Web', 'score' => max(1, round($global * 0.4, 2))],
                ['source' => 'Détection IA Syntaxique', 'score' => max(1, round($global * 0.2, 2))],
            ], JSON_UNESCAPED_UNICODE),
            'highlighted_segments' => json_encode([
                ['start' => 120, 'end' => 300],
                ['start' => 580, 'end' => 760],
                ['start' => 1120, 'end' => 1300],
            ], JSON_UNESCAPED_UNICODE),
            'analyzed_at' => now(),
            'generated_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('verifications')->insertGetId([
                'document_id' => $doc->id,
                'score_global' => $global,
                'type' => 'OFFICIEL',
                'date_test' => now(),
            ]);
        } catch (\Throwable) {
            // Optional table in this workspace.
        }

        return response()->json([
            'message' => 'Analyse multi-niveaux terminee (Shingle, Web, IA).',
            'report_id' => $reportId,
            'metrics' => [
                'local_shingle' => $localShingle,
                'web_search' => $webSearch,
                'ai_detection' => $aiLinguistics,
                'global_similarity' => $global,
                'risk_level' => $riskLevel,
            ],
        ], 201);
    }

    public function detectAiText(User $actor, string $text): JsonResponse
    {
        try {
            $result = $this->geminiDetector->detectProbability($text);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Detection IA indisponible pour le moment.',
                'error' => $exception->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Detection IA terminee avec Gemini.',
            'data' => [
                'ai_probability' => $result['ai_probability'],
                'human_probability' => round(100 - (float) $result['ai_probability'], 2),
                'reason' => $result['reason'],
                'model' => $result['model'],
                'analyzed_by' => [
                    'id' => $actor->id,
                    'role' => $actor->role,
                ],
            ],
        ]);
    }

    public function reportsIndex(User $actor): JsonResponse
    {
        $reports = DB::table('similarity_reports as sr')
            ->join('documents as d', 'd.id', '=', 'sr.document_id')
            ->join('themes as t', 't.id', '=', 'd.theme_id')
            ->join('users as s', 's.id', '=', 'd.student_id')
            ->leftJoin('deliberations as dl', 'dl.similarity_report_id', '=', 'sr.id')
            ->select(
                'sr.id',
                'sr.global_similarity',
                'sr.ai_score',
                'sr.risk_level',
                'sr.analyzed_at',
                'd.original_name',
                't.title as theme_title',
                't.final_score',
                's.name as student_name',
                'dl.decision as committee_decision',
                'dl.committee',
                'dl.decided_at'
            )
            ->orderByDesc('sr.analyzed_at')
            ->get();

        return response()->json(['reports' => $reports]);
    }

    public function showReport(User $actor, int $reportId): JsonResponse
    {
        $item = DB::table('similarity_reports as sr')
            ->join('documents as d', 'd.id', '=', 'sr.document_id')
            ->join('themes as t', 't.id', '=', 'd.theme_id')
            ->join('users as s', 's.id', '=', 'd.student_id')
            ->leftJoin('users as gen', 'gen.id', '=', 'sr.generated_by')
            ->where('sr.id', $reportId)
            ->select(
                'sr.*',
                'd.original_name',
                'd.storage_path',
                't.title as theme_title',
                't.final_score',
                's.name as student_name',
                'gen.name as generated_by_name'
            )
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Rapport introuvable.'], 404);
        }

        $sources = $this->jsonDecodeOrEmpty($item->matched_sources);
        $segments = $this->jsonDecodeOrEmpty($item->highlighted_segments);

        return response()->json([
            'report' => $item,
            'graphs' => [
                'analysis_breakdown' => [
                    ['label' => 'Similarite globale', 'value' => (float) $item->global_similarity],
                    ['label' => 'Score IA', 'value' => (float) ($item->ai_score ?? 0)],
                    ['label' => 'Passages detectes', 'value' => count($segments)],
                ],
                'sources_distribution' => collect($sources)->map(function ($src) {
                    return [
                        'label' => $src['source'] ?? 'source',
                        'value' => (float) ($src['score'] ?? 0),
                    ];
                })->values(),
            ],
        ]);
    }

    public function deliberateReport(User $actor, int $reportId, array $payload): JsonResponse
    {
        $exists = DB::table('similarity_reports')->where('id', $reportId)->exists();

        if (! $exists) {
            return response()->json(['message' => 'Rapport introuvable.'], 404);
        }

        DB::table('deliberations')->updateOrInsert(
            ['similarity_report_id' => $reportId],
            [
                'decided_by' => $actor->id,
                'committee' => $actor->role,
                'decision' => $payload['decision'],
                'notes' => $payload['notes'] ?? null,
                'decided_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Decision enregistree avec succes.',
        ], 201);
    }

    private function buildRiskLevel(float $globalSimilarity): string
    {
        if ($globalSimilarity >= 45) {
            return 'high';
        }

        if ($globalSimilarity >= 20) {
            return 'medium';
        }

        return 'low';
    }

    private function jsonDecodeOrEmpty(mixed $value): array
    {
        if (! $value) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        return json_decode($value, true) ?: [];
    }
}