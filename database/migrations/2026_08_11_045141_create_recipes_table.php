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
        Resolver::resolveSchemaBuilder()->create('recipes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->text('start_url');
            $table->longText('instructions');
            $table->string('status');
            $table->unsignedBigInteger('active_version_id')->nullable()->index();
            $table->string('ai_conversation_id', 36)->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Resolver::resolveSchemaBuilder()->dropIfExists('recipes');
    }
};
