<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CollectorConnectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class CollectorConnection extends BaseModel
{
    /** @use HasFactory<CollectorConnectionFactory> */
    use HasFactory;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * Never serialize reusable credentials.
     *
     * @var list<string>
     */
    protected $hidden = ['credentials'];

    /**
     * Base selection.
     *
     * @param Builder<static> $builder
     */
    public static function querySelect(Builder $builder): void
    {
        $builder->getQuery()->select($builder->qualifyColumn('*'));
    }

    /**
     * Search labels.
     *
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->where('name', 'LIKE', '%' . $search . '%');
    }

    /**
     * Require an explicit successful preview after reconnecting.
     */
    public function hasVerifiedState(): bool
    {
        return $this->assertNullableCarbon('verified_at') !== null;
    }

    /**
     * Connection label.
     */
    public function getName(): string
    {
        return $this->assertString('name');
    }

    /**
     * Exact authorized origin.
     */
    public function getOrigin(): string
    {
        return $this->assertString('origin');
    }

    /**
     * Connection mechanism.
     */
    public function getKind(): string
    {
        return $this->assertString('kind');
    }

    /**
     * Reconnection status.
     */
    public function getStatus(): string
    {
        return $this->assertString('status');
    }

    /**
     * Decrypt only within server execution.
     *
     * @return array<string, mixed>
     */
    public function getCredentials(): array
    {
        return Typer::assertStringKeyArray($this->assertArray('credentials'));
    }

    /**
     * Credential revision pinned for execution and verification.
     */
    public function getStateRevision(): int
    {
        return $this->assertInt('state_revision');
    }

    /**
     * Typed encrypted state.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'verified_at' => 'datetime'];
    }
}
