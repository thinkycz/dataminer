<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('obsolete generic agent cancel endpoint is retired', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $this->be($user, 'users')->postJson('/agent/runs/cancel', ['run_id' => 'old'])->assertNotFound();
});
