<?php

declare(strict_types=1);

use App\Jobs\RunChatAgentJob;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Queue;
use Thinkycz\LaravelCore\Support\Typer;

\test('obsolete generic agent start endpoint is retired', function (): void {
    Queue::fake();
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $this->be($user, 'users')->postJson('/agent/runs', ['prompt' => 'Hello'])->assertNotFound();
    Queue::assertNotPushed(RunChatAgentJob::class);
});
