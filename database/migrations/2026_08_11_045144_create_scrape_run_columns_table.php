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
        Resolver::resolveSchemaBuilder()->create('scrape_run_columns', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id');
            $table->foreign('run_id')->references('id')->on('scrape_runs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['run_id', 'key']);
            $table->unique(['run_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('scrape_run_columns');
    }
};
