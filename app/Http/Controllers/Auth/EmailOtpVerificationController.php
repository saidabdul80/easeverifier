<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailOtpVerificationController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        if (! $user->verifyEmailOtp($validated['otp'])) {
            return back()->withErrors([
                'otp' => 'The verification code is invalid or has expired.',
            ]);
        }

        event(new Verified($user));

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
