<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends ApiController
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active') && $request->input('is_active') !== 'all' && $request->input('is_active') !== null) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        // Sorting
        $allowedSorts = ['id', 'name', 'email', 'is_active', 'last_login_at', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'id';
        $sortDirection = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDirection);

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $paginator = $query->paginate($perPage);

        return $this->success($paginator);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:150|unique:users,email',
            'password' => 'required|string|min:8',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'اسم المستخدم مطلوب.',
            'name.max' => 'اسم المستخدم يجب ألا يتجاوز 100 حرف.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'email.unique' => 'البريد الإلكتروني مسجل بالفعل لمستخدم آخر.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب ألا تقل عن 8 أحرف.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        Audit::log('user_created', $user, [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
        ]);

        return $this->success($user, 'تم إنشاء المستخدم بنجاح.', 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        return $this->success($user);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'اسم المستخدم مطلوب.',
            'name.max' => 'اسم المستخدم يجب ألا يتجاوز 100 حرف.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'email.unique' => 'البريد الإلكتروني مسجل بالفعل لمستخدم آخر.',
            'password.min' => 'كلمة المرور يجب ألا تقل عن 8 أحرف.',
        ]);

        // Prevent self-deactivation
        if (isset($validated['is_active']) && ! $validated['is_active'] && $user->id === Auth::id()) {
            return $this->error('لا يمكنك إلغاء تفعيل حسابك الحالي.', 422);
        }

        $user->name = $validated['name'];
        $user->email = strtolower(trim($validated['email']));

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if (isset($validated['is_active'])) {
            $user->is_active = (bool) $validated['is_active'];
        }

        $user->save();

        Audit::log('user_updated', $user, [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'password_changed' => ! empty($validated['password']),
        ]);

        return $this->success($user, 'تم تحديث بيانات المستخدم بنجاح.');
    }

    /**
     * Toggle the user's active status.
     */
    public function toggleActive(User $user): JsonResponse
    {
        if ($user->id === Auth::id()) {
            return $this->error('لا يمكنك تعطيل حسابك الحالي.', 422);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        Audit::log($user->is_active ? 'user_activated' : 'user_deactivated', $user, [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
        ]);

        $message = $user->is_active ? 'تم تفعيل حساب المستخدم بنجاح.' : 'تم تعطيل حساب المستخدم بنجاح.';

        return $this->success($user, $message);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->id === Auth::id()) {
            return $this->error('لا يمكنك حذف حسابك الحالي.', 422);
        }

        // Ensure there is at least one active user remaining
        if ($user->is_active && User::where('is_active', true)->count() <= 1) {
            return $this->error('لا يمكن حذف المستخدم الأخير النشط في النظام.', 422);
        }

        $deletedDetails = [
            'deleted_user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        // Revoke tokens
        $user->tokens()->delete();
        $user->delete();

        Audit::log('user_deleted', null, $deletedDetails);

        return $this->success(null, 'تم حذف المستخدم بنجاح.');
    }
}
