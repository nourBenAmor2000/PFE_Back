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
        $this->middleware('auth:agent')->except('verify');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function verify(Request $request, $id, $hash)
    {
        $agent = Agent::findOrFail($id);
        
        if (!hash_equals($hash, sha1($agent->getEmailForVerification()))) {
            abort(403, 'Invalid verification link');
        }

        if ($agent->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $agent->markEmailAsVerified();
        event(new Verified($agent));

        return response()->json(['message' => 'Email successfully verified']);
    }

    public function resend(Request $request)
    {
        $agent = $request->user('agent');
        
        if ($agent->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        $agent->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link resent']);
    }
    
}