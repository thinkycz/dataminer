<?php

declare(strict_types=1);

namespace App\Models;

use App\Scraping\RecipeDefinition;
use Database\Factories\RecipeVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeVersion extends BaseModel
{
    /** @use HasFactory<RecipeVersionFactory> */
    use HasFactory;

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_TESTING = 'testing';

    public const string STATUS_TESTED = 'tested';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_REJECTED = 'rejected';

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
     * Search recipe versions by source or summary.
     *
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->where(static function (Builder $query) use ($search): void {
            $query->where('source', 'LIKE', "%{$search}%")
                ->orWhere('generation_summary', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Version number getter.
     */
    public function getVersion(): int
    {
        return $this->assertInt('version');
    }

    /**
     * Identify legacy JavaScript separately from structured definitions.
     */
    public function getDefinitionFormat(): string
    {
        return $this->assertString('definition_format');
    }

    /**
     * Read the immutable structured extraction contract.
     */
    public function getDefinition(): RecipeDefinition|null
    {
        if ($this->getDefinitionFormat() === 'legacy_js') {
            return null;
        }

        return RecipeDefinition::fromArray(Typer::assertStringKeyArray($this->assertArray('definition')));
    }

    /**
     * Source getter.
     */
    public function getSource(): string
    {
        return $this->assertString('source');
    }

    /**
     * Checksum getter.
     */
    public function getChecksum(): string
    {
        return $this->assertString('checksum');
    }

    /**
     * Status getter.
     */
    public function getStatus(): string
    {
        return $this->assertString('status');
    }

    /**
     * Generation summary getter.
     */
    public function getGenerationSummary(): string|null
    {
        return $this->assertNullableString('generation_summary');
    }

    /**
     * Generation reason getter.
     */
    public function getGenerationReason(): string
    {
        return $this->assertString('generation_reason');
    }

    /**
     * Approval call identifier getter.
     */
    public function getApprovalCallId(): string|null
    {
        return $this->assertNullableString('approval_call_id');
    }

    /**
     * Proposed columns getter.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProposedColumns(): array
    {
        return \array_values(\array_map(static fn(mixed $column): array => Typer::assertStringKeyArray(Typer::assertArray($column)), $this->assertArray('proposed_columns')));
    }

    /**
     * Approved timestamp getter.
     */
    public function getApprovedAt(): Carbon|null
    {
        return $this->assertNullableCarbon('approved_at');
    }

    /**
     * Test summary getter.
     *
     * @return array<string, mixed>|null
     */
    public function getTestSummary(): array|null
    {
        $value = $this->getAttribute('test_summary');

        return $value === null ? null : Typer::assertStringKeyArray($this->assertArray('test_summary'));
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
     * Version runs relationship.
     *
     * @return HasMany<ScrapeRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ScrapeRun::class, 'recipe_version_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'proposed_columns' => 'array',
            'usage' => 'array',
            'test_summary' => 'array',
            'approved_at' => 'datetime',
        ];
    }
}
