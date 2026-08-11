<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('scrape_runs', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('recipe_version_id')->constrained('recipe_versions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('kind');
            $table->string('status');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedBigInteger('byte_count')->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->json('limits');
            $table->longText('logs')->nullable();
            $table->text('error')->nullable();
            $table->string('artifact_disk')->nullable();
            $table->text('json_path')->nullable();
            $table->text('csv_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['recipe_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('scrape_runs');
    }
};
