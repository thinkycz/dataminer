<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Thinkycz\LaravelCore\Models\BaseModel;

class Recipe extends BaseModel
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_GENERATING = 'generating';

    public const string STATUS_PENDING_APPROVAL = 'pending_approval';

    public const string STATUS_TESTING = 'testing';

    public const string STATUS_READY = 'ready';

    public const string STATUS_FAILED = 'failed';

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
     * Search recipes by name, URL, or instructions.
     *
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->where(static function (Builder $query) use ($search): void {
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('start_url', 'LIKE', "%{$search}%")
                ->orWhere('instructions', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Name getter.
     */
    public function getName(): string
    {
        return $this->assertString('name');
    }

    /**
     * Start URL getter.
     */
    public function getStartUrl(): string
    {
        return $this->assertString('start_url');
    }

    /**
     * Instructions getter.
     */
    public function getInstructions(): string
    {
        return $this->assertString('instructions');
    }

    /**
     * Status getter.
     */
    public function getStatus(): string
    {
        return $this->assertString('status');
    }

    /**
     * Active version identifier getter.
     */
    public function getActiveVersionId(): int|null
    {
        return $this->assertNullableInt('active_version_id');
    }

    /**
     * AI conversation identifier getter.
     */
    public function getAiConversationId(): string|null
    {
        return $this->assertNullableString('ai_conversation_id');
    }

    /**
     * Recipe owner relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Recipe versions relationship.
     *
     * @return HasMany<RecipeVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class, 'recipe_id');
    }

    /**
     * Recipe runs relationship.
     *
     * @return HasMany<ScrapeRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ScrapeRun::class, 'recipe_id');
    }

    /**
     * Active approved version relationship.
     *
     * @return BelongsTo<RecipeVersion, $this>
     */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'active_version_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
