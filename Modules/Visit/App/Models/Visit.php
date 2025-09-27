<?php

namespace Modules\Visit\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Visit\Database\factories\VisitFactory;
use MongoDB\Laravel\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $table = 'visits';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['client_id', 'logement_id', 'visit_date'];
    protected $casts = ['_id' => 'string', 'client_id' => 'string', 'logement_id' => 'string'];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function logement()
    {
        return $this->belongsTo(Logement::class, 'logement_id');
    }
    
    protected static function newFactory(): VisitFactory
    {
        //return VisitFactory::new();
    }
}
