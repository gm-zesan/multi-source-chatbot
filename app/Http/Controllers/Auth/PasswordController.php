<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update or set the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isPasswordSet = $user->password_set ?? true;

        $rules = [
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];

        // Only require current password if user has already set one
        if ($isPasswordSet) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $validated = $request->validateWithBag('updatePassword', $rules);

        $user->update([
            'password'     => Hash::make($validated['password']),
            'password_set' => true,
        ]);

        return back()->with('status', $isPasswordSet ? 'password-updated' : 'password-set');
    }
}
