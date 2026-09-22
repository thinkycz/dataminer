<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Create one durable schedule per recipe.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('collector_schedules', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->unique()->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipe_version_id')->constrained('recipe_versions')->restrictOnDelete();
            $table->string('cadence', 32);
            $table->string('timezone', 80);
            $table->string('local_time', 5);
            $table->string('cron_expression', 100)->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->string('status', 16);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->string('last_slot_key', 20)->nullable();
            $table->timestamps();
        });
    }
};
