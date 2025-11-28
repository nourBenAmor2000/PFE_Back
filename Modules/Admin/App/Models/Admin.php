<?php

namespace Modules\Admin\App\Models;
use MongoDB\Laravel\Auth\User as Authenticatable;
// use Illuminate\Foundation\Auth\User as Authenticatable; // Use Authenticatable class
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\CanResetPassword;

class Admin extends Authenticatable implements JWTSubject, MustVerifyEmail, CanResetPassword
{
    use Notifiable, HasFactory;

    protected $connection = 'mongodb'; // Specify MongoDB connection
    protected $collection = 'admins'; // MongoDB uses 'collection' instead of 'table'

    protected $fillable = ['name', 'email', 'password', 'verification_code', 'verification_code_expires_at'];

    protected $hidden = ['password', 'remember_token', 'verification_code'];
    protected $casts = ['verification_code_expires_at' => 'datetime'];

    // Implement JWTSubject methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get the email address that should be used for verification.
     */
    public function getEmailForVerification(): string
    {
        return $this->email;
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
            \Log::error('BLOCKED: Password reset notification called during registration for admin: ' . $this->email . ' - This should NOT happen!');
            return; // DO NOT send password reset email during registration
        }
        
        \Log::info('Password reset notification called for admin: ' . $this->email . ' - This is correct (forgot password flow)');
        $this->notify(new \Modules\Admin\App\Notifications\ResetPassword($token));
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        // Generate 6-digit verification code
        $verificationCode = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store code and expiration (15 minutes)
        $this->verification_code = $verificationCode;
        $this->verification_code_expires_at = now()->addMinutes(15);
        $this->save();
        
        $this->notify(new \Modules\Admin\App\Notifications\VerifyEmail($verificationCode));
    }
}

