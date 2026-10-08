<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    /**
     * Authenticate user and issue SPA session cookie.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user && ! $user->is_active) {
            Audit::log('failed_login', $user, [
                'reason' => 'inactive_account',
                'email' => $request->email,
            ]);

            return $this->error('الحساب معطل، يرجى التواصل مع الإدارة.', 403);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            Audit::log('failed_login', null, [
                'reason' => 'invalid_credentials',
                'email' => $request->email,
            ]);

            return response()->json([
                'data' => null,
                'meta' => [
                    'errors' => [
                        'email' => ['البريد الإلكتروني أو كلمة المرور غير صحيحة.'],
                    ],
                ],
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
                'errors' => [
                    'email' => ['البريد الإلكتروني أو كلمة المرور غير صحيحة.'],
                ],
            ], 422);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        /** @var User $authenticatedUser */
        $authenticatedUser = Auth::user();
        $authenticatedUser->update(['last_login_at' => now()]);

        Audit::log('login', $authenticatedUser);

        return $this->success(
            new UserResource($authenticatedUser),
            'تم تسجيل الدخول بنجاح.'
        );
    }

    /**
     * Terminate the authenticated session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        Audit::log('logout', $user);

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->success(null, 'تم تسجيل الخروج بنجاح.');
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    /**
     * Update authenticated user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        Audit::log('password_change', $user);

        return $this->success(null, 'تم تغيير كلمة المرور بنجاح.');
    }
}
