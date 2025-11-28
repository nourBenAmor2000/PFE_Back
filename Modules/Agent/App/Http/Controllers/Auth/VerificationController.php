<?php

namespace Modules\Agent\App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Auth\Events\Verified;
use Modules\Agent\App\Models\Agent;

class VerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:agent')->only('resend');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    /**
     * Verify email using verification code.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $agent = Agent::where('email', $request->email)->first();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'error' => 'User not found'
            ], 404);
        }

        if ($agent->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'error' => 'Email already verified'
            ], 400);
        }

        // Check if code matches and is not expired
        if (!$agent->verification_code || $agent->verification_code !== $request->code) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid verification code'
            ], 400);
        }

        if ($agent->verification_code_expires_at && $agent->verification_code_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'error' => 'Verification code has expired'
            ], 400);
        }

        // Mark email as verified
        $agent->markEmailAsVerified();
        
        // Clear verification code
        $agent->verification_code = null;
        $agent->verification_code_expires_at = null;
        $agent->save();
        
        event(new Verified($agent));

        return response()->json([
            'success' => true,
            'message' => 'Email successfully verified'
        ]);
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request)
    {
        $agent = $request->user('agent');
        
        if ($agent->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'error' => 'Email already verified'
            ], 400);
        }

        $agent->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email'
        ]);
    }
}