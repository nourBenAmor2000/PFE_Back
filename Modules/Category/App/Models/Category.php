<?php

namespace Modules\Category\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Category\Database\factories\CategoryFactory;
use MongoDB\Laravel\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $table = 'categorys';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name'];
    protected $casts = ['_id' => 'string'];

    public function subcategories()
{
    return $this->hasMany(SubCategory::class, 'category_id');
}
    
    protected static function newFactory(): CategoryFactory
    {
        //return CategoryFactory::new();
    }
}
