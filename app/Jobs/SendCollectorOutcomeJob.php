<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Thinkycz\LaravelCore\Support\Typer;

class SendCollectorOutcomeJob implements ShouldQueue
{
    use Queueable;

    /**
     * Queue only event metadata, never source samples or credentials.
     */
    public function __construct(private readonly int $recipeId, private readonly string $kind) {}

    /**
     * Send only for collectors that still opt in to email.
     */
    public function handle(): void
    {
        $recipe = Recipe::query()->find($this->recipeId);
        if (!$recipe instanceof Recipe || !$recipe->wantsEmailNotifications()) {
            return;
        }
        $user = $recipe->user()->getResults();
        if (!$user instanceof User) {
            return;
        }
        $subject = Typer::assertString(\__('collector_event_subject', ['name' => $recipe->getName()], $user->getLocale()));
        $body = Typer::assertString(\__('collector_event_' . $this->kind, [], $user->getLocale()));
        Mail::raw($body, static function (Message $message) use ($user, $subject): void {
            $message->to($user->getEmail())->subject($subject);
        });
    }
}
