<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ScrapeRowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;
use Thinkycz\LaravelCore\Support\Typer;

class ScrapeRow extends BaseModel
{
    /** @use HasFactory<ScrapeRowFactory> */
    use HasFactory;

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
     * Search the JSON payload.
     *
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->getQuery()->where('payload', 'LIKE', "%{$search}%");
    }

    /**
     * Sequence getter.
     */
    public function getSequence(): int
    {
        return $this->assertInt('sequence');
    }

    /**
     * Payload getter.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return Typer::assertStringKeyArray($this->assertArray('payload'));
    }

    /**
     * Parent run relationship.
     *
     * @return BelongsTo<ScrapeRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ScrapeRun::class, 'run_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
