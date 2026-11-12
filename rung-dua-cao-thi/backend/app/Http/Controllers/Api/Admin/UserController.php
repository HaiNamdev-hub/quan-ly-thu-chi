<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($query) => $query->where('name', 'like', $term)
                    ->orWhere('username', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')->toString()))
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('id')->paginate(20);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        return DB::transaction(function () use ($request, $auditLogger): JsonResponse {
            $user = User::create([...$request->validated(), 'is_active' => true]);
            $auditLogger->record('user.created', $user, null, $user->only(['name', 'username', 'email', 'phone', 'role', 'is_active']));

            return response()->json(['user' => $this->payload($user)], 201);
        });
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogger $auditLogger): JsonResponse
    {
        return DB::transaction(function () use ($request, $user, $auditLogger): JsonResponse {
            $before = $user->only(['name', 'username', 'email', 'phone', 'role', 'is_active']);
            $user->update($request->validated());
            $auditLogger->record('user.updated', $user, $before, $user->only(array_keys($before)));

            return response()->json(['user' => $this->payload($user->refresh())]);
        });
    }

    public function resetPassword(Request $request, User $user, AuditLogger $auditLogger): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string', 'min:12']]);
        DB::transaction(function () use ($user, $validated, $auditLogger): void {
            $user->update(['password' => $validated['password']]);
            $user->tokens()->delete();
            $auditLogger->record('user.password_reset', $user);
        });

        return response()->json(['message' => 'Đã đặt lại mật khẩu.']);
    }

    public function activity(User $user): JsonResponse
    {
        return response()->json(['activity' => AuditLog::query()->where('user_id', $user->id)->latest('created_at')->limit(100)->get()]);
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'code' => 'CTU-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'last_login_at' => $user->last_login_at,
        ];
    }
}
