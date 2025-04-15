<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    protected $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Register a new user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:tenant,landlord'],
            'phone' => ['required', 'string', 'max:20']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Register user with Supabase Auth
            $auth = $this->supabase->createUser([
                'email' => $request->email,
                'password' => $request->password,
                'user_metadata' => [
                    'name' => $request->name,
                    'role' => $request->role,
                    'phone' => $request->phone
                ]
            ]);

            // Send verification email
            $this->supabase->sendEmailVerification($request->email);

            return response()->json([
                'message' => 'Registration successful. Please check your email for verification.',
                'user' => $auth->user
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $auth = $this->supabase->signIn([
                'email' => $request->email,
                'password' => $request->password
            ]);

            return response()->json([
                'message' => 'Login successful',
                'user' => $auth->user,
                'session' => $auth->session
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Logout user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $this->supabase->signOut($request->header('Authorization'));

            return response()->json([
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));

            return response()->json([
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get user information',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Request password reset.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->supabase->resetPasswordForEmail($request->email);

            return response()->json([
                'message' => 'Password reset instructions have been sent to your email.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send password reset instructions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'token' => ['required', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->supabase->updateUser(
                $request->token,
                ['password' => $request->password]
            );

            return response()->json([
                'message' => 'Password has been reset successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
