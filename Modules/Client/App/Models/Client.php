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
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;


class Client extends Authenticatable implements JWTSubject, MustVerifyEmail, CanResetPassword
{
    use Notifiable, HasFactory, CanResetPasswordTrait;
    
    protected $connection = 'mongodb';
    protected $table = 'clients';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'username', 'email', 'password', 'phone','role', 'verification_code', 'verification_code_expires_at'];
    protected $casts = ['_id' => 'string', 'verification_code_expires_at' => 'datetime'];
    protected $hidden = ['password', 'remember_token', 'verification_code'];
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
        // Generate 6-digit verification code
        $verificationCode = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store code and expiration (15 minutes)
        $this->verification_code = $verificationCode;
        $this->verification_code_expires_at = now()->addMinutes(15);
        $this->save();
        
        $this->notify(new \Modules\Client\Notifications\VerifyEmail($verificationCode));
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
        // IMPORTANT: This method should ONLY be called from ForgotPasswordController
        // NOT during registration. 
        // Check if this is being called during registration (user just created and not verified)
        if (!$this->hasVerifiedEmail() && $this->wasRecentlyCreated) {
            \Log::error('BLOCKED: Password reset notification called during registration for client: ' . $this->email . ' - This should NOT happen!');
            return; // DO NOT send password reset email during registration
        }
        
        \Log::info('Password reset notification called for client: ' . $this->email . ' - This is correct (forgot password flow)');
        $this->notify(new CustomResetPassword($token));
    }
    public function getEmailForPasswordReset()
{
    return $this->email;
}

}

