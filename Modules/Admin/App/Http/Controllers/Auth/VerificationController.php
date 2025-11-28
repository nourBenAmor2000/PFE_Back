<?php

namespace Modules\Admin\App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Admin\App\Models\Admin;
use Illuminate\Auth\Events\Verified;

class VerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin')->except('verify');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(Request $request, $id, $hash)
    {
        $admin = Admin::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($admin->getEmailForVerification()))) {
            abort(403, 'Invalid verification link');
        }

        if ($admin->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        if ($admin->markEmailAsVerified()) {
            event(new Verified($admin));
        }

        return response()->json(['message' => 'Email successfully verified']);
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request)
    {
        $admin = $request->user('admin');

        if ($admin->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        $admin->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification link resent'
        ]);
    }
}

