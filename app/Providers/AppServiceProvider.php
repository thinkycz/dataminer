<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\RecipeAiEventListener;
use App\Models\RecipeVersion;
use App\Models\ScrapeRow;
use App\Models\ScrapeRunColumn;
use App\Observers\RecipeVersionObserver;
use App\Observers\ScrapeRowObserver;
use App\Observers\ScrapeRunColumnObserver;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\ToolApprovalRequested;
use Laravel\Ai\Events\ToolApprovalResolved;
use Thinkycz\LaravelCore\Support\Resolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $events = Resolver::resolveEventDispatcher();
        $events->listen(ToolApprovalRequested::class, [RecipeAiEventListener::class, 'requested']);
        $events->listen(ToolApprovalResolved::class, [RecipeAiEventListener::class, 'resolved']);
        $events->listen(AgentPrompted::class, [RecipeAiEventListener::class, 'prompted']);
        RecipeVersion::observe(RecipeVersionObserver::class);
        ScrapeRow::observe(ScrapeRowObserver::class);
        ScrapeRunColumn::observe(ScrapeRunColumnObserver::class);
    }
}
