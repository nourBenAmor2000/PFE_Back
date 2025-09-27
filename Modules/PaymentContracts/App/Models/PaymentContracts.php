<?php

namespace Modules\PaymentContracts\App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Contract\App\Models\Contract; // pour la relation belongsTo

class PaymentContracts extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'payment_contracts'; // MongoDB

    protected $fillable = [
        'contract_id',
        'montant',
        'methode_paiement',
        'statut',
        'date_paiement',
        'reference_transaction'
    ];

    protected $casts = [
        '_id'           => 'string',
        'contract_id'   => 'string',
        'date_paiement' => 'datetime',
        'montant'       => 'float',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', '_id');
    }
}
