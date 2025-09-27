<?php

namespace Modules\Attribute\App\Models;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Attribute\Database\factories\AttributeFactory;

class Attribute extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $table = 'attributes';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'type'];
    protected $casts = ['_id' => 'string'];
    public function logements()
    {
        return $this->belongsToMany(Logement::class, null, 'attribute_id', 'logement_id')
                    ->withPivot('value'); 
    }
    protected static function newFactory(): AttributeFactory
    {
        //return AttributeFactory::new();
    }
}
