<?php

namespace Modules\Admin\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Modules\Admin\App\Models\Admin;
use Tymon\JWTAuth\Exceptions\JWTException;

class AdminController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login'); // Ensure this view exists
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        try {
            if (!$token = Auth::guard('admin')->attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid credentials'
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to login, please try again'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Admin login successful',
            'token' => $token,
            'admin' => Auth::guard('admin')->user()
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'admin' => Auth::guard('admin')->user()
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('admin')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    public function refresh(): JsonResponse
    {
        $token = Auth::guard('admin')->refresh();

        return response()->json([
            'success' => true,
            'token' => $token
        ]);
    }
}