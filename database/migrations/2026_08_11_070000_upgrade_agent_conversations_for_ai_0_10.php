<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Upgrade existing Laravel AI conversation tables to the 0.10 schema.
     */
    public function up(): void
    {
        $schema = Resolver::resolveSchemaBuilder();
        $conversationsTable = Config::inject()->assertString('ai.conversations.tables.conversations');
        $messagesTable = Config::inject()->assertString('ai.conversations.tables.messages');

        if ($schema->hasColumn($conversationsTable, 'user_id')) {
            $schema->table($conversationsTable, static function (Blueprint $table): void {
                $table->dropIndex(['user_id', 'updated_at']);
                $table->renameColumn('user_id', 'participant_id');
                $table->string('participant_type')->nullable()->after('id');
                $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index');
            });

            Resolver::resolveDatabaseManager()->connection()->table($conversationsTable)
                ->whereNotNull('participant_id')
                ->update(['participant_type' => Conversation::participantType(new User())]);
        }

        if ($schema->hasColumn($messagesTable, 'user_id')) {
            $schema->table($messagesTable, static function (Blueprint $table): void {
                $table->dropIndex('conversation_index');
                $table->dropIndex(['user_id']);
                $table->renameColumn('user_id', 'participant_id');
                $table->string('participant_type')->nullable()->after('conversation_id');
                $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
                $table->index(['participant_type', 'participant_id'], 'participant_index');
            });

            Resolver::resolveDatabaseManager()->connection()->table($messagesTable)
                ->whereNotNull('participant_id')
                ->update(['participant_type' => Conversation::participantType(new User())]);
        }

        if (!$schema->hasColumn($messagesTable, 'approval_state')) {
            $schema->table($messagesTable, static function (Blueprint $table): void {
                $table->text('approval_state')->nullable()->after('meta');
            });
        }
    }

    /**
     * Restore the pre-0.10 conversation schema.
     */
    public function down(): void
    {
        $schema = Resolver::resolveSchemaBuilder();
        $conversationsTable = Config::inject()->assertString('ai.conversations.tables.conversations');
        $messagesTable = Config::inject()->assertString('ai.conversations.tables.messages');

        if ($schema->hasColumn($messagesTable, 'approval_state')) {
            $schema->table($messagesTable, static function (Blueprint $table): void {
                $table->dropColumn('approval_state');
            });
        }
    }
};
