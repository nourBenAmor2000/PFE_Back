<?php

namespace Modules\Contract\App\Models;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Contract\Database\factories\ContractFactory;

class Contract extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $table = 'contracts';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['client_id', 'logement_id', 'start_date', 'end_date', 'amount'];
    protected $casts = [
        '_id'         => 'string',
        'client_id'   => 'string',
        'logement_id' => 'string',
        'start_date'  => 'datetime',
        'end_date'    => 'datetime',
        'amount'      => 'float',
    ];
    public function payment()
    {
        return $this->hasOne(PaymentContract::class, 'contract_id', '_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function logement()
    {
        return $this->belongsTo(Logement::class, 'logement_id');
    }
    
    protected static function newFactory(): ContractFactory
    {
        //return ContractFactory::new();
    }
}
