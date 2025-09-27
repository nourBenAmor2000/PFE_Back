<?php

namespace Modules\Client\App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Custom throttle key
        $throttleKey = 'password_reset:'.$request->ip().':'.$request->email;

        // Check if throttled
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'error' => 'Too many attempts. Please try again in '.$seconds.' seconds.'
            ], 429);
        }

        // Increment attempts
        RateLimiter::hit($throttleKey, 120); // 2 minutes

        $response = Password::broker('clients')->sendResetLink(
            $request->only('email')
        );

        return $response == Password::RESET_LINK_SENT
            ? response()->json(['message' => __($response)])
            : response()->json(['error' => __($response)], 400);
    }
}