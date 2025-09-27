<?php

namespace App\Http\Controllers\Auth;



use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Modules\Client\App\Models\Client;
use Modules\Admin\App\Models\Admin;
use Modules\Agent\App\Models\Agent;

class VerificationController extends Controller
{
    public function verify(Request $request)
    {
        $user = $this->getUser($request->route('id'));

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified']);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Successfully verified']);
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email déjà vérifié'], 400);
        }
    
        $request->user()->sendEmailVerificationNotification();
    
        return response()->json([
            'success' => true,
            'message' => 'Email de vérification renvoyé'
        ]);
    }

    protected function getUser($id)
    {
        if ($user = Client::find($id)) return $user;
        if ($user = Admin::find($id)) return $user;
        if ($user = Agent::find($id)) return $user;
        
        abort(404, 'User not found');
    }
}