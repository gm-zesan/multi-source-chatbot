<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Spatie\Permission\Models\Role;
use Throwable;

class SocialAuthController extends Controller
{
    /**
     * Supported OAuth providers.
     *
     * @var list<string>
     */
    protected array $allowedProviders = ['google', 'facebook'];

    /**
     * Redirect the user to the provider authentication page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->allowedProviders, true)) {
            return redirect()->route('login')->with('error', 'Unsupported login provider.');
        }

        try {
            $driver = Socialite::driver($provider);
            if ($provider === 'facebook') {
                $driver->scopes(['public_profile', 'email']);
            }
            return $driver->redirect();
        } catch (Throwable $e) {
            Log::error("Socialite redirect error ({$provider}): " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return redirect()->route('login')->with('error', "Unable to connect with {$provider}: " . $e->getMessage());
        }
    }

    /**
     * Obtain the user information from the provider and authenticate.
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        if (!in_array($provider, $this->allowedProviders, true)) {
            return redirect()->route('login')->with('error', 'Unsupported login provider.');
        }

        // Handle user cancellation or error on provider side
        if ($request->has('error') || $request->has('error_code')) {
            $errorDesc = $request->get('error_description', $request->get('error', 'Login was cancelled.'));
            return redirect()->route('login')->with('error', ucfirst($provider) . ' login cancelled: ' . $errorDesc);
        }

        try {
            // First attempt with standard session state verification
            try {
                $socialUser = Socialite::driver($provider)->user();
            } catch (InvalidStateException $e) {
                // Fallback to stateless for localhost/IP domain cookie mismatches
                Log::info("Socialite state mismatch for {$provider}, retrying with stateless()");
                $socialUser = Socialite::driver($provider)->stateless()->user();
            }
        } catch (Throwable $e) {
            // If standard attempt threw another error, try stateless as fallback
            try {
                $socialUser = Socialite::driver($provider)->stateless()->user();
            } catch (Throwable $fallbackEx) {
                Log::error("Socialite callback failed ({$provider}): " . $fallbackEx->getMessage(), [
                    'exception' => $fallbackEx,
                    'request_all' => $request->all(),
                ]);

                $userMessage = $fallbackEx->getMessage();
                if (str_contains($userMessage, 'cURL error 60')) {
                    $userMessage = 'SSL certificate error on local server. Please check your PHP cURL CA certificate configuration.';
                } elseif (str_contains($userMessage, 'redirect_uri_mismatch')) {
                    $userMessage = 'Google Redirect URI mismatch. Ensure your Google Cloud Console Authorized Redirect URI matches: ' . url('/auth/google/callback');
                } elseif (str_contains($userMessage, 'invalid_client')) {
                    $userMessage = 'Invalid Client ID or Secret in .env for ' . ucfirst($provider) . '.';
                }

                return redirect()->route('login')->with('error', 'Authentication failed: ' . $userMessage);
            }
        }

        $email = $socialUser->getEmail();
        if (!$email) {
            return redirect()->route('login')->with('error', 'Your ' . ucfirst($provider) . ' account does not have a public or verified email address.');
        }

        $providerIdColumn = $provider . '_id';

        // 1. Check if a user exists with this social provider ID
        $user = User::where($providerIdColumn, $socialUser->getId())->first();

        // 2. If not found by provider ID, find by email
        if (!$user) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            // Ensure account is active
            if (!$user->is_active) {
                return redirect()->route('login')->with('error', 'Your account has been deactivated. Please contact an administrator.');
            }

            // Link social provider ID if not linked yet
            if (empty($user->{$providerIdColumn})) {
                $user->{$providerIdColumn} = $socialUser->getId();
            }

            // Update avatar if currently empty and social avatar is provided
            if (empty($user->avatar) && $socialUser->getAvatar()) {
                $user->avatar = $socialUser->getAvatar();
            }

            $user->last_login_at = now();
            $user->last_login_ip = $request->ip();
            $user->save();
        } else {
            // 3. Create a new user record
            $workspaceId = Workspace::first()?->id;

            $user = User::create([
                'workspace_id'      => $workspaceId,
                'name'              => $socialUser->getName() ?: ($socialUser->getNickname() ?: ucfirst($provider) . ' User'),
                'email'             => $email,
                'password'          => Hash::make(Str::random(32)),
                'avatar'            => $socialUser->getAvatar(),
                $providerIdColumn   => $socialUser->getId(),
                'password_set'      => false,
                'email_verified_at' => now(),
                'is_active'         => true,
                'last_login_at'     => now(),
                'last_login_ip'     => $request->ip(),
            ]);

            // Assign default role if roles exist
            if (class_exists(Role::class) && Role::where('name', 'user')->exists()) {
                $user->assignRole('user');
            }
        }

        Auth::login($user, true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
