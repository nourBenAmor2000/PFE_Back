<?php
declare(strict_types=1);

namespace App\Services\Assistant\Handlers;
use App\Services\Assistant\Handlers\ContratHandler;
use App\Services\Assistant\Handlers\LogementHandler;
use App\Services\Assistant\Handlers\VisitHandler;

final class HandlerRegistry
{
    /** @var array<string,string> */
    private array $map = [
        // 🔑 clé SINGULIÈRE
        'visit'  => VisitHandler::class,

        // ✅ alias pluriel (par sécurité)
        'visits' => VisitHandler::class,
        // autres: 'client' => ClientHandler::class, etc.
        'logement'  => LogementHandler::class,   // ✅
        'logements' => LogementHandler::class,   // ✅ alias*
            'client'   => ClientHandler::class,  // <-- ajoute ceci
        'contrat'  => ContratHandler::class,
        'contrats' => ContratHandler::class,
    
    ];

    public function handle(array $intent): array
    {
        $entity = $intent['entity'] ?? 'visit';
        // normaliser en minuscule sans espaces
        $entity = strtolower(trim($entity));

        $handlerClass = $this->map[$entity] ?? null;

        if (!$handlerClass) {
            return [
                'answer' => "Aucun handler pour l'entité '{$entity}'.",
                'data' => [],
                'sources' => [$entity],
                'suggestions' => [],
            ];
        }

        return app($handlerClass)->handle($intent);
    }

    
public function __construct(
    ContratHandler $contrat,
    LogementHandler $logement,
    VisitHandler $visit,
) {
    $this->handlers = [
        $contrat,   // prioritaire pour mots "contrat"
        $logement,
        $visit,
    ];
}
}
