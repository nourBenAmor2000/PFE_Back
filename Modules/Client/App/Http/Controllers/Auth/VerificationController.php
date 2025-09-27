<?php

namespace Modules\Client\App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Client\App\Models\Client;
use Illuminate\Auth\Events\Verified;

class VerificationController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(Request $request, $id, $hash)
    {
        $client = Client::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($client->getEmailForVerification()))) {
            abort(403, 'Invalid verification link');
        }

        if ($client->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        if ($client->markEmailAsVerified()) {
            event(new Verified($client));
        }

        return response()->json(['message' => 'Email successfully verified']);
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request)
    {
        $client = $request->user('client');

        if ($client->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        $client->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link resent']);
    }
}