<?php


namespace App\Http\Controllers;


use App\Http\Requests\AssistantQueryRequest;
use App\Services\Assistant\QueryInterpreter;
use App\Services\Assistant\AIAssistant;
use App\Services\Assistant\Handlers\HandlerRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; 

class AssistantController extends Controller
{
    public function __construct(
        private QueryInterpreter $interpreter,
        private HandlerRegistry $handlers,
        private AIAssistant $ai,
    ) {}


// public function query(AssistantQueryRequest $request): JsonResponse
// {
// $userQuery = $request->string('query');
// $provider = $request->string('provider', 'openrouter');
// $userId = $request->string('userId', 'anonymous');


// // 1) Intent
// $intent = $this->interpreter->interpret($userQuery, $provider);


// // 2) Data (Eloquent)
// $result = $this->handlers->handle($intent);


// // 3) Résumé IA (server-side)
// $answer = $this->ai->summarize($userQuery, $intent, $result, $provider);


// return response()->json([
// 'answer' => $answer['text'] ?? 'Voici les informations trouvées.',
// 'timestamp' => now()->toISOString(),
// 'confidence' => $intent['confidence'] ?? 0.9,
// 'sources' => $result['sources'] ?? [],
// 'suggestions' => $answer['suggestions'] ?? [],
// ]);
// }
    public function query(Request $req): JsonResponse
    {
        try {
            $query = (string) $req->input('query', '');
            $provider = (string) $req->input('provider', 'openrouter');
            $userId = (string) $req->input('userId', 'anonymous');

            if (empty($query)) {
                return response()->json([
                    'answer' => 'Veuillez poser une question.',
                    'timestamp' => now()->toISOString(),
                    'confidence' => 0,
                    'sources' => [],
                    'suggestions' => [],
                ], 400);
            }

            // 1) Interpréter l'intent
            try {
                $intent = $this->interpreter->interpret($query, $provider);
                
                // Validate intent structure
                if (!is_array($intent) || !isset($intent['entity'])) {
                    Log::error('assistant.interpret.invalid', [
                        'intent' => $intent,
                        'query' => $query,
                    ]);
                    throw new \RuntimeException('Intent invalide retourné par l\'interpréteur');
                }
            } catch (\Throwable $e) {
                Log::error('assistant.interpret.error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'query' => $query,
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new \RuntimeException('Erreur lors de l\'interprétation de la requête: ' . $e->getMessage(), 0, $e);
            }
            
            // Store original query for context in handlers
            $intent['original_query'] = $query;

            // 🔎 log visible dans storage/logs/laravel.log
            Log::info('assistant.intent', $intent);

            // 2) Récupérer les données via les handlers
            try {
                $result = $this->handlers->handle($intent);
                
                // Ensure result has required keys
                if (!isset($result['answer'])) {
                    $result['answer'] = 'Données récupérées avec succès.';
                }
                if (!isset($result['data'])) {
                    $result['data'] = [];
                }
                if (!isset($result['sources'])) {
                    $result['sources'] = [];
                }
                if (!isset($result['suggestions'])) {
                    $result['suggestions'] = [];
                }
            } catch (\Throwable $e) {
                Log::error('assistant.handler.error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'intent' => $intent,
                ]);
                throw new \RuntimeException('Erreur lors de la récupération des données: ' . $e->getMessage(), 0, $e);
            }

            // 3) Générer une réponse intelligente avec l'IA
            try {
                $answer = $this->ai->summarize($query, $intent, $result, $provider);
            } catch (\Throwable $e) {
                Log::error('assistant.summarize.error', [
                    'message' => $e->getMessage(),
                    'query' => $query,
                ]);
                // Use result answer as fallback
                $answer = [
                    'text' => $result['answer'] ?? 'Voici les informations trouvées.',
                    'suggestions' => $result['suggestions'] ?? [],
                ];
            }

            return response()->json([
                'answer' => $answer['text'] ?? ($result['answer'] ?? 'Voici les informations trouvées.'),
                'timestamp' => now()->toISOString(),
                'confidence' => $intent['confidence'] ?? 0.9,
                'sources' => $result['sources'] ?? [],
                'suggestions' => $answer['suggestions'] ?? $result['suggestions'] ?? [],
                'analytics' => $result['analytics'] ?? null,
                'data' => $result['data'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('assistant.error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'query' => $query ?? 'N/A',
            ]);

            // In development, return more detailed error information
            $errorMessage = config('app.debug') 
                ? 'Erreur: ' . $e->getMessage() . ' (Fichier: ' . basename($e->getFile()) . ':' . $e->getLine() . ')'
                : 'Désolé, une erreur est survenue lors du traitement de votre requête. Veuillez réessayer.';

            return response()->json([
                'answer' => $errorMessage,
                'timestamp' => now()->toISOString(),
                'confidence' => 0,
                'sources' => [],
                'suggestions' => ['Réessayer', 'Consulter l\'aide'],
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Health check endpoint to test assistant services
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'services' => [],
        ];

        // Test QueryInterpreter
        try {
            $testIntent = $this->interpreter->interpret('test', 'openrouter');
            $health['services']['interpreter'] = 'ok';
        } catch (\Throwable $e) {
            $health['services']['interpreter'] = 'error: ' . $e->getMessage();
            $health['status'] = 'error';
        }

        // Test HandlerRegistry
        try {
            $testResult = $this->handlers->handle(['entity' => 'visit', 'type' => 'list']);
            $health['services']['handlers'] = 'ok';
        } catch (\Throwable $e) {
            $health['services']['handlers'] = 'error: ' . $e->getMessage();
            $health['status'] = 'error';
        }

        // Test AIAssistant
        try {
            $apiKey = config('services.openrouter.key');
            $health['services']['ai'] = empty($apiKey) ? 'warning: API key not configured' : 'ok';
        } catch (\Throwable $e) {
            $health['services']['ai'] = 'error: ' . $e->getMessage();
            $health['status'] = 'error';
        }

        return response()->json($health, $health['status'] === 'ok' ? 200 : 500);
    }
}