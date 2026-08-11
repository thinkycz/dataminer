<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Laravel\Ai\Migrations\AiMigration;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends AiMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conversationsTable = Config::inject()->assertString('ai.conversations.tables.conversations');
        $messagesTable = Config::inject()->assertString('ai.conversations.tables.messages');

        Resolver::resolveSchemaBuilder()->create($conversationsTable, function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('title');
            $table->timestamps();

            $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index');
        });

        Resolver::resolveSchemaBuilder()->create($messagesTable, function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36)->index();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('agent');
            $table->string('role', 25);
            $table->text('content');
            $table->text('attachments');
            $table->text('tool_calls');
            $table->text('tool_results');
            $table->text('usage');
            $table->text('meta');
            $table->text('approval_state')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
            $table->index(['participant_type', 'participant_id'], 'participant_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $messagesTable = Config::inject()->assertString('ai.conversations.tables.messages');
        $conversationsTable = Config::inject()->assertString('ai.conversations.tables.conversations');

        Resolver::resolveSchemaBuilder()->dropIfExists($messagesTable);
        Resolver::resolveSchemaBuilder()->dropIfExists($conversationsTable);
    }
};
