<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CollectorOccurrenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;

class CollectorOccurrence extends BaseModel
{
    /** @use HasFactory<CollectorOccurrenceFactory> */
    use HasFactory;

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_SKIPPED = 'skipped';

    public const string STATUS_FAILED = 'failed';

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @param Builder<static> $builder
     */
    public static function querySelect(Builder $builder): void
    {
        $builder->getQuery()->select($builder->qualifyColumn('*'));
    }

    /**
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->where('slot_key', 'LIKE', '%' . $search . '%');
    }

    /**
     * Local slot identifier.
     */
    public function getSlotKey(): string
    {
        return $this->assertString('slot_key');
    }

    /**
     * Occurrence status.
     */
    public function getStatus(): string
    {
        return $this->assertString('status');
    }

    /**
     * Due instant.
     */
    public function getDueAt(): Carbon
    {
        return $this->assertCarbon('due_at');
    }

    /**
     * Optional linked run ID.
     */
    public function getRunId(): string|null
    {
        return $this->assertNullableString('run_id');
    }

    /**
     * Schedule relationship.
     *
     * @return BelongsTo<CollectorSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CollectorSchedule::class, 'schedule_id');
    }

    /**
     * Pinned version relationship.
     *
     * @return BelongsTo<RecipeVersion, $this>
     */
    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }

    /**
     * Run relationship.
     *
     * @return BelongsTo<ScrapeRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ScrapeRun::class, 'run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }
}
