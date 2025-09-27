<?php

namespace Modules\Logement\App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Logement\Database\factories\LogementFactory;

class Logement extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $table = 'logements';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
    'title','description', 'price', 'category_id', 'agency_id', 'latitude', 'longitude', 'location', 'surface', 'floor', 'free'
    ];
    
    protected $casts = [
        '_id' => 'string', 'category_id' => 'string', 'agency_id' => 'string', 'free' => 'boolean'
    ];
    
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, null, 'logement_id', 'attribute_id')
                    ->withPivot('value');
    }
    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function visits()
    {
        return $this->hasMany(Visit::class, 'logement_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'logement_id');
    }
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'contracts', 'logement_id', 'client_id');
    }
    
    protected static function newFactory(): LogementFactory
    {
        //return LogementFactory::new();
    }
}
