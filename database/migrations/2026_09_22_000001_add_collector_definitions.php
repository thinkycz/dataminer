<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Add configuration versions without rewriting any legacy collector.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('recipes', static function (Blueprint $table): void {
            $table->json('setup_draft')->nullable();
        });
        Resolver::resolveSchemaBuilder()->table('recipe_versions', static function (Blueprint $table): void {
            $table->string('definition_format')->default('legacy_js');
            $table->unsignedInteger('schema_version')->nullable();
            $table->json('definition')->nullable();
        });
        Resolver::resolveSchemaBuilder()->table('scrape_runs', static function (Blueprint $table): void {
            $table->boolean('complete')->default(false);
            $table->json('diagnostics')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->boolean('retention_managed')->default(false);
        });
    }
};
