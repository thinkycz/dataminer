<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Thinkycz\LaravelCore\Support\Resolver;

return new class extends Migration {
    /**
     * Store reusable connection state encrypted at rest.
     */
    public function up(): void
    {
        Resolver::resolveSchemaBuilder()->create('collector_connections', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('origin', 2048);
            $table->string('kind');
            $table->string('status')->default('ready');
            $table->longText('credentials');
            $table->timestamps();
        });
    }
};
