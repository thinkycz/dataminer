<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CollectorScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;

class CollectorSchedule extends BaseModel
{
    /** @use HasFactory<CollectorScheduleFactory> */
    use HasFactory;

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_PAUSED = 'paused';

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
        $builder->whereHas('recipe', static function (Builder $query) use ($search): void {
            $query->where('name', 'LIKE', '%' . $search . '%');
        });
    }

    /**
     * Schedule cadence.
     */
    public function getCadence(): string
    {
        return $this->assertString('cadence');
    }

    /**
     * IANA timezone.
     */
    public function getTimezone(): string
    {
        return $this->assertString('timezone');
    }

    /**
     * Local HH:MM anchor.
     */
    public function getLocalTime(): string
    {
        return $this->assertString('local_time');
    }

    /**
     * Advanced five-field cron expression.
     */
    public function getCronExpression(): string|null
    {
        return $this->assertNullableString('cron_expression');
    }

    /**
     * ISO weekday for weekly cadence.
     */
    public function getWeekday(): int|null
    {
        return $this->assertNullableInt('weekday');
    }

    /**
     * Current state.
     */
    public function getStatus(): string
    {
        return $this->assertString('status');
    }

    /**
     * Pinned approved version.
     */
    public function getRecipeVersionId(): int
    {
        return $this->assertInt('recipe_version_id');
    }

    /**
     * Next due instant in UTC.
     */
    public function getNextRunAt(): Carbon|null
    {
        return $this->assertNullableCarbon('next_run_at');
    }

    /**
     * Last local wall clock slot claimed.
     */
    public function getLastSlotKey(): string|null
    {
        return $this->assertNullableString('last_slot_key');
    }

    /**
     * Owner relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Recipe relationship.
     *
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
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
     * Occurrence history.
     *
     * @return HasMany<CollectorOccurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(CollectorOccurrence::class, 'schedule_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['next_run_at' => 'datetime'];
    }
}
