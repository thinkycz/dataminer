<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * A changed connection must pass preview before scheduling.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('collector_connections', static function (Blueprint $table): void {
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('state_revision')->default(1);
        });
        Resolver::resolveSchemaBuilder()->table('scrape_runs', static function (Blueprint $table): void {
            $table->unsignedInteger('connection_revision')->nullable();
        });
    }
};
