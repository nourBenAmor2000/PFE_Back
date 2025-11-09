<?php
declare(strict_types=1);

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;

final class AIAssistant
{
    public function chat(string $system, string $user, string $provider = 'openrouter'): string
    {
        $res = Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openrouter.key'),
            'Content-Type'  => 'application/json',
            'HTTP-Referer'  => config('app.url'),
            'X-Title'       => config('app.name', 'Assistant'),
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => config('services.openrouter.model', 'google/gemini-2.0-flash-lite-001'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.2,
        ])->throw()->json();

        return $res['choices'][0]['message']['content'] ?? '';
    }

    /**
     * @param array<string,mixed> $intent
     * @param array<string,mixed> $result
     * @return array{text:string,suggestions:array<int,string>}
     */
    public function summarize(string $userQuery, array $intent, array $result, string $provider = 'openrouter'): array
    {
        $system = 'Tu es un assistant métier. Rédige des résumés clairs et propose 2-3 actions suivantes.';
        $user   = "Requête: {$userQuery}\nIntent: ".json_encode($intent)."\nData: ".json_encode($result['data'] ?? []);

        try {
            $text = $this->chat($system, $user, $provider);
            return ['text' => $text, 'suggestions' => $result['suggestions'] ?? ['Exporter en CSV', 'Voir le détail']];
        } catch (\Throwable $e) {
            $data  = $result['data'] ?? [];
            $count = is_array($data) ? (is_countable($data) ? count($data) : 1) : 0;
            return ['text' => "Résultats ({$count}) disponibles.", 'suggestions' => []];
        }
    }
}
