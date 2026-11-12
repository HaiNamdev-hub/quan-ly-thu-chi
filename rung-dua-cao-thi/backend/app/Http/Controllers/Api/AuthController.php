<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()
            ->where('email', $credentials['login'])
            ->orWhere('username', $credentials['login'])
            ->first();

        if ($user === null || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Tên đăng nhập hoặc mật khẩu không chính xác.'],
            ]);
        }

        Auth::login($user, (bool) ($credentials['remember'] ?? false));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $request->user()->update($validated);

        return response()->json(['user' => $this->userPayload($request->user()->refresh())]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $request->user()->update(['password' => $validated['password']]);
        Auth::logoutOtherDevices($validated['password']);
        $request->session()->regenerate();

        return response()->json(['message' => 'Đã đổi mật khẩu.']);
    }

    /** @return array{id: int, code: string, name: string, username: ?string, email: string, phone: ?string, role: string} */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'code' => 'CTU-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
        ];
    }
}
