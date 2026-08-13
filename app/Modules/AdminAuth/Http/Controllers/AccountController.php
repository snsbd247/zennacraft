<?php

namespace App\Modules\AdminAuth\Http\Controllers;

use App\Modules\Media\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private MediaService $media) {}

    /**
     * The signed-in staff member's own account — profile info and a
     * change-password form.
     */
    public function show(): View
    {
        return view('studio.account.index', [
            'staffUser' => auth()->guard('staff')->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $staff = auth()->guard('staff')->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('staff_users', 'phone')->ignore($staff->id)->whereNull('deleted_at')],
            'avatar' => ['nullable', 'image', 'max:4096'],
        ]);

        $staff->name = $data['name'];
        $staff->phone = $data['phone'] ?: null;
        if ($request->hasFile('avatar')) {
            $staff->avatar = $this->media->url($this->media->upload($request->file('avatar'), $staff->name.' avatar', $staff, 'staff'));
        }
        $staff->save();

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $staff = auth()->guard('staff')->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($validated['current_password'], $staff->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $staff->password = $validated['password']; // auto-hashed by the model mutator
        $staff->save();

        return back()->with('success', 'Password updated.');
    }
}
