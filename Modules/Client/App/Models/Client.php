<?php

namespace Modules\Client\App\Models;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Client\Database\factories\ClientFactory;
use MongoDB\Laravel\Eloquent\Model as EloquentModel;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\CanResetPassword;
use Modules\Client\Notifications\CustomResetPassword;


class Client extends Authenticatable implements JWTSubject, MustVerifyEmail, CanResetPassword
{
    use Notifiable, HasFactory;
    
    protected $connection = 'mongodb';
    protected $table = 'clients';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'username', 'email', 'password', 'phone','role'];
    protected $casts = ['_id' => 'string'];
    protected $hidden = ['password', 'remember_token'];
    public const ROLE_Client = 'Client';
public function scopeClient($query)
    {
        return $query->where('role', self::ROLE_Client);
    }

    public function visits()
{
    return $this->hasMany(Visit::class, 'client_id');
}

public function contracts()
{
    return $this->hasMany(Contract::class, 'client_id');
}

public function logements()
{
    return $this->belongsToMany(Logement::class, 'contracts', 'client_id', 'logement_id');
}

public function reviews()
{
    return $this->hasMany(Review::class, 'client_id');
}
    
    protected static function newFactory(): ClientFactory
    {
        //return ClientFactory::new();
    }
     // Méthodes pour JWT
     public function getJWTIdentifier()
     {
         return $this->getKey();
     }
 
     public function getJWTCustomClaims()
     {
         return [];
     }
     public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \Modules\Client\Notifications\VerifyEmail);
    }

    /**
     * Get the email address that should be used for verification.
     */
    public function getEmailForVerification(): string
    {
        return $this->email;
    }
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }
    public function getEmailForPasswordReset()
{
    return $this->email;
}

}
