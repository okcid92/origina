<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Workflow\ActivityWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityWorkflowController extends Controller
{
    public function __construct(private readonly ActivityWorkflowService $workflow)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        return $this->workflow->overview($actor);
    }

    public function proposeTheme(Request $request): JsonResponse
    {
        $actor = $this->resolveActor($request);

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

        return $this->workflow->proposeTheme($actor, $payload);
    }

    public function pendingThemes(Request $request): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin', 'da'], true)) {
            return response()->json(['message' => 'Seuls les enseignants ou le DA peuvent moderer les themes.'], 403);
        }

        return $this->workflow->pendingThemes($actor);
    }

    public function moderateTheme(Request $request, int $theme): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        $payload = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'final_score' => ['nullable', 'numeric', 'between:0,20'],
        ]);

        return $this->workflow->moderateTheme($actor, $theme, $payload);
    }

    public function validateThemeCd(Request $request, int $theme): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin'], true)) {
            return response()->json(['message' => 'Seuls les enseignants peuvent faire la validation locale.'], 403);
        }

        $payload = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->workflow->validateThemeLocal($actor, $theme, $payload);
    }

    public function validateThemeDa(Request $request, int $theme): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['da', 'admin'], true)) {
            return response()->json(['message' => 'Seul le DA peut faire la validation academique.'], 403);
        }

        $payload = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'final_score' => ['required_if:decision,approved', 'nullable', 'numeric', 'between:0,20'],
        ]);

        return $this->workflow->validateThemeAcademic($actor, $theme, $payload);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $actor = $this->resolveActor($request);

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

        return $this->workflow->uploadDocument($actor, $payload);
    }

    public function autoTestDocument(Request $request, int $document): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if ($actor->role !== 'student') {
            return response()->json(['message' => 'Seul un etudiant peut lancer un auto-test.'], 403);
        }

        return $this->workflow->autoTestDocument($actor, $document);
    }

    public function analyzeDocument(Request $request, int $document): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin'], true)) {
            return response()->json(['message' => 'Seuls les enseignants peuvent lancer une analyse officielle.'], 403);
        }

        $payload = $request->validate([
            'include_direct' => ['nullable', 'boolean'],
            'include_paraphrase' => ['nullable', 'boolean'],
            'include_translation' => ['nullable', 'boolean'],
            'include_ai' => ['nullable', 'boolean'],
        ]);

        return $this->workflow->analyzeDocument($actor, $document, $payload);
    }

    public function reportsIndex(Request $request): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin', 'da', 'var'], true)) {
            return response()->json(['message' => 'Acces reserve aux enseignants et a la commission.'], 403);
        }

        return $this->workflow->reportsIndex($actor);
    }

    public function showReport(Request $request, int $report): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['teacher', 'admin', 'da', 'var'], true)) {
            return response()->json(['message' => 'Acces reserve aux enseignants et a la commission.'], 403);
        }

        return $this->workflow->showReport($actor, $report);
    }

    public function deliberateReport(Request $request, int $report): JsonResponse
    {
        $actor = $this->resolveActor($request);

        if (! $actor) {
            return response()->json(['message' => 'Utilisateur non authentifie. Fournir X-User-Id.'], 401);
        }

        if (! in_array($actor->role, ['da', 'var', 'admin'], true)) {
            return response()->json(['message' => 'Seule la DA / Commission VAR peut statuer.'], 403);
        }

        $payload = $request->validate([
            'decision' => ['required', 'in:final_validation,sanction,rewrite_required'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]);

        return $this->workflow->deliberateReport($actor, $report, $payload);
    }

    private function resolveActor(Request $request): ?User
    {
        $headerValue = $request->header('X-User-Id');
        $userId = $headerValue ?: $request->input('user_id');

        if (! $userId) {
            return null;
        }

        return User::find($userId);
    }
}