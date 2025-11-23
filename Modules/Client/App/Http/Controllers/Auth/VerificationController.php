<?php

namespace Modules\Client\App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Client\App\Models\Client;
use Illuminate\Auth\Events\Verified;

class VerificationController extends Controller
{
    /**
     * Verify email using verification code.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $client = Client::where('email', $request->email)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => 'User not found'
            ], 404);
        }

        if ($client->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'error' => 'Email already verified'
            ], 400);
        }

        // Check if code matches and is not expired
        if (!$client->verification_code || $client->verification_code !== $request->code) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid verification code'
            ], 400);
        }

        if ($client->verification_code_expires_at && $client->verification_code_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'error' => 'Verification code has expired'
            ], 400);
        }

        // Mark email as verified
        if ($client->markEmailAsVerified()) {
            // Clear verification code
            $client->verification_code = null;
            $client->verification_code_expires_at = null;
            $client->save();
            
            event(new Verified($client));
        }

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
        $client = $request->user('client');

        if ($client->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'error' => 'Email already verified'
            ], 400);
        }

        $client->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email'
        ]);
    }
}