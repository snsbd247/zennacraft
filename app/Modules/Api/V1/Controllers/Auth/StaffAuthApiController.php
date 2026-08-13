<?php

namespace App\Modules\Api\V1\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffAuthApiController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $staff = StaffUser::where('email', $validated['email'])->first();

        if (! $staff || ! Hash::check($validated['password'], $staff->password)) {
            return $this->error('Invalid login credentials.', [
                'email' => ['Invalid login credentials.'],
            ], 422);
        }

        if ($staff->status !== 'active') {
            return $this->error('Staff account is inactive.', [
                'email' => ['Staff account is inactive.'],
            ], 403);
        }

        $token = $staff->createToken('staff-api-token', ['staff'])->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'staff' => $this->staffPayload($staff),
        ], 'Logged in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success([], 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'staff' => $this->staffPayload($request->user()),
        ]);
    }

    protected function staffPayload(StaffUser $staff): array
    {
        $staff->loadMissing('roles.permissions');

        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'status' => $staff->status,
            'roles' => $staff->roles->pluck('slug')->values(),
            'permissions' => $staff->roles
                ->flatMap(fn ($role) => $role->permissions)
                ->pluck('slug')
                ->unique()
                ->values(),
        ];
    }
}
