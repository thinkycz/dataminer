<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Keep durable, unique claims for local wall clock slots.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('collector_occurrences', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')->constrained('collector_schedules')->cascadeOnDelete();
            $table->foreignId('recipe_version_id')->constrained('recipe_versions')->restrictOnDelete();
            $table->string('slot_key', 20);
            $table->timestamp('due_at');
            $table->string('run_id', 36)->nullable()->index();
            $table->string('status', 16);
            $table->string('reason', 80)->nullable();
            $table->timestamps();
            $table->unique(['schedule_id', 'slot_key']);
        });
    }
};
