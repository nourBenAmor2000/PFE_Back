<?php
declare(strict_types=1);

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AIAssistant
{
    public function chat(string $system, string $user, string $provider = 'openrouter'): string
    {
        try {
            $apiKey = config('services.openrouter.key');
            if (empty($apiKey)) {
                Log::warning('OpenRouter API key not configured');
                return '';
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
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
            ]);

            if (!$response->successful()) {
                Log::error('OpenRouter API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return '';
            }

            $res = $response->json();
            return $res['choices'][0]['message']['content'] ?? '';
        } catch (\Throwable $e) {
            Log::error('AIAssistant.chat error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return '';
        }
    }

    /**
     * @param array<string,mixed> $intent
     * @param array<string,mixed> $result
     * @return array{text:string,suggestions:array<int,string>}
     */
    public function summarize(string $userQuery, array $intent, array $result, string $provider = 'openrouter'): array
    {
        $entity = $intent['entity'] ?? 'unknown';
        $data = $result['data'] ?? [];
        $count = is_array($data) ? (is_countable($data) ? count($data) : 1) : 0;
        
        // Enhanced system prompt for professional, business-focused responses with proactive suggestions
        $system = <<<PROMPT
Tu es un assistant IA professionnel expert en gestion immobilière pour la plateforme E-DAR. 
Tu es le conseiller stratégique de l'administrateur global.

TON RÔLE:
- Fournir des analyses stratégiques approfondies et actionnables
- Identifier les opportunités d'optimisation et de croissance
- Présenter des insights business avec des métriques précises
- Proposer des recommandations concrètes et priorisées
- SUGGÉRER PROACTIVEMENT des questions pertinentes pour approfondir l'analyse

STYLE PROFESSIONNEL:
1. Langage: Professionnel, clair, et orienté business
2. Structure: Toujours structuré avec sections claires
3. Métriques: Toujours inclure des chiffres précis et contextuels
4. Insights: Identifier les tendances, patterns, et anomalies
5. Actions: Proposer 3-5 actions concrètes et priorisées
6. Questions: Suggérer 4-6 questions pertinentes pour approfondir
7. Format: Utiliser des emojis stratégiques (📊 📈 💰 🎯 ✅ ❓) pour la lisibilité

FORMAT DE RÉPONSE OBLIGATOIRE:
📊 RÉSUMÉ EXÉCUTIF
[1-2 phrases synthétiques avec les chiffres clés]

📈 ANALYSE DÉTAILLÉE
[Analyse approfondie avec métriques, comparaisons, et contexte]

💡 INSIGHTS STRATÉGIQUES
[Identification des tendances, opportunités, et points d'attention]

🎯 RECOMMANDATIONS PRIORITAIRES
[3-5 actions concrètes, numérotées et priorisées]

❓ QUESTIONS SUGGÉRÉES POUR APPROFONDIR
[4-6 questions pertinentes basées sur les données analysées]

IMPORTANT:
- Toujours formater les nombres (ex: 1 250 000 TND, 15,3%)
- Utiliser un langage professionnel mais accessible
- Être précis, factuel, et orienté résultats
- Proposer des actions mesurables et réalisables
- Suggérer des questions qui aident l'admin à découvrir des insights cachés
PROMPT;

        // Build detailed context for the AI
        $dataSummary = $this->buildDataSummary($data, $entity);
        $context = $this->buildContext($intent, $result, $dataSummary);
        
        // Build analytics summary for better context
        $analyticsSummary = $this->buildAnalyticsSummary($result['analytics'] ?? []);
        
        // Extract values for heredoc
        $intentType = $intent['type'] ?? 'list';
        $filters = $this->formatFilters($intent['filters'] ?? []);
        $timeframe = $intent['timeframe'] ?? 'toutes périodes';
        $sources = $this->formatSources($result['sources'] ?? []);
        
        $user = <<<USER
Requête utilisateur: "{$userQuery}"

Contexte de l'intent:
- Entité: {$entity}
- Type: {$intentType}
- Filtres: {$filters}
- Période: {$timeframe}

Données récupérées:
{$dataSummary}

Statistiques et Analytics:
- Nombre total: {$count}
- Sources: {$sources}
{$analyticsSummary}

Analyse demandée:
{$context}

Génère une réponse PROFESSIONNELLE, STRATÉGIQUE et ACTIONNABLE en suivant EXACTEMENT le format demandé:
- Résumé exécutif avec chiffres clés (1-2 phrases)
- Analyse détaillée avec métriques précises, comparaisons, et contexte business
- Insights stratégiques (tendances identifiées, opportunités, risques, anomalies)
- Recommandations priorisées (3-5 actions concrètes, numérotées, avec priorités)
- QUESTIONS SUGGÉRÉES (6-8 questions pertinentes pour approfondir l'analyse)

Les questions suggérées doivent:
- Être basées sur les données analysées et les analytics
- Aider à découvrir des insights cachés et des opportunités
- Être actionnables et pertinentes pour l'admin global
- Couvrir différents aspects: performance, revenus, optimisation, risques, croissance
- Inclure des questions sur les tendances et prévisions

Sois PRÉCIS, FACTUEL, et ORIENTÉ RÉSULTATS. Utilise un langage professionnel adapté à un administrateur global.
Analyse en profondeur les données et propose des insights business actionnables.
USER;

        try {
            $text = $this->chat($system, $user, $provider);
            
            // If AI response is empty, use fallback
            if (empty($text) || trim($text) === '') {
                Log::warning('AI returned empty response, using fallback');
                $text = $this->generateFallbackResponse($entity, $count, $data, $result);
            }
            
            // Generate smart suggestions based on entity, data, and analytics
            $analytics = $result['analytics'] ?? [];
            $suggestions = $this->generateSmartSuggestions($entity, array_merge($intent, ['analytics' => $analytics]), $count, $data);
            
            // Merge with handler suggestions, prioritizing our smart suggestions
            $handlerSuggestions = $result['suggestions'] ?? [];
            $allSuggestions = array_merge($suggestions, $handlerSuggestions);
            $allSuggestions = array_unique($allSuggestions);
            
            return [
                'text' => $text, 
                'suggestions' => array_slice($allSuggestions, 0, 8) // Max 8 suggestions pour plus de choix
            ];
        } catch (\Throwable $e) {
            Log::error('AI summarization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $userQuery,
            ]);
            
            // Fallback with basic analysis and smart suggestions
            $analytics = $result['analytics'] ?? [];
            $suggestions = $this->generateSmartSuggestions($entity, array_merge($intent, ['analytics' => $analytics]), $count, $data);
            $handlerSuggestions = $result['suggestions'] ?? [];
            $allSuggestions = array_merge($suggestions, $handlerSuggestions);
            $allSuggestions = array_unique($allSuggestions);
            
            return [
                'text' => $this->generateFallbackResponse($entity, $count, $data, $result),
                'suggestions' => array_slice($allSuggestions, 0, 8)
            ];
        }
    }
    
    /**
     * Build a summary of data for AI analysis
     */
    private function buildDataSummary($data, string $entity): string
    {
        if (empty($data) || !is_array($data)) {
            return "Aucune donnée disponible.";
        }
        
        $summary = "📊 DONNÉES DISPONIBLES:\n";
        $count = is_countable($data) ? count($data) : 0;
        $summary .= "• Nombre d'éléments: {$count}\n\n";
        
        // Entity-specific summaries with comprehensive details
        switch ($entity) {
            case 'visit':
            case 'visits':
                $summary .= $this->summarizeVisits($data);
                break;
            case 'logement':
            case 'logements':
                $summary .= $this->summarizeLogements($data);
                break;
            case 'client':
            case 'clients':
                $summary .= $this->summarizeClients($data);
                break;
            case 'payment':
            case 'payments':
            case 'paiement':
            case 'paiements':
                $summary .= $this->summarizePayments($data);
                break;
            case 'contrat':
            case 'contrats':
                $summary .= $this->summarizeContracts($data);
                break;
            case 'review':
            case 'reviews':
            case 'avis':
                $summary .= $this->summarizeReviews($data);
                break;
            case 'agency':
            case 'agencies':
            case 'agence':
            case 'agences':
                $summary .= $this->summarizeAgencies($data);
                break;
            default:
                $summary .= "• Données brutes disponibles\n";
        }
        
        return $summary;
    }
    
    private function summarizeVisits($data): string
    {
        $summary = "Type: Visites immobilières\n";
        if (is_countable($data) && count($data) > 0) {
            $dates = array_filter(array_column($data, 'visit_date'));
            if (!empty($dates)) {
                $summary .= "• Période: " . min($dates) . " à " . max($dates) . "\n";
            }
            
            // Extract client and logement info
            $uniqueClients = [];
            $uniqueLogements = [];
            foreach ($data as $visit) {
                if (isset($visit['client']) && isset($visit['client']['name'])) {
                    $uniqueClients[$visit['client']['name']] = true;
                }
                if (isset($visit['logement']) && isset($visit['logement']['title'])) {
                    $uniqueLogements[$visit['logement']['title']] = true;
                }
            }
            if (!empty($uniqueClients)) {
                $summary .= "• Clients uniques: " . count($uniqueClients) . "\n";
            }
            if (!empty($uniqueLogements)) {
                $summary .= "• Logements visités: " . count($uniqueLogements) . "\n";
            }
        }
        return $summary;
    }
    
    private function summarizeLogements($data): string
    {
        $summary = "Type: Logements/Propriétés\n";
        if (is_countable($data) && count($data) > 0) {
            $prices = array_filter(array_column($data, 'price'));
            if (!empty($prices)) {
                $avgPrice = array_sum($prices) / count($prices);
                $minPrice = min($prices);
                $maxPrice = max($prices);
                $summary .= "• Prix moyen: " . number_format($avgPrice, 0, ',', ' ') . " TND\n";
                $summary .= "• Fourchette: " . number_format($minPrice, 0, ',', ' ') . " - " . number_format($maxPrice, 0, ',', ' ') . " TND\n";
            }
            $free = array_filter($data, fn($l) => ($l['free'] ?? true) === true);
            $summary .= "• Disponibles: " . count($free) . " / " . count($data) . "\n";
            
            // Extract agency and category info
            $agencies = [];
            $categories = [];
            foreach ($data as $logement) {
                if (isset($logement['agency']['name'])) {
                    $agencies[$logement['agency']['name']] = true;
                }
                if (isset($logement['category']['name'])) {
                    $categories[$logement['category']['name']] = true;
                }
            }
            if (!empty($agencies)) {
                $summary .= "• Agences: " . count($agencies) . "\n";
            }
            if (!empty($categories)) {
                $summary .= "• Catégories: " . count($categories) . "\n";
            }
            
            // Stats
            $totalVisits = 0;
            $totalContracts = 0;
            $totalReviews = 0;
            foreach ($data as $logement) {
                if (isset($logement['stats'])) {
                    $totalVisits += $logement['stats']['visits_count'] ?? 0;
                    $totalContracts += $logement['stats']['contracts_count'] ?? 0;
                    $totalReviews += $logement['stats']['reviews_count'] ?? 0;
                }
            }
            if ($totalVisits > 0 || $totalContracts > 0 || $totalReviews > 0) {
                $summary .= "• Statistiques globales: {$totalVisits} visites, {$totalContracts} contrats, {$totalReviews} avis\n";
            }
        }
        return $summary;
    }
    
    private function summarizeClients($data): string
    {
        $summary = "- Type: Clients\n";
        if (is_countable($data) && count($data) > 0) {
            $summary .= "- Total clients: " . count($data) . "\n";
        }
        return $summary;
    }
    
    private function summarizePayments($data): string
    {
        $summary = "- Type: Paiements\n";
        if (is_countable($data) && count($data) > 0) {
            $amounts = array_filter(array_column($data, 'montant'));
            if (!empty($amounts)) {
                $total = array_sum($amounts);
                $avg = $total / count($amounts);
                $summary .= "- Total: " . number_format($total, 2, ',', ' ') . " TND\n";
                $summary .= "- Moyenne: " . number_format($avg, 2, ',', ' ') . " TND\n";
            }
            $statuses = array_count_values(array_column($data, 'statut'));
            if (!empty($statuses)) {
                $summary .= "- Par statut: " . json_encode($statuses) . "\n";
            }
        }
        return $summary;
    }
    
    private function summarizeContracts($data): string
    {
        $summary = "- Type: Contrats\n";
        if (is_countable($data) && count($data) > 0) {
            $amounts = array_filter(array_column($data, 'amount'));
            if (!empty($amounts)) {
                $total = array_sum($amounts);
                $summary .= "- Valeur totale: " . number_format($total, 2, ',', ' ') . " TND\n";
            }
        }
        return $summary;
    }
    
    private function summarizeReviews($data): string
    {
        $summary = "Type: Avis/Reviews\n";
        if (is_countable($data) && count($data) > 0) {
            $ratings = array_filter(array_column($data, 'rating'));
            if (!empty($ratings)) {
                $avgRating = array_sum($ratings) / count($ratings);
                $summary .= "• Note moyenne: " . number_format($avgRating, 1) . "/5\n";
                $summary .= "• Nombre total d'avis: " . count($data) . "\n";
            }
        }
        return $summary;
    }
    
    private function summarizeAgencies($data): string
    {
        $summary = "Type: Agences\n";
        if (is_countable($data) && count($data) > 0) {
            $summary .= "• Nombre d'agences: " . count($data) . "\n";
            
            // Extract cities
            $cities = [];
            $totalAgents = 0;
            $totalLogements = 0;
            foreach ($data as $agency) {
                if (isset($agency['city'])) {
                    $cities[$agency['city']] = true;
                }
                if (isset($agency['agents_count'])) {
                    $totalAgents += $agency['agents_count'];
                }
                if (isset($agency['logements_count'])) {
                    $totalLogements += $agency['logements_count'];
                }
            }
            if (!empty($cities)) {
                $summary .= "• Villes: " . count($cities) . "\n";
            }
            if ($totalAgents > 0) {
                $summary .= "• Total agents: {$totalAgents}\n";
            }
            if ($totalLogements > 0) {
                $summary .= "• Total logements: {$totalLogements}\n";
            }
        }
        return $summary;
    }
    
    private function buildContext(array $intent, array $result, string $dataSummary): string
    {
        $context = "Analyse demandée pour: " . ($intent['entity'] ?? 'unknown') . "\n";
        
        if (isset($intent['timeframe'])) {
            $context .= "Période: " . $intent['timeframe'] . "\n";
        }
        
        if (!empty($result['answer'])) {
            $context .= "Résultat préliminaire: " . $result['answer'] . "\n";
        }
        
        return $context;
    }
    
    private function formatFilters(array $filters): string
    {
        if (empty($filters)) {
            return "Aucun filtre";
        }
        return json_encode($filters, JSON_UNESCAPED_UNICODE);
    }
    
    private function formatSources(array $sources): string
    {
        if (empty($sources)) {
            return "Aucune source";
        }
        return implode(', ', $sources);
    }
    
    private function generateSmartSuggestions(string $entity, array $intent, int $count, $data): array
    {
        $suggestions = [];
        $analytics = $intent['analytics'] ?? [];
        $timeframe = $intent['timeframe'] ?? null;
        
        // Questions contextuelles basées sur l'entité, les données et le contexte
        switch ($entity) {
            case 'visit':
            case 'visits':
                $timeframeText = $timeframe ? "ce {$timeframe}" : "ce mois";
                $suggestions = [
                    "Quels sont les logements les plus visités {$timeframeText}?",
                    'Quel est le taux de conversion visites-contrats?',
                    'Quels clients ont le plus de visites?',
                    'Quelle est la répartition des visites par agence?',
                    'Quels sont les créneaux horaires les plus fréquentés?',
                    'Quels logements n\'ont reçu aucune visite?',
                    'Quelle est l\'évolution du nombre de visites sur 3 mois?',
                    'Quels agents ont organisé le plus de visites?',
                    'Quelle est la durée moyenne entre visite et signature de contrat?',
                    'Quels sont les jours de la semaine les plus propices aux visites?',
                ];
                break;
            case 'logement':
            case 'logements':
                $suggestions = [
                    'Quels sont les logements les plus chers?',
                    'Quels logements sont disponibles depuis le plus longtemps?',
                    'Quelle est la performance des logements par catégorie?',
                    'Quels logements ont le meilleur taux de conversion?',
                    'Quelle est la répartition des logements par ville?',
                    'Quels logements ont les meilleures notes?',
                    'Quels logements ont le plus de visites?',
                    'Quels logements génèrent le plus de revenus?',
                    'Quelle est la durée moyenne de disponibilité avant location?',
                    'Quels logements nécessitent une attention particulière?',
                    'Quelle est la performance des logements par agence?',
                    'Quels sont les logements les plus rentables?',
                ];
                break;
            case 'client':
            case 'clients':
                $suggestions = [
                    'Quels clients ont le plus de contrats?',
                    'Quels sont les clients les plus fidèles?',
                    'Quels clients n\'ont pas de contrat?',
                    'Quelle est la valeur totale des contrats par client?',
                    'Quels clients ont le meilleur taux de conversion?',
                    'Quels clients n\'ont fait aucune visite?',
                    'Quels clients ont le plus de visites sans contrat?',
                    'Quelle est la valeur moyenne des contrats par client?',
                    'Quels clients ont donné le plus d\'avis positifs?',
                    'Quels sont les clients à risque de départ?',
                    'Quelle est la durée moyenne de relation avec les clients?',
                    'Quels clients génèrent le plus de revenus?',
                ];
                break;
            case 'payment':
            case 'payments':
            case 'paiement':
            case 'paiements':
                $timeframeText = $timeframe ? "ce {$timeframe}" : "ce mois";
                $suggestions = [
                    "Quels sont les revenus totaux {$timeframeText}?",
                    'Quelle est la répartition des paiements par méthode?',
                    'Quels paiements sont en attente?',
                    'Quelle est l\'évolution des revenus sur 6 mois?',
                    'Quels sont les contrats avec paiements en retard?',
                    'Quelle est la moyenne des paiements par agence?',
                    'Quelle est la prévision des revenus pour le prochain mois?',
                    'Quels sont les paiements en retard de plus de 30 jours?',
                    'Quelle est la répartition des revenus par ville?',
                    'Quels sont les facteurs qui impactent les revenus?',
                    'Quelle est la tendance des revenus (croissance/décroissance)?',
                    'Quels contrats génèrent le plus de revenus récurrents?',
                ];
                break;
            case 'contrat':
            case 'contrats':
                $suggestions = [
                    'Quels contrats expirent dans les 30 prochains jours?',
                    'Quels contrats ont été signés ce mois?',
                    'Quels contrats sont en attente de signature?',
                    'Quelle est la valeur totale des contrats actifs?',
                    'Quels sont les contrats les plus rentables?',
                    'Quelle est la durée moyenne des contrats?',
                    'Quels contrats nécessitent un renouvellement urgent?',
                    'Quelle est la valeur moyenne des contrats signés?',
                    'Quels sont les contrats à risque de non-renouvellement?',
                    'Quelle est la répartition des contrats par type?',
                    'Quels clients ont le plus de contrats actifs?',
                    'Quelle est la performance des contrats par agence?',
                ];
                break;
            case 'review':
            case 'reviews':
            case 'avis':
                $suggestions = [
                    'Quels logements ont les meilleures notes?',
                    'Quelle est la note moyenne par agence?',
                    'Quels clients ont donné le plus d\'avis?',
                    'Quels logements n\'ont reçu aucun avis?',
                    'Quels sont les avis les plus récents?',
                    'Quelle est l\'évolution des notes dans le temps?',
                    'Quels logements ont reçu des avis négatifs?',
                    'Quelle est la corrélation entre notes et taux de location?',
                    'Quels sont les thèmes récurrents dans les avis?',
                    'Quels logements nécessitent une amélioration basée sur les avis?',
                    'Quelle est la satisfaction client globale?',
                    'Quels agents gèrent les logements les mieux notés?',
                ];
                break;
            case 'agency':
            case 'agencies':
            case 'agence':
            case 'agences':
                $suggestions = [
                    'Quelle agence a le plus de logements?',
                    'Quelle agence a les meilleures performances?',
                    'Quelle est la répartition des agences par ville?',
                    'Quelle agence a le plus d\'agents?',
                    'Quelle agence génère le plus de revenus?',
                    'Quelle est la performance des agences ce mois?',
                    'Quelle agence a le meilleur taux d\'occupation?',
                    'Quelle agence a le meilleur taux de conversion?',
                    'Quelle agence nécessite un support supplémentaire?',
                    'Quelle est la rentabilité par agence?',
                    'Quels sont les facteurs de succès des meilleures agences?',
                    'Quelle agence a le plus de clients satisfaits?',
                ];
                break;
            case 'analytics':
            case 'analyse':
            case 'statistiques':
                $suggestions = [
                    'Quelles sont les statistiques globales du système?',
                    'Quelle est la performance globale ce mois?',
                    'Quel est le taux de conversion global?',
                    'Quels sont les revenus totaux cette année?',
                    'Quelle est l\'évolution des indicateurs clés?',
                    'Quels sont les points d\'attention prioritaires?',
                    'Quelle est la prévision des revenus pour les 3 prochains mois?',
                    'Quels sont les KPIs à surveiller en priorité?',
                    'Quelle est la santé globale du business?',
                    'Quels sont les risques identifiés?',
                    'Quelles sont les opportunités de croissance?',
                    'Quel est le ROI par agence?',
                ];
                break;
            default:
                $suggestions = [
                    'Quelles sont les statistiques globales?',
                    'Quels sont les éléments les plus récents?',
                    'Quels sont les éléments nécessitant une attention?',
                    'Quelle est la performance globale?',
                ];
        }
        
        // Ajouter des questions intelligentes basées sur les analytics disponibles
        if (!empty($analytics)) {
            // Questions basées sur le taux d'occupation
            if (isset($analytics['occupancy_rate'])) {
                $rate = $analytics['occupancy_rate'];
                if ($rate < 50) {
                    array_unshift($suggestions, "Comment améliorer le taux d'occupation actuel de " . number_format($rate, 1) . "%?");
                    array_unshift($suggestions, 'Quels logements sont disponibles depuis trop longtemps?');
                } elseif ($rate > 90) {
                    array_unshift($suggestions, 'Comment maintenir ce taux d\'occupation élevé?');
                }
            }
            
            // Questions basées sur le taux de conversion
            if (isset($analytics['conversion_rate'])) {
                $rate = $analytics['conversion_rate'];
                if ($rate < 20) {
                    array_unshift($suggestions, "Comment améliorer le taux de conversion de " . number_format($rate, 1) . "%?");
                    array_unshift($suggestions, 'Quelles sont les raisons des visites non converties?');
                }
            }
            
            // Questions basées sur les revenus
            if (isset($analytics['total_revenue']) || isset($analytics['total_amount'])) {
                $revenue = $analytics['total_revenue'] ?? $analytics['total_amount'] ?? 0;
                if ($revenue > 0) {
                    array_unshift($suggestions, 'Quelle est la prévision des revenus pour le prochain mois?');
                    array_unshift($suggestions, 'Quels sont les facteurs qui impactent les revenus?');
                }
            }
            
            // Questions basées sur le nombre de résultats
            if ($count > 0) {
                if ($count > 100) {
                    array_unshift($suggestions, "Quels sont les {$entity} les plus performants parmi les {$count} trouvés?");
                }
                if ($count < 10) {
                    array_unshift($suggestions, "Pourquoi y a-t-il si peu de {$entity} ({$count})?");
                }
            }
            
            // Questions basées sur les tendances
            if (isset($analytics['growth']) || isset($analytics['trends'])) {
                array_unshift($suggestions, 'Quelle est l\'évolution des tendances?');
            }
        }
        
        // Ajouter des questions générales de gestion si aucune suggestion spécifique
        if (empty($suggestions)) {
            $suggestions = [
                'Quelles sont les statistiques globales?',
                'Quels sont les éléments nécessitant une attention?',
                'Quelle est la performance globale?',
                'Quels sont les points d\'amélioration prioritaires?',
            ];
        }
        
        // Limiter à 8 suggestions maximum pour plus de choix
        return array_slice($suggestions, 0, 8);
    }
    
    private function generateFallbackResponse(string $entity, int $count, $data, array $result): string
    {
        $baseAnswer = $result['answer'] ?? "Résultats disponibles.";
        $analytics = $result['analytics'] ?? [];
        
        $response = "📊 RÉSUMÉ EXÉCUTIF\n";
        $response .= "{$baseAnswer}\n\n";
        
        if ($count > 0) {
            $response .= "📈 ANALYSE DÉTAILLÉE\n";
            $response .= "Nombre total d'éléments trouvés: {$count}\n";
            
            if (!empty($analytics)) {
                foreach ($analytics as $key => $value) {
                    if (is_numeric($value)) {
                        $formattedKey = ucwords(str_replace('_', ' ', $key));
                        if (str_contains($key, 'amount') || str_contains($key, 'value') || str_contains($key, 'price') || str_contains($key, 'revenue')) {
                            $response .= "- {$formattedKey}: " . number_format((float)$value, 2, ',', ' ') . " TND\n";
                        } elseif (str_contains($key, 'rate') || str_contains($key, 'percent')) {
                            $response .= "- {$formattedKey}: " . number_format((float)$value, 2, ',', ' ') . "%\n";
                        } else {
                            $response .= "- {$formattedKey}: " . number_format((float)$value, 0, ',', ' ') . "\n";
                        }
                    }
                }
            }
            
            $response .= "\n💡 INSIGHTS STRATÉGIQUES\n";
            $response .= "Les données sont disponibles et prêtes pour analyse approfondie.\n";
            
            $response .= "\n🎯 RECOMMANDATIONS PRIORITAIRES\n";
            $response .= "1. Consulter les détails complets des données\n";
            $response .= "2. Exporter les données pour analyse approfondie\n";
            $response .= "3. Analyser les tendances et patterns\n";
            
            $response .= "\n❓ QUESTIONS SUGGÉRÉES POUR APPROFONDIR\n";
            $response .= "Pour découvrir plus d'insights, posez ces questions:\n";
            $response .= "• Quels sont les éléments les plus performants?\n";
            $response .= "• Quelle est l'évolution sur les 3 derniers mois?\n";
            $response .= "• Quels sont les points d'attention prioritaires?\n";
            $response .= "• Comment optimiser les performances?\n";
        } else {
            $response .= "Aucune donnée trouvée pour cette requête.\n";
            $response .= "\n💡 INSIGHTS\n";
            $response .= "Il n'y a actuellement aucune donnée correspondant à votre requête.\n";
            $response .= "\n🎯 RECOMMANDATIONS\n";
            $response .= "1. Vérifier les filtres appliqués\n";
            $response .= "2. Essayer une période différente\n";
            $response .= "3. Consulter d'autres entités du système\n";
            
            $response .= "\n❓ QUESTIONS SUGGÉRÉES\n";
            $response .= "Essayez ces questions pour obtenir des résultats:\n";
            $response .= "• Quelles sont les statistiques globales?\n";
            $response .= "• Quels sont les éléments les plus récents?\n";
            $response .= "• Quelle est la performance globale?\n";
        }
        
        return $response;
    }
    
    /**
     * Build analytics summary for AI context
     */
    private function buildAnalyticsSummary(array $analytics): string
    {
        if (empty($analytics)) {
            return "";
        }
        
        $summary = "\nAnalytics disponibles:\n";
        foreach ($analytics as $key => $value) {
            if (is_numeric($value)) {
                $formattedKey = ucwords(str_replace('_', ' ', $key));
                if (str_contains($key, 'amount') || str_contains($key, 'value') || str_contains($key, 'price') || str_contains($key, 'revenue')) {
                    $summary .= "- {$formattedKey}: " . number_format((float)$value, 2, ',', ' ') . " TND\n";
                } elseif (str_contains($key, 'rate') || str_contains($key, 'percent')) {
                    $summary .= "- {$formattedKey}: " . number_format((float)$value, 2, ',', ' ') . "%\n";
                } else {
                    $summary .= "- {$formattedKey}: " . number_format((float)$value, 0, ',', ' ') . "\n";
                }
            }
        }
        
        return $summary;
    }
    
    /**
     * Generate contextual questions based on database insights
     */
    private function generateContextualQuestions(string $entity, array $analytics, int $count): array
    {
        $questions = [];
        
        // Questions basées sur les analytics
        if (isset($analytics['occupancy_rate'])) {
            if ($analytics['occupancy_rate'] < 50) {
                $questions[] = 'Comment améliorer le taux d\'occupation actuel de ' . number_format($analytics['occupancy_rate'], 1) . '%?';
            } else {
                $questions[] = 'Quels sont les facteurs de succès du taux d\'occupation élevé?';
            }
        }
        
        if (isset($analytics['conversion_rate'])) {
            if ($analytics['conversion_rate'] < 20) {
                $questions[] = 'Comment améliorer le taux de conversion de ' . number_format($analytics['conversion_rate'], 1) . '%?';
            }
        }
        
        if (isset($analytics['total_revenue'])) {
            $questions[] = 'Quelle est la prévision des revenus pour le prochain mois?';
            $questions[] = 'Quels sont les facteurs qui impactent les revenus?';
        }
        
        // Questions basées sur le count
        if ($count > 0) {
            $questions[] = "Quels sont les {$entity} les plus performants parmi les {$count} trouvés?";
            $questions[] = "Quelle est l'évolution des {$entity} sur les 3 derniers mois?";
        }
        
        return array_slice($questions, 0, 4);
    }
}
