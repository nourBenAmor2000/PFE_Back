<?php
declare(strict_types=1);

namespace App\Services\Assistant\Handlers;
use App\Services\Assistant\Handlers\ContratHandler;
use App\Services\Assistant\Handlers\LogementHandler;
use App\Services\Assistant\Handlers\VisitHandler;
use App\Services\Assistant\Handlers\ClientHandler;
use App\Services\Assistant\Handlers\PaymentHandler;
use App\Services\Assistant\Handlers\ReviewHandler;
use App\Services\Assistant\Handlers\AgencyHandler;
use App\Services\Assistant\Handlers\AnalyticsHandler;
use Illuminate\Support\Facades\Log;

final class HandlerRegistry
{
    /** @var array<string,string> */
    private array $map = [
        // 🔑 clé SINGULIÈRE
        'visit'  => VisitHandler::class,
        'visits' => VisitHandler::class,
        'logement'  => LogementHandler::class,
        'logements' => LogementHandler::class,
        'client'   => ClientHandler::class,
        'clients'  => ClientHandler::class,
        'contrat'  => ContratHandler::class,
        'contrats' => ContratHandler::class,
        'payment'  => PaymentHandler::class,
        'payments' => PaymentHandler::class,
        'paiement' => PaymentHandler::class,
        'paiements' => PaymentHandler::class,
        'review'   => ReviewHandler::class,
        'reviews'  => ReviewHandler::class,
        'avis'     => ReviewHandler::class,
        'agency'   => AgencyHandler::class,
        'agencies' => AgencyHandler::class,
        'agence'   => AgencyHandler::class,
        'agences'  => AgencyHandler::class,
        'analytics' => AnalyticsHandler::class,
        'analyse' => AnalyticsHandler::class,
        'statistiques' => AnalyticsHandler::class,
        'stats' => AnalyticsHandler::class,
        'revenus' => AnalyticsHandler::class,
        'performance' => AnalyticsHandler::class,
        'conversion' => AnalyticsHandler::class,
        'marché' => AnalyticsHandler::class,
        'market' => AnalyticsHandler::class,
    ];

    public function handle(array $intent): array
    {
        try {
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

            try {
                $handler = app($handlerClass);
            } catch (\Throwable $e) {
                Log::error('Handler instantiation failed', [
                    'handler' => $handlerClass,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return [
                    'answer' => "Erreur: impossible d'instancier le handler pour '{$entity}': " . $e->getMessage(),
                    'data' => [],
                    'sources' => [$entity],
                    'suggestions' => [],
                ];
            }
            
            if (!method_exists($handler, 'handle')) {
                Log::error('Handler missing handle method', ['handler' => $handlerClass]);
                return [
                    'answer' => "Erreur: le handler pour '{$entity}' n'a pas de méthode handle.",
                    'data' => [],
                    'sources' => [$entity],
                    'suggestions' => [],
                ];
            }

            try {
                return $handler->handle($intent);
            } catch (\Throwable $e) {
                Log::error('Handler execution failed', [
                    'handler' => $handlerClass,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e; // Re-throw to be caught by outer catch
            }
        } catch (\Throwable $e) {
            Log::error('HandlerRegistry.handle error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'intent' => $intent,
            ]);
            
            return [
                'answer' => "Erreur lors du traitement de la requête: " . $e->getMessage(),
                'data' => [],
                'sources' => [],
                'suggestions' => ['Réessayer', 'Vérifier la syntaxe'],
            ];
        }
    }
}
