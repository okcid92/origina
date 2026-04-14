<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiAiDetectionService
{
    public function detectProbability(string $text): array
    {
        $apiKey = (string) config('services.gemini.api_key');
        $model = (string) config('services.gemini.model', 'gemini-1.5-flash');

        if ($apiKey === '') {
            throw new RuntimeException('La cle Gemini est absente. Configurer GEMINI_API_KEY.');
        }

        $prompt = $this->buildPrompt($text);

        $response = Http::timeout(25)
            ->acceptJson()
            ->post(
                sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', $model, $apiKey),
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException('Gemini indisponible: ' . $response->status());
        }

        $rawText = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        if ($rawText === '') {
            throw new RuntimeException('Reponse Gemini vide.');
        }

        $parsed = json_decode($rawText, true);

        if (is_array($parsed) && isset($parsed['ai_probability'])) {
            $value = (float) $parsed['ai_probability'];
            $reason = (string) ($parsed['reason'] ?? 'Aucune explication retournee.');

            return [
                'ai_probability' => $this->normalizePercent($value),
                'reason' => $reason,
                'model' => $model,
            ];
        }

        preg_match('/(\d+(?:\.\d+)?)/', $rawText, $matches);

        if (! isset($matches[1])) {
            throw new RuntimeException('Impossible d extraire un pourcentage depuis la reponse Gemini.');
        }

        return [
            'ai_probability' => $this->normalizePercent((float) $matches[1]),
            'reason' => 'Estimation extraite du texte brut de Gemini.',
            'model' => $model,
        ];
    }

    private function buildPrompt(string $text): string
    {
        return implode("\n", [
            'Tu es un detecteur de contenu genere par IA.',
            'Analyse le texte et estime la probabilite qu il soit genere par une IA.',
            'Reponds UNIQUEMENT en JSON valide avec ce schema:',
            '{"ai_probability": number, "reason": string}',
            'Le champ ai_probability doit etre entre 0 et 100.',
            '',
            'Texte a analyser:',
            $text,
        ]);
    }

    private function normalizePercent(float $value): float
    {
        $bounded = max(0, min(100, $value));

        return round($bounded, 2);
    }
}
