<?php

namespace App\Modules\AdminAuth\Http\Controllers;

use App\Modules\AdminAuth\Models\Permission;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\AdminAuth\Services\StaffUserService;
use App\Modules\Media\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Studio "Admin" group — manage the panel's staff/admin users: add, edit,
 * enable/disable, reset password, and assign per-admin permissions. Reuses the
 * existing RBAC stack (StaffUserService, Permission) and the direct
 * staff_user_permission pivot that StaffUser::hasPermission() now honours.
 */
class StaffManagementController extends Controller
{
    public function __construct(private StaffUserService $staffService, private MediaService $media) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'active');

        $admins = StaffUser::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('studio.admins.index', [
            'admins' => $admins,
            'term' => $term,
            'status' => $status,
            'currentId' => (int) optional($request->user('staff'))->id,
        ]);
    }

    public function create(): View
    {
        return view('studio.admins.form', ['admin' => new StaffUser(['status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:staff_users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $data['phone'] = $data['phone'] ?? null;
        $data['avatar'] = $this->uploadAvatar($request, $data['name']);
        unset($data['image']);
        $this->staffService->createUser($data);

        return redirect()->route('admins.index')->with('success', 'Admin created.');
    }

    public function edit(StaffUser $admin): View
    {
        return view('studio.admins.form', ['admin' => $admin]);
    }

    public function update(Request $request, StaffUser $admin): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('staff_users', 'email')->ignore($admin->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $admin->name = $data['name'];
        $admin->email = $data['email'];
        $admin->phone = $data['phone'] ?? null;
        $admin->status = $data['status'];
        if (! empty($data['password'])) {
            $admin->password = $data['password'];
        }
        if ($avatar = $this->uploadAvatar($request, $data['name'])) {
            $admin->avatar = $avatar;
        }
        $admin->save();

        return redirect()->route('admins.index')->with('success', 'Admin updated.');
    }

    public function toggleStatus(Request $request, StaffUser $admin): JsonResponse
    {
        if ($admin->id === (int) optional($request->user('staff'))->id) {
            return response()->json(['message' => "You can't disable your own account."], 422);
        }

        $admin->status = $admin->status === 'active' ? 'inactive' : 'active';
        $admin->save();

        return response()->json(['message' => 'Status updated.', 'status' => $admin->status]);
    }

    public function resetPassword(Request $request, StaffUser $admin): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);
        $admin->password = $data['password'];
        $admin->save();

        return response()->json(['message' => 'Password reset.']);
    }

    public function permissions(StaffUser $admin): View
    {
        return view('studio.admins.permissions', [
            'admin' => $admin,
            'groups' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'assigned' => $admin->permissions()->pluck('permissions.id')->all(),
        ]);
    }

    public function savePermissions(Request $request, StaffUser $admin): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $admin->permissions()->sync($data['permissions'] ?? []);

        return response()->json(['message' => 'Permissions saved for '.$admin->name.'.']);
    }

    private function uploadAvatar(Request $request, string $name): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $this->media->url($this->media->upload($request->file('image'), $name.' avatar', $request->user('staff'), 'staff'));
    }
}
