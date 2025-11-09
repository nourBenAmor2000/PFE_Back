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
        // 1) Tentative via LLM
        try {
            $system = 'Tu extrais un intent JSON pour des requêtes de gestion immobilière.';
            $user   = "Analyse la requête et retourne uniquement un JSON avec: {type, entity, filters, timeframe, aggregation}.\n\nRequête: \"{$userQuery}\"";
            $raw    = $this->ai->chat($system, $user, $provider);

            if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
                /** @var array<string,mixed> $json */
                $json = json_decode($m[0], true, 512, JSON_THROW_ON_ERROR);
                $json['confidence'] = 0.95;
                return $this->withDefaults($json);
            }
        } catch (Throwable $e) {
            // ignore → fallback
        }

        // 2) Fallback heuristique
        return $this->fallback($userQuery);
        $t = mb_strtolower($text);

// --- CONTRATS ---
if (str_contains($t, 'contrat')) {

    // expirant bientôt -> dans 30 jours
    if (str_contains($t, 'expire') || str_contains($t, 'expirent') || str_contains($t, 'bientôt')) {
        return ['entity' => 'contrat', 'name' => 'expiring_next_30d'];
    }

    // signés ce mois-ci
    if ((str_contains($t, 'ce mois') || str_contains($t, 'mois-ci')) && (str_contains($t, 'sign'))) {
        return ['entity' => 'contrat', 'name' => 'signed_this_month'];
    }

    // en attente de signature
    if (str_contains($t, 'attente') && str_contains($t, 'signature')) {
        return ['entity' => 'contrat', 'name' => 'pending_signature'];
    }

    // fallback contrat
    return ['entity' => 'contrat', 'name' => 'signed_this_month'];
}

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
        // app/Services/Assistant/QueryInterpreter.php
 $t = mb_strtolower($query);
// --- CONTRATS en priorité ---
if (str_contains($t, 'contrat')) {

    if ( (str_contains($t, 'expire') || str_contains($t, 'expirent') || str_contains($t, 'bientôt') || str_contains($t, 'bientot')) ) {
        return ['entity' => 'contrat', 'name' => 'expiring_next_30d'];
    }

    if ( (str_contains($t, 'ce mois') || str_contains($t, 'mois-ci') || str_contains($t, 'mois ci'))
         && (str_contains($t, 'sign') || str_contains($t, 'signés') || str_contains($t, 'signes')) ) {
        return ['entity' => 'contrat', 'name' => 'signed_this_month'];
    }

    if (str_contains($t, 'attente') && str_contains($t, 'signature')) {
        return ['entity' => 'contrat', 'name' => 'pending_signature'];
    }

    // fallback
    return ['entity' => 'contrat', 'name' => 'signed_this_month'];
}

        $l = mb_strtolower($q);

        // entity
        $entity = 'logement';
        if (str_contains($l, 'client')) {
            $entity = 'client';
        } elseif (str_contains($l, 'visite') || str_contains($l, 'visit')) {
            $entity = 'visit';
        } elseif (str_contains($l, 'factur') || str_contains($l, 'revenu') || str_contains($l, 'paiement')) {
            $entity = 'billing';
        }

        // type
        $type = 'list';
        if (str_contains($l, 'combien') || str_contains($l, 'how many') || str_contains($l, 'count')) {
            $type = 'count';
        } elseif (str_contains($l, 'trouve') || str_contains($l, 'find') || str_contains($l, 'search')) {
            $type = 'find';
        } elseif (str_contains($l, 'analyse') || str_contains($l, 'résumé') || str_contains($l, 'summary')) {
            $type = 'analyze';
        }

        // filters / timeframe
        $filters = [];
        if (str_contains($l, 'actif')) {
            $filters['status'] = 'active';
        }
        $timeframe = null;
        if (str_contains($l, "aujourd'hui") || str_contains($l, 'aujourdhui')) {
            $timeframe = 'today';
        } elseif (str_contains($l, 'semaine')) {
            $timeframe = 'week';
        } elseif (str_contains($l, 'mois')) {
            $timeframe = 'month';
        }

        return $this->withDefaults(compact('type', 'entity', 'filters', 'timeframe'));
    }
    
}
