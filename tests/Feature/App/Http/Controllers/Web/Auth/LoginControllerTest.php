<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest can view login page', function (): void {
    $response = $this->get('/login', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/Login');
});

\test('user can login with database token cookie', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->post('/login', [
        'email' => $user->getEmail(),
        'password' => UserFactory::$password,
    ]);

    $response->assertRedirect('/dashboard');
    $response->assertCookie(Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName());
});

\test('Inertia login errors redirect back with readable field errors', function (): void {
    $this->from('/login')->post('/login', [
        'email' => 'missing@example.com', 'password' => 'password1',
    ], $this->inertiaHeaders())->assertRedirect('/login')->assertSessionHasErrors('email');
    $this->get('/login', $this->inertiaHeaders())->assertJsonPath('props.errors.email', \__('auth.failed'));
});
