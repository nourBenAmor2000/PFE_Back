<?php

namespace Modules\Agent\App\Models;
use MongoDB\Laravel\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Agent\Database\factories\AgentFactory;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Modules\Agent\Notifications\VerifyEmail;

class Agent extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use Notifiable, HasFactory;

    protected $connection = 'mongodb';
    protected $table = 'agents';
    protected $casts = ['_id' => 'string', 'agency_id' => 'string'];
    

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'email', 'password', 'phone', 'agency_id','role']; // role: 'admin_agence', 'rh', 'agent'
    protected $hidden = ['password', 'remember_token'];

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
        $this->notify(new VerifyEmail()); // Notification avec le bon namespace
    }
    /**
     * Get the email address that should be used for verification.
     */
    public function getEmailForVerification(): string
    {
        return $this->email;
    }
        // ✅ Déclaration des rôles
    public const ROLE_ADMIN = 'admin_agence';
    public const ROLE_RH = 'rh';
    public const ROLE_AGENT = 'agent';


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
}