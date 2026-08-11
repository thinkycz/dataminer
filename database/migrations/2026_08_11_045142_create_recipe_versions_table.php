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
        Resolver::resolveSchemaBuilder()->create('recipe_versions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('source');
            $table->char('checksum', 64);
            $table->json('proposed_columns');
            $table->text('generation_summary')->nullable();
            $table->string('generation_reason');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->json('usage')->nullable();
            $table->string('status');
            $table->json('test_summary')->nullable();
            $table->string('approval_call_id')->nullable()->unique();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['recipe_id', 'version']);
            $table->index(['recipe_id', 'status']);
        });

        Resolver::resolveSchemaBuilder()->table('recipes', static function (Blueprint $table): void {
            $table->foreign('active_version_id')->references('id')->on('recipe_versions')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->table('recipes', static function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });

        Resolver::resolveSchemaBuilder()->dropIfExists('recipe_versions');
    }
};
