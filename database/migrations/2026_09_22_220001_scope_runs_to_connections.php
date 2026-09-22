<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Track the connection pinned to each run.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->table('scrape_runs', static function (Blueprint $table): void {
            $table->foreignId('collector_connection_id')->nullable()->constrained('collector_connections')->nullOnDelete();
        });
    }
};
