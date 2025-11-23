<?php
declare(strict_types=1);

namespace App\Services\Assistant;
use App\Services\Assistant\AIAssistant;

use Throwable;

/**
 * Transforme une question utilisateur en intent normalisé :
 * [type, entity, filters, timeframe, aggregation, confidence]
 */
final class QueryInterpreter
{
    public function __construct(private AIAssistant $ai)
    {
    }

    /**
     * @return array{
     *   type: string,
     *   entity: string,
     *   filters: array<string,mixed>,
     *   timeframe: string|null,
     *   aggregation: string|null,
     *   confidence: float
     * }
     */
    public function interpret(string $userQuery, string $provider = 'openrouter'): array
    {
        // 1) Tentative via LLM avec prompt amélioré
        try {
            $system = <<<PROMPT
Tu es un expert en extraction d'intent pour un système de gestion immobilière.
Analyse la requête utilisateur et extrais un intent JSON structuré.

Entités possibles: visit, logement, client, contrat, payment, review, agency, analytics, agent, category
Types possibles: list, count, find, analyze, compare, aggregate
Timeframes: today, week, month, year, all
Filtres: status, price_min, price_max, city, agency_id, category_id, rating_min, rating_max, free

Retourne UNIQUEMENT un JSON valide avec: {type, entity, filters, timeframe, aggregation, action}
PROMPT;
            
            $user = "Analyse cette requête et retourne uniquement un JSON:\n\nRequête: \"{$userQuery}\"\n\nJSON:";
            $raw = $this->ai->chat($system, $user, $provider);

            if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
                /** @var array<string,mixed> $json */
                $json = json_decode($m[0], true, 512, JSON_THROW_ON_ERROR);
                $json['confidence'] = 0.95;
                return $this->withDefaults($json);
            }
        } catch (Throwable $e) {
            // ignore → fallback
        }

        // 2) Fallback heuristique amélioré
        return $this->fallback($userQuery);
    }

    /**
     * Applique des valeurs par défaut et normalise la structure.
     *
     * @param array<string,mixed> $i
     * @return array{
     *   type: string,
     *   entity: string,
     *   filters: array<string,mixed>,
     *   timeframe: string|null,
     *   aggregation: string|null,
     *   confidence: float
     * }
     */
    private function withDefaults(array $i): array
    {
        return [
            'type'        => isset($i['type']) ? (string) $i['type'] : 'list',
            'entity'      => isset($i['entity']) ? (string) $i['entity'] : 'logement',
            'filters'     => isset($i['filters']) && is_array($i['filters']) ? $i['filters'] : [],
            'timeframe'   => isset($i['timeframe']) ? (string) $i['timeframe'] : null,
            'aggregation' => isset($i['aggregation']) ? (string) $i['aggregation'] : null,
            'action'      => isset($i['action']) ? (string) $i['action'] : null,
            'confidence'  => isset($i['confidence']) ? (float) $i['confidence'] : 0.7,
        ];
    }

    /**
     * Heuristiques simples si le LLM échoue.
     *
     * @return array{
     *   type: string,
     *   entity: string,
     *   filters: array<string,mixed>,
     *   timeframe: string|null,
     *   aggregation: string|null,
     *   confidence: float
     * }
     */
    private function fallback(string $query, ?\Illuminate\Contracts\Auth\Authenticatable $user = null): array
    {
        $t = mb_strtolower(trim($query));
        $filters = [];
        $timeframe = null;
        $type = 'list';
        $action = null;
        
        // --- Détection de timeframe améliorée ---
        if (str_contains($t, "aujourd'hui") || str_contains($t, 'aujourdhui') || str_contains($t, 'today')) {
            $timeframe = 'today';
        } elseif (str_contains($t, 'semaine') || str_contains($t, 'week') || str_contains($t, '7 jours')) {
            $timeframe = 'week';
        } elseif (str_contains($t, 'mois') || str_contains($t, 'month') || str_contains($t, 'mensuel')) {
            $timeframe = 'month';
        } elseif (str_contains($t, 'année') || str_contains($t, 'year') || str_contains($t, 'annuel')) {
            $timeframe = 'year';
        } elseif (str_contains($t, 'trimestre') || str_contains($t, 'quarter')) {
            $timeframe = 'quarter';
        }
        
        // --- Détection de type améliorée ---
        if (str_contains($t, 'combien') || str_contains($t, 'how many') || str_contains($t, 'count') || 
            str_contains($t, 'nombre') || str_contains($t, 'total')) {
            $type = 'count';
        } elseif (str_contains($t, 'trouve') || str_contains($t, 'find') || str_contains($t, 'search') ||
                   str_contains($t, 'cherche') || str_contains($t, 'liste') || str_contains($t, 'list')) {
            $type = 'find';
        } elseif (str_contains($t, 'analyse') || str_contains($t, 'résumé') || str_contains($t, 'summary') ||
                   str_contains($t, 'statistique') || str_contains($t, 'stat') || str_contains($t, 'rapport')) {
            $type = 'analyze';
        } elseif (str_contains($t, 'compare') || str_contains($t, 'comparer') || str_contains($t, 'comparaison')) {
            $type = 'compare';
        }
        
        // --- CONTRATS en priorité ---
        if (str_contains($t, 'contrat') || str_contains($t, 'contract')) {
            if (str_contains($t, 'expire') || str_contains($t, 'expirent') || str_contains($t, 'bientôt') || 
                str_contains($t, 'bientot') || str_contains($t, 'expiring')) {
                return $this->withDefaults([
                    'entity' => 'contrat',
                    'action' => 'expiring_next_30d',
                    'type' => $type,
                    'timeframe' => $timeframe,
                    'filters' => $filters
                ]);
            }
            if ((str_contains($t, 'ce mois') || str_contains($t, 'mois-ci') || str_contains($t, 'mois ci') || str_contains($t, 'this month'))
                && (str_contains($t, 'sign') || str_contains($t, 'signés') || str_contains($t, 'signes') || str_contains($t, 'signed'))) {
                return $this->withDefaults([
                    'entity' => 'contrat',
                    'action' => 'signed_this_month',
                    'type' => $type,
                    'timeframe' => 'month',
                    'filters' => $filters
                ]);
            }
            if (str_contains($t, 'attente') && (str_contains($t, 'signature') || str_contains($t, 'sign'))) {
                return $this->withDefaults([
                    'entity' => 'contrat',
                    'action' => 'pending_signature',
                    'type' => $type,
                    'timeframe' => $timeframe,
                    'filters' => $filters
                ]);
            }
            return $this->withDefaults([
                'entity' => 'contrat',
                'type' => $type,
                'timeframe' => $timeframe,
                'filters' => $filters
            ]);
        }

        // --- Entity detection améliorée avec priorité ---
        $entity = 'logement';
        if ((str_contains($t, 'agence') || str_contains($t, 'agency')) && 
    (str_contains($t, 'performance') || str_contains($t, 'performant'))) {
    $entity = 'agency';
    $type = 'analyze'; // Force analyze type for performance queries
}
        // Analytics/Stats en priorité
        if (str_contains($t, 'statistique') || str_contains($t, 'analyse') || str_contains($t, 'analytics') || 
            str_contains($t, 'revenu') || str_contains($t, 'revenue') || str_contains($t, 'performance') || 
            str_contains($t, 'conversion') || str_contains($t, 'marché') || str_contains($t, 'market') || 
            str_contains($t, 'overview') || str_contains($t, 'global') || str_contains($t, 'dashboard') ||
            str_contains($t, 'rapport') || str_contains($t, 'report')) {
            $entity = 'analytics';
        } elseif (str_contains($t, 'agent') && !str_contains($t, 'agence')) {
            $entity = 'agent';
        } elseif (str_contains($t, 'client') || str_contains($t, 'customer') || str_contains($t, 'utilisateur')) {
            $entity = 'client';
        } elseif (str_contains($t, 'visite') || str_contains($t, 'visit') || str_contains($t, 'rendez-vous') || str_contains($t, 'rdv')) {
            $entity = 'visit';
        } elseif (str_contains($t, 'factur') || str_contains($t, 'paiement') || str_contains($t, 'payment') ||
                   str_contains($t, 'transaction') || str_contains($t, 'revenu') || str_contains($t, 'revenue')) {
            $entity = 'payment';
        } elseif (str_contains($t, 'avis') || str_contains($t, 'review') || str_contains($t, 'commentaire') ||
                   str_contains($t, 'note') || str_contains($t, 'rating') || str_contains($t, 'évaluation')) {
            $entity = 'review';
        } elseif (str_contains($t, 'agence') || str_contains($t, 'agency') || str_contains($t, 'bureau')) {
            $entity = 'agency';
        } elseif (str_contains($t, 'logement') || str_contains($t, 'property') || str_contains($t, 'bien') ||
                   str_contains($t, 'appartement') || str_contains($t, 'maison') || str_contains($t, 'villa')) {
            $entity = 'logement';
        } elseif (str_contains($t, 'categorie') || str_contains($t, 'category') || str_contains($t, 'type')) {
            $entity = 'category';
        }
        
        // --- Filtres améliorés ---
        // Prix
        if (preg_match('/prix[^\d]*(\d+)/i', $t, $matches)) {
            $filters['price_min'] = (int)$matches[1];
        }
        if (preg_match('/moins de (\d+)/i', $t, $matches)) {
            $filters['price_max'] = (int)$matches[1];
        }
        if (preg_match('/plus de (\d+)/i', $t, $matches)) {
            $filters['price_min'] = (int)$matches[1];
        }
        
        // Statut
        if (str_contains($t, 'disponible') || str_contains($t, 'available') || str_contains($t, 'libre')) {
            $filters['free'] = true;
        }
        if (str_contains($t, 'occupé') || str_contains($t, 'occupied') || str_contains($t, 'loué') || str_contains($t, 'rented')) {
            $filters['free'] = false;
        }
        if (str_contains($t, 'actif') || str_contains($t, 'active')) {
            $filters['status'] = 'active';
        }
        
        // Ville/Location
        if (preg_match('/\b(tunis|sfax|sousse|bizerte|gabes|gafsa|kairouan|monastir|ben arous|ariana)\b/i', $t, $matches)) {
            $filters['city'] = ucfirst(strtolower($matches[1]));
        }
        
        // Note/Rating
        if (preg_match('/(\d+)[\s\*]*étoile/i', $t, $matches)) {
            $filters['rating_min'] = (int)$matches[1];
        }
        
        // Agence
        if (preg_match('/agence[^\w]*(\w+)/i', $t, $matches)) {
            $filters['agency_name'] = $matches[1];
        }

        return $this->withDefaults(compact('type', 'entity', 'filters', 'timeframe', 'action'));
    }
    
}
