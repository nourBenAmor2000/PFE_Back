<?php


namespace App\Http\Controllers;


use App\Http\Requests\AssistantQueryRequest;
use App\Services\Assistant\QueryInterpreter;
use App\Services\Assistant\AIAssistant;
use App\Services\Assistant\Handlers\HandlerRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request; 

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
public function query(Request $req)
{
    $query = (string) $req->input('query', '');
    $userId = (string) $req->input('userId', 'anonymous');

    $intent = app(\App\Services\Assistant\QueryInterpreter::class)->interpret($query);

    // 🔎 log visible dans storage/logs/laravel.log
    \Log::info('assistant.intent', $intent);

    $result = app(\App\Services\Assistant\Handlers\HandlerRegistry::class)->handle($intent);

    return response()->json($result);
}

}