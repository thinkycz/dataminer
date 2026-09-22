<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Persist comparison results and a deduplicated notification state.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('scrape_runs', static function (Blueprint $table): void {
            $table->json('comparison')->nullable();
        });
        Resolver::resolveSchemaBuilder()->table('recipes', static function (Blueprint $table): void {
            $table->string('last_outcome')->nullable();
            $table->boolean('email_notifications')->default(false);
        });
        Resolver::resolveSchemaBuilder()->create('collector_events', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->uuid('run_id')->unique();
            $table->string('kind');
            $table->json('summary');
            $table->timestamps();
        });
    }
};
