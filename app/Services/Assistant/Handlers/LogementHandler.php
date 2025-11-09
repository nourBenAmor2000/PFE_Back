<?php


namespace App\Services\Assistant\Handlers;


use Modules\Logement\App\Models\Logement; // adaptez


class LogementHandler
{
public function handle(array $intent): array
{
$q = Logement::query();
if (($intent['filters']['status'] ?? null) === 'available') $q->where('status', 'available');
if (($intent['type'] ?? 'list') === 'count') return ['data'=>['count'=>$q->count()], 'sources'=>['logements']];
return ['data'=>$q->latest()->limit(50)->get(), 'sources'=>['logements']];
}
}