<?php

namespace Modules\Review\App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Review\Database\factories\ReviewFactory;

class Review extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $table = 'reviews';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['client_id', 'logement_id', 'comment', 'rating'];
    protected $casts = ['_id' => 'string', 'client_id' => 'string', 'logement_id' => 'string'];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    
    public function logement()
    {
        return $this->belongsTo(Logement::class, 'logement_id');
    }
    
    
    protected static function newFactory(): ReviewFactory
    {
        //return ReviewFactory::new();
    }
}
