<?php

declare(strict_types=1);

use App\Ai\AssistanceGate;
use App\Ai\DisabledRecipeAssistant;
use App\Ai\RecipeAssistantInterface;
use App\Models\User;
use App\Scraping\RecipeDefinition;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

\test('assistance requires separate enablement, provider availability, and entitlement', function (): void {
    $user = new User();
    $user->forceFill(['email' => 'pilot@example.com']);
    $gate = new AssistanceGate();

    \expect($gate->available($user))->toBeFalse();

    Config::inject()->assign('assistance.enabled', true);
    \expect($gate->available($user))->toBeFalse();

    Config::inject()->assign('assistance.provider_available', true);
    \expect($gate->available($user))->toBeFalse();

    Config::inject()->assign('assistance.entitled_emails', ['pilot@example.com']);
    \expect($gate->available($user))->toBeTrue();
});

\test('disabled recipe assistant cannot propose a definition', function (): void {
    $assistant = Resolver::resolve(RecipeAssistantInterface::class);
    \expect($assistant)->toBeInstanceOf(DisabledRecipeAssistant::class);

    $assistant->propose(RecipeDefinition::fromArray([
        'schema_version' => 1,
        'source_type' => 'json',
        'url' => 'https://example.com/items.json',
        'fields' => [['name' => 'name', 'path' => 'name', 'type' => 'string', 'required' => true]],
    ]), 'Find products');
})->throws(HttpException::class);
