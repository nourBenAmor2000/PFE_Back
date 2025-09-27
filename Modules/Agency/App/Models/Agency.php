<?php

namespace Modules\Agency\App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Agency\Database\factories\AgencyFactory;

class Agency extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $table = 'agencys';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'username', 'address', 'phone','logo','location',"city"];
    protected $casts = ['_id' => 'string'];

    public function agents()
    {
        return $this->hasMany(Agent::class, 'agency_id');
    }
    public function admin()
{
    return $this->hasOne(Agent::class, 'agency_id')->where('role', Agent::ROLE_ADMIN);
}


    public function logements()
    {
        return $this->hasMany(Logement::class, 'agency_id');
    }
    
    protected static function newFactory(): AgencyFactory
    {
        //return AgencyFactory::new();
    }
}
