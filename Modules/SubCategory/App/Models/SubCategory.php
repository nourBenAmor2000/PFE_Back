<?php

namespace Modules\SubCategory\App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\SubCategory\Database\factories\SubCategoryFactory;

class SubCategory extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $table = 'subcategorys';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'category_id'];
    protected $casts = ['_id' => 'string', 'category_id' => 'string'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    
    protected static function newFactory(): SubCategoryFactory
    {
        //return SubCategoryFactory::new();
    }
}
