<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ScrapeRunColumnFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thinkycz\LaravelCore\Models\BaseModel;

class ScrapeRunColumn extends BaseModel
{
    /** @use HasFactory<ScrapeRunColumnFactory> */
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
     * Search columns by key or label.
     *
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->where(static function (Builder $query) use ($search): void {
            $query->where('key', 'LIKE', "%{$search}%")
                ->orWhere('label', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Column key getter.
     */
    public function getColumnKey(): string
    {
        return $this->assertString('key');
    }

    /**
     * Label getter.
     */
    public function getLabel(): string
    {
        return $this->assertString('label');
    }

    /**
     * Type getter.
     */
    public function getType(): string
    {
        return $this->assertString('type');
    }

    /**
     * Position getter.
     */
    public function getPosition(): int
    {
        return $this->assertInt('position');
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
        return [];
    }
}
