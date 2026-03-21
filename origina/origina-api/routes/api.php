<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Route::get('/ping', function () {
    return response()->json([
        'message' => 'API Origina OK',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::where('email', $credentials['email'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
        return response()->json([
            'message' => 'Identifiants invalides.',
        ], 422);
    }

    return response()->json([
        'message' => 'Connexion reussie.',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department,
        ],
    ]);
});

Route::post('/logout', function () {
    return response()->json([
        'message' => 'Deconnexion reussie.',
    ]);
});

$resolveActor = function (Request $request): ?User {
    $headerValue = $request->header('X-User-Id');
    $userId = $headerValue ?: $request->input('user_id');

    if (! $userId) {
        return null;
    }

    return User::find($userId);
};

$forbidden = fn (string $message = 'Action non autorisee.') => response()->json([
    'message' => $message,
], 403);

$jsonDecodeOrEmpty = function ($value): array {
    if (! $value) {
        return [];
    }

    if (is_array($value)) {
        return $value;
    }

    return json_decode($value, true) ?: [];
};

$buildRiskLevel = function (float $globalSimilarity): string {
    if ($globalSimilarity >= 45) {
        return 'high';
    }

    if ($globalSimilarity >= 20) {
        return 'medium';
    }

    return 'low';
};

Route::group([], function () use ($resolveActor, $forbidden, $jsonDecodeOrEmpty, $buildRiskLevel) {
    Route::get('/me/overview', function (Request $request) use ($resolveActor) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

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
                    's.name as student_name'
                )
                ->where('t.status', 'approved')
                ->orderByDesc('d.submitted_at')
                ->limit(20)
                ->get();
        }

        return response()->json([
            'role' => $actor->role,
            'reports' => $reports,
            'documents' => $documents,
        ]);
    });

    Route::post('/themes/propose', function (Request $request) use ($resolveActor) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if ($actor->role !== 'student') {
            return response()->json(['message' => 'Seul un etudiant peut proposer un theme.'], 403);
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'min:8', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

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
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Theme propose avec succes. En attente de validation.',
            'is_unique' => true,
            'theme' => DB::table('themes')->where('id', $themeId)->first(),
        ], 201);
    });

    Route::get('/themes/pending', function (Request $request) use ($resolveActor, $forbidden) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin'], true)) {
            return $forbidden('Seuls les enseignants peuvent moderer les themes.');
        }

        $themes = DB::table('themes as t')
            ->join('users as s', 's.id', '=', 't.student_id')
            ->where('t.status', 'pending')
            ->select('t.*', 's.name as student_name', 's.email as student_email')
            ->orderBy('t.created_at')
            ->get();

        return response()->json(['themes' => $themes]);
    });

    Route::patch('/themes/{theme}/moderate', function (Request $request, int $theme) use ($resolveActor, $forbidden) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin'], true)) {
            return $forbidden('Seuls les enseignants peuvent valider ou rejeter un theme.');
        }

        $payload = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = DB::table('themes')
            ->where('id', $theme)
            ->update([
                'status' => $payload['decision'],
                'moderated_by' => $actor->id,
                'moderation_comment' => $payload['comment'] ?? null,
                'moderated_at' => now(),
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return response()->json(['message' => 'Theme introuvable.'], 404);
        }

        return response()->json([
            'message' => $payload['decision'] === 'approved'
                ? 'Theme valide.'
                : 'Theme rejete.',
        ]);
    });

    Route::post('/documents/upload', function (Request $request) use ($resolveActor) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if ($actor->role !== 'student') {
            return response()->json(['message' => 'Seul un etudiant peut televerser un memoire.'], 403);
        }

        $payload = $request->validate([
            'theme_id' => ['required', 'integer'],
            'original_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:120'],
            'file_size' => ['nullable', 'integer', 'min:1'],
            'is_final' => ['nullable', 'boolean'],
        ]);

        $theme = DB::table('themes')
            ->where('id', $payload['theme_id'])
            ->where('student_id', $actor->id)
            ->first();

        if (! $theme) {
            return response()->json(['message' => 'Theme introuvable pour cet etudiant.'], 404);
        }

        if ($theme->status !== 'approved') {
            return response()->json([
                'message' => 'Televersement impossible: le theme doit etre valide.',
            ], 422);
        }

        $isFinal = (bool) ($payload['is_final'] ?? true);
        $storagePath = sprintf(
            'documents/student%d/%s-%s',
            $actor->id,
            now()->format('YmdHis'),
            Str::slug(pathinfo($payload['original_name'], PATHINFO_FILENAME)).'.'.pathinfo($payload['original_name'], PATHINFO_EXTENSION)
        );

        $docId = DB::table('documents')->insertGetId([
            'theme_id' => $theme->id,
            'student_id' => $actor->id,
            'original_name' => $payload['original_name'],
            'storage_path' => $storagePath,
            'mime_type' => $payload['mime_type'] ?? 'application/pdf',
            'file_size' => $payload['file_size'] ?? 1500000,
            'checksum' => sha1($payload['original_name'].'|'.now()->timestamp),
            'is_final' => $isFinal,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Memoire televerse avec succes.',
            'document' => DB::table('documents')->where('id', $docId)->first(),
        ], 201);
    });

    Route::post('/documents/{document}/auto-test', function (Request $request, int $document) use ($resolveActor, $buildRiskLevel) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if ($actor->role !== 'student') {
            return response()->json(['message' => 'Seul un etudiant peut lancer un auto-test.'], 403);
        }

        $doc = DB::table('documents')->where('id', $document)->where('student_id', $actor->id)->first();

        if (! $doc) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        $direct = random_int(3, 18);
        $paraphrase = random_int(2, 14);
        $translation = random_int(1, 11);
        $ai = random_int(5, 28);
        $global = round(($direct + $paraphrase + $translation) / 3, 2);

        return response()->json([
            'message' => 'Auto-test termine.',
            'analysis_type' => 'autotest',
            'metrics' => [
                'direct_plagiarism' => $direct,
                'paraphrase' => $paraphrase,
                'translation' => $translation,
                'ai_detection' => $ai,
                'global_similarity' => $global,
                'risk_level' => $buildRiskLevel($global),
            ],
        ]);
    });

    Route::post('/documents/{document}/analyze', function (Request $request, int $document) use ($resolveActor, $forbidden, $buildRiskLevel) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin'], true)) {
            return $forbidden('Seuls les enseignants peuvent lancer une analyse officielle.');
        }

        $doc = DB::table('documents')->where('id', $document)->first();

        if (! $doc) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        $payload = $request->validate([
            'include_direct' => ['nullable', 'boolean'],
            'include_paraphrase' => ['nullable', 'boolean'],
            'include_translation' => ['nullable', 'boolean'],
            'include_ai' => ['nullable', 'boolean'],
        ]);

        $withDirect = $payload['include_direct'] ?? true;
        $withParaphrase = $payload['include_paraphrase'] ?? true;
        $withTranslation = $payload['include_translation'] ?? true;
        $withAi = $payload['include_ai'] ?? true;

        $direct = $withDirect ? random_int(4, 32) : 0;
        $paraphrase = $withParaphrase ? random_int(3, 24) : 0;
        $translation = $withTranslation ? random_int(2, 19) : 0;
        $ai = $withAi ? random_int(4, 35) : null;

        $selectedCount = collect([$withDirect, $withParaphrase, $withTranslation])->filter()->count();
        $global = $selectedCount > 0
            ? round(($direct + $paraphrase + $translation) / $selectedCount, 2)
            : 0.0;

        $riskLevel = $buildRiskLevel($global);

        $reportId = DB::table('similarity_reports')->insertGetId([
            'document_id' => $doc->id,
            'global_similarity' => $global,
            'ai_score' => $ai,
            'risk_level' => $riskLevel,
            'matched_sources' => json_encode([
                ['source' => 'memoire_archive_2024.pdf', 'score' => max(1, round($global * 0.4, 2))],
                ['source' => 'publication_web_science', 'score' => max(1, round($global * 0.3, 2))],
                ['source' => 'soumission_locale', 'score' => max(1, round($global * 0.3, 2))],
            ]),
            'highlighted_segments' => json_encode([
                ['start' => 120, 'end' => 300],
                ['start' => 580, 'end' => 760],
                ['start' => 1120, 'end' => 1300],
            ]),
            'analyzed_at' => now(),
            'generated_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Analyse lancee et rapport genere.',
            'report_id' => $reportId,
            'metrics' => [
                'direct_plagiarism' => $direct,
                'paraphrase' => $paraphrase,
                'translation' => $translation,
                'ai_detection' => $ai,
                'global_similarity' => $global,
                'risk_level' => $riskLevel,
            ],
        ], 201);
    });

    Route::get('/reports', function (Request $request) use ($resolveActor, $forbidden) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin', 'da', 'var'], true)) {
            return $forbidden('Acces reserve aux enseignants et a la commission.');
        }

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
                's.name as student_name',
                'dl.decision as committee_decision',
                'dl.committee',
                'dl.decided_at'
            )
            ->orderByDesc('sr.analyzed_at')
            ->get();

        return response()->json(['reports' => $reports]);
    });

    Route::get('/reports/{report}', function (Request $request, int $report) use ($resolveActor, $forbidden, $jsonDecodeOrEmpty) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin', 'da', 'var'], true)) {
            return $forbidden('Acces reserve aux enseignants et a la commission.');
        }

        $item = DB::table('similarity_reports as sr')
            ->join('documents as d', 'd.id', '=', 'sr.document_id')
            ->join('themes as t', 't.id', '=', 'd.theme_id')
            ->join('users as s', 's.id', '=', 'd.student_id')
            ->leftJoin('users as gen', 'gen.id', '=', 'sr.generated_by')
            ->where('sr.id', $report)
            ->select(
                'sr.*',
                'd.original_name',
                'd.storage_path',
                't.title as theme_title',
                's.name as student_name',
                'gen.name as generated_by_name'
            )
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Rapport introuvable.'], 404);
        }

        $sources = $jsonDecodeOrEmpty($item->matched_sources);
        $segments = $jsonDecodeOrEmpty($item->highlighted_segments);

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
    });

    Route::post('/reports/{report}/deliberate', function (Request $request, int $report) use ($resolveActor, $forbidden) {
        $actor = $resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['da', 'var', 'admin'], true)) {
            return $forbidden('Seule la DA / Commission VAR peut statuer.');
        }

        $payload = $request->validate([
            'decision' => ['required', 'in:final_validation,sanction,rewrite_required'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]);

        $exists = DB::table('similarity_reports')->where('id', $report)->exists();

        if (! $exists) {
            return response()->json(['message' => 'Rapport introuvable.'], 404);
        }

        DB::table('deliberations')->insert([
            'similarity_report_id' => $report,
            'decided_by' => $actor->id,
            'committee' => $actor->role,
            'decision' => $payload['decision'],
            'notes' => $payload['notes'] ?? null,
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Decision enregistree avec succes.',
        ], 201);
    });
});
