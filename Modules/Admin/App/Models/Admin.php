<?php

namespace Modules\Admin\App\Models;
use MongoDB\Laravel\Auth\User as Authenticatable;
// use Illuminate\Foundation\Auth\User as Authenticatable; // Use Authenticatable class
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class Admin extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use Notifiable, HasFactory;

    protected $connection = 'mongodb'; // Specify MongoDB connection
    protected $collection = 'admins'; // MongoDB uses 'collection' instead of 'table'

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    // Implement JWTSubject methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
