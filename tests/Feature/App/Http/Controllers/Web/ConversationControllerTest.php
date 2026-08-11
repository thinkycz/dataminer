<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers\Web;

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('obsolete generic conversation endpoints are retired', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $this->be($user, 'users')->get('/conversations/old')->assertNotFound();
    $this->be($user, 'users')->delete('/conversations/old')->assertNotFound();
});
