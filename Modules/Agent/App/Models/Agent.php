<?php

namespace Modules\Agent\App\Models;
use MongoDB\Laravel\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Agent\Database\factories\AgentFactory;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\CanResetPassword;
use Modules\Agent\Notifications\VerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
class Agent extends Authenticatable implements JWTSubject, MustVerifyEmail, CanResetPassword
{
    use Notifiable, HasFactory , CanResetPasswordTrait;

    protected $connection = 'mongodb';
    protected $table = 'agents';
    protected $casts = ['_id' => 'string', 'agency_id' => 'string', 'verification_code_expires_at' => 'datetime','email_verified_at' => 'datetime',
        'verification_expires_at' => 'datetime',];
    

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'email', 'password', 'phone', 'agency_id','role', 'verification_code', 'verification_code_expires_at']; // role: 'admin_agence', 'rh', 'agent'
    protected $hidden = ['password', 'remember_token', 'verification_code'];

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agency_id');
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

    public function sendEmailVerificationNotification()
    {
        // Generate 6-digit verification code
        $verificationCode = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store code and expiration (15 minutes)
        $this->verification_code = $verificationCode;
        $this->verification_code_expires_at = now()->addMinutes(15);
        $this->save();
        
        $this->notify(new VerifyEmail($verificationCode));
    }
    /**
     * Get the email address that should be used for verification.
     */
    public function getEmailForVerification(): string
    {
        return $this->email;
    }
        // ✅ Déclaration des rôles (use App\Constants\Roles for centralized constants)
    public const ROLE_ADMIN = 'admin_agence';
    public const ROLE_RH = 'rh';
    public const ROLE_AGENT = 'agent';
    
    // Alias for consistency
    public const ROLE_ADMIN_AGENCE = 'admin_agence';
    public const ROLE_PERSONNEL = 'agent';


    // ✅ Scopes pour filtrer par rôle
    public function scopeAdminAgence($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeRh($query)
    {
        return $query->where('role', self::ROLE_RH);
    }

    public function scopeAgentPersonnel($query)
    {
        return $query->where('role', self::ROLE_AGENT);
    }

    /**
     * Get the email address that should be used for password reset.
     */
    public function getEmailForPasswordReset()
    {
        return $this->email;
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        // IMPORTANT: This method should ONLY be called from ForgotPasswordController
        // NOT during registration. 
        // Check if this is being called during registration (user just created and not verified)
        if (!$this->hasVerifiedEmail() && $this->wasRecentlyCreated) {
            \Log::error('BLOCKED: Password reset notification called during registration for agent: ' . $this->email . ' - This should NOT happen!');
            return; // DO NOT send password reset email during registration
        }
        
        \Log::info('Password reset notification called for agent: ' . $this->email . ' - This is correct (forgot password flow)');
        $this->notify(new \Modules\Agent\App\Notifications\ResetPassword($token));
    }
    
}

