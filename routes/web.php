<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Auth\EmailVerificationConfirmController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\LogoutController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\VerifyEmailController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\RecipeApprovalController;
use App\Http\Controllers\Web\RecipeController;
use App\Http\Controllers\Web\RecipeVersionController;
use App\Http\Controllers\Web\ScheduleController;
use App\Http\Controllers\Web\ScrapeRunController;
use App\Http\Controllers\Web\Settings\SettingsController;
use App\Http\Middleware\EnsureInertiaUserIsAuthenticated;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()->get('/', static function () {
    if (User::auth() instanceof User) {
        return Resolver::resolveRedirector()->to('/collectors');
    }

    return Resolver::resolveRedirector()->to('/login');
});

Resolver::resolveRouteRegistrar()
    ->middleware('guest:users')
    ->group(static function (Router $router): void {
        $router->get('login', [LoginController::class, 'create']);
        $router->post('login', [LoginController::class, 'store']);
        $router->get('register', [RegisterController::class, 'create']);
        $router->post('register', [RegisterController::class, 'store']);
        $router->get('forgot-password', [ForgotPasswordController::class, 'create']);
        $router->post('forgot-password', [ForgotPasswordController::class, 'store']);
        $router->get('reset-password', [ResetPasswordController::class, 'create']);
        $router->post('reset-password', [ResetPasswordController::class, 'store']);
    });

Resolver::resolveRouteRegistrar()->get('email/verify', EmailVerificationConfirmController::class);

Resolver::resolveRouteRegistrar()
    ->middleware(EnsureInertiaUserIsAuthenticated::class)
    ->group(static function (Router $router): void {
        $router->post('logout', LogoutController::class);
        $router->get('dashboard', DashboardController::class);
        $router->get('collectors', [RecipeController::class, 'index']);
        $router->get('collectors/create', [RecipeController::class, 'create']);
        $router->post('collectors', [RecipeController::class, 'store']);
        $router->get('collectors/{recipe}', [RecipeController::class, 'show']);
        $router->get('collectors/{recipe}/setup', [RecipeController::class, 'setup']);
        $router->post('collectors/{recipe}/setup', [RecipeController::class, 'updateSetup']);
        $router->post('collectors/{recipe}/sample', [RecipeController::class, 'sample']);
        $router->post('collectors/{recipe}/notifications', [RecipeController::class, 'notifications']);
        $router->post('collectors/{recipe}/connections', [RecipeController::class, 'connection']);
        $router->post('collectors/{recipe}/connections/{connection}/revoke', [RecipeController::class, 'revokeConnection']);
        $router->post('collectors/{recipe}/browser', [RecipeController::class, 'browser']);
        $router->post('collectors/{recipe}/preview', [RecipeController::class, 'preview']);
        $router->post('collectors/{recipe}/schedule', [ScheduleController::class, 'update']);
        $router->post('collectors/{recipe}/schedule/pause', [ScheduleController::class, 'pause']);
        $router->post('collectors/{recipe}/schedule/resume', [ScheduleController::class, 'resume']);
        $router->post('collectors/{recipe}/schedule/run-now', [ScheduleController::class, 'runNow']);
        $router->post('collectors/{recipe}/generate', [RecipeController::class, 'generate']);
        $router->post('collectors/{recipe}/approvals/{approval}/decide', [RecipeApprovalController::class, 'decide']);
        $router->post('collectors/{recipe}/versions/{recipeVersion}/approve', [RecipeVersionController::class, 'approve']);
        $router->post('collectors/{recipe}/versions/{recipeVersion}/reject', [RecipeVersionController::class, 'reject']);
        $router->post('collectors/{recipe}/versions/{recipeVersion}/repair', [RecipeVersionController::class, 'repair']);
        $router->get('collectors/{recipe}/runs', [RecipeController::class, 'show']);
        $router->post('collectors/{recipe}/runs/start', [ScrapeRunController::class, 'start']);
        $router->get('runs', [ScrapeRunController::class, 'index']);
        $router->get('runs/{scrapeRun}', [ScrapeRunController::class, 'show']);
        $router->post('runs/{scrapeRun}/cancel', [ScrapeRunController::class, 'cancel']);
        $router->get('runs/{scrapeRun}/stream', [ScrapeRunController::class, 'stream']);
        $router->get('runs/{scrapeRun}/download/{format}', [ScrapeRunController::class, 'download']);
        $router->get('recipes', static fn(): RedirectResponse => Resolver::resolveRedirector()->to('/collectors'));
        $router->get('recipes/create', static fn(): RedirectResponse => Resolver::resolveRedirector()->to('/collectors/create'));
        $router->get('recipes/{recipe}/setup', static fn(int $recipe): RedirectResponse => Resolver::resolveRedirector()->to('/collectors/' . $recipe . '/setup'));
        $router->get('recipes/{recipe}', static fn(int $recipe): RedirectResponse => Resolver::resolveRedirector()->to('/collectors/' . $recipe));
        $router->get('scrape-runs', static fn(): RedirectResponse => Resolver::resolveRedirector()->to('/runs'));
        $router->get('scrape-runs/{scrapeRun}', static fn(string $scrapeRun): RedirectResponse => Resolver::resolveRedirector()->to('/runs/' . $scrapeRun));
        $router->get('verify-email', [VerifyEmailController::class, 'create']);
        $router->post('verify-email', [VerifyEmailController::class, 'store']);
        $router->get('settings', [SettingsController::class, 'edit']);
        $router->post('settings/profile', [SettingsController::class, 'updateProfile']);
        $router->post('settings/password', [SettingsController::class, 'updatePassword']);
    });
