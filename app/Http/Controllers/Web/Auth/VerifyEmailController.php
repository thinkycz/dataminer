<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyEmailController
{
    use ThrottlesWebRequests;

    /**
     * Show the email verification notice.
     */
    public function create(): Response
    {
        return Inertia::render('auth/VerifyEmail');
    }

    /**
     * Resend an email verification message.
     */
    public function store(Request $request): Response
    {
        $user = User::mustAuth();

        $clearThrottle = $this->hit($this->limit());

        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            Inertia::flash('success', \__('Verification email sent.'));
        } else {
            Inertia::flash('success', \__('Email already verified.'));
        }

        $clearThrottle();

        return Inertia::render('auth/VerifyEmail');
    }
}
