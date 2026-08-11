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
use App\Http\Controllers\Web\ScrapeRunController;
use App\Http\Controllers\Web\Settings\SettingsController;
use App\Http\Middleware\EnsureInertiaUserIsAuthenticated;
use App\Models\User;
use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()->get('/', static function () {
    if (User::auth() instanceof User) {
        return Resolver::resolveRedirector()->to('/recipes');
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
        $router->get('recipes', [RecipeController::class, 'index']);
        $router->get('recipes/create', [RecipeController::class, 'create']);
        $router->post('recipes', [RecipeController::class, 'store']);
        $router->get('recipes/{recipe}', [RecipeController::class, 'show']);
        $router->post('recipes/{recipe}/generate', [RecipeController::class, 'generate']);
        $router->post('recipes/{recipe}/approvals/{approval}/decide', [RecipeApprovalController::class, 'decide']);
        $router->post('recipes/{recipe}/versions/{recipeVersion}/approve', [RecipeVersionController::class, 'approve']);
        $router->post('recipes/{recipe}/versions/{recipeVersion}/reject', [RecipeVersionController::class, 'reject']);
        $router->post('recipes/{recipe}/versions/{recipeVersion}/repair', [RecipeVersionController::class, 'repair']);
        $router->get('recipes/{recipe}/runs', [RecipeController::class, 'show']);
        $router->post('recipes/{recipe}/runs/start', [ScrapeRunController::class, 'start']);
        $router->get('scrape-runs', [ScrapeRunController::class, 'index']);
        $router->get('scrape-runs/{scrapeRun}', [ScrapeRunController::class, 'show']);
        $router->post('scrape-runs/{scrapeRun}/cancel', [ScrapeRunController::class, 'cancel']);
        $router->get('scrape-runs/{scrapeRun}/stream', [ScrapeRunController::class, 'stream']);
        $router->get('scrape-runs/{scrapeRun}/download/{format}', [ScrapeRunController::class, 'download']);
        $router->get('verify-email', [VerifyEmailController::class, 'create']);
        $router->post('verify-email', [VerifyEmailController::class, 'store']);
        $router->get('settings', [SettingsController::class, 'edit']);
        $router->post('settings/profile', [SettingsController::class, 'updateProfile']);
        $router->post('settings/password', [SettingsController::class, 'updatePassword']);
    });
