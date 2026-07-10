<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * TIJDELIJKE ontwikkel-login. Laat toe om als een van de geseede rolaccounts
 * in te loggen, zodat de rolscheiding getest kan worden zonder Entra ID.
 *
 * Wordt in productie volledig geweigerd; de echte authenticatie verloopt via
 * Microsoft Entra ID (SSO/OIDC) en vervangt deze controller.
 */
class DevLoginController extends Controller
{
    private function weigerInProductie(): void
    {
        if (! app()->environment('local', 'testing')) {
            abort(404);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $this->weigerInProductie();

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($data['user_id']);
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['laatst_ingelogd_op' => now()])->save();

        // Na de login komt eerst het modulekeuzescherm.
        return redirect()->intended(route('modules.kiezen'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
