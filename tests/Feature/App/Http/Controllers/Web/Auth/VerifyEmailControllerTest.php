<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Notifications\EmailVerificationNotification;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest can view verify email page', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->get('/verify-email', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/VerifyEmail');
});

\test('authenticated unverified user triggers verification notification', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->unverified()->createOne(), User::class);

    static::assertNull($user->getEmailVerifiedAt());

    $response = $this->be($user, 'users')->post('/verify-email', [], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/VerifyEmail');
    \assertInertiaFlash($response, 'success', \__('Verification email sent.'));

    Notification::assertSentTo($user, EmailVerificationNotification::class);
});

\test('already verified user does not receive another notification', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $user->markEmailAsVerified();

    $response = $this->be($user, 'users')->post('/verify-email', [], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/VerifyEmail');

    Notification::assertNothingSent();
    \assertInertiaFlash($response, 'success', \__('Email already verified.'));
});

\test('guest cannot resend verification', function (): void {
    Notification::fake();

    $response = $this->post('/verify-email');

    $response->assertRedirect('/login');
});
