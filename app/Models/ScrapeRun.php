<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ScrapeRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class ScrapeRun extends BaseModel
{
    /** @use HasFactory<ScrapeRunFactory> */
    use HasFactory;

    public const string KIND_TEST = 'test';

    public const string KIND_FULL = 'full';

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_RUNNING = 'running';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_CANCELLED = 'cancelled';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * Base select query.
     *
     * @param Builder<static> $builder
     */
    public static function querySelect(Builder $builder): void
    {
        $builder->getQuery()->select($builder->qualifyColumn('*'));
    }

    /**
     * Search runs by logs or error.
     *
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->where(static function (Builder $query) use ($search): void {
            $query->where('logs', 'LIKE', "%{$search}%")
                ->orWhere('error', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Identifier getter.
     */
    public function getId(): string
    {
        return $this->assertString('id');
    }

    /**
     * Kind getter.
     */
    public function getKind(): string
    {
        return $this->assertString('kind');
    }

    /**
     * Status getter.
     */
    public function getStatus(): string
    {
        return $this->assertString('status');
    }

    /**
     * Row count getter.
     */
    public function getRowCount(): int
    {
        return $this->assertInt('row_count');
    }

    /**
     * Progress getter.
     */
    public function getProgress(): int
    {
        return $this->assertInt('progress');
    }

    /**
     * Byte count getter.
     */
    public function getByteCount(): int
    {
        return $this->assertInt('byte_count');
    }

    /**
     * Request count getter.
     */
    public function getRequestCount(): int
    {
        return $this->assertInt('request_count');
    }

    /**
     * Limits getter.
     *
     * @return array<string, mixed>
     */
    public function getLimits(): array
    {
        return Typer::assertStringKeyArray($this->assertArray('limits'));
    }

    /**
     * Logs getter.
     */
    public function getLogs(): string|null
    {
        return $this->assertNullableString('logs');
    }

    /**
     * Error getter.
     */
    public function getError(): string|null
    {
        return $this->assertNullableString('error');
    }

    /**
     * Artifact disk getter.
     */
    public function getArtifactDisk(): string|null
    {
        return $this->assertNullableString('artifact_disk');
    }

    /**
     * JSON artifact path getter.
     */
    public function getJsonPath(): string|null
    {
        return $this->assertNullableString('json_path');
    }

    /**
     * CSV artifact path getter.
     */
    public function getCsvPath(): string|null
    {
        return $this->assertNullableString('csv_path');
    }

    /**
     * Finished timestamp getter.
     */
    public function getFinishedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('finished_at');
    }

    /**
     * Whether the run is active.
     */
    public function isActive(): bool
    {
        return \in_array($this->getStatus(), [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    /**
     * Parent recipe relationship.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    /**
     * Recipe version relationship.
     *
     * @return BelongsTo<RecipeVersion, $this>
     */
    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }

    /**
     * Run owner relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Run columns relationship.
     *
     * @return HasMany<ScrapeRunColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(ScrapeRunColumn::class, 'run_id');
    }

    /**
     * Run rows relationship.
     *
     * @return HasMany<ScrapeRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ScrapeRow::class, 'run_id');
    }

    /**
     * Whether automatic retention may clean up this dataset.
     */
    public function hasManagedRetention(): bool
    {
        return $this->assertBool('retention_managed');
    }

    /**
     * Whether the result is complete enough for comparison.
     */
    public function isComplete(): bool
    {
        return $this->assertBool('complete');
    }

    /** Stored comparison outcome.
     * @return array<string, mixed>|null
     */
    public function getComparison(): array|null
    {
        return $this->getAttribute('comparison') === null ? null : Typer::assertStringKeyArray($this->assertArray('comparison'));
    }

    /** Bounded execution diagnostics.
     * @return list<string>
     */
    public function getDiagnostics(): array
    {
        return $this->getAttribute('diagnostics') === null ? [] : \array_values(\array_map(Typer::assertString(...), $this->assertArray('diagnostics')));
    }

    /**
     * Credential revision pinned for execution and verification.
     */
    public function getConnectionRevision(): int|null
    {
        return $this->assertNullableInt('connection_revision');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'diagnostics' => 'array',
            'comparison' => 'array',
            'complete' => 'boolean',
            'retention_managed' => 'boolean',
            'heartbeat_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
