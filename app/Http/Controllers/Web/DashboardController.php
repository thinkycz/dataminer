<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Thinkycz\LaravelCore\Support\Resolver;

class DashboardController
{
    /**
     * Show the dashboard.
     */
    public function __invoke(): RedirectResponse
    {
        return Resolver::resolveRedirector()->to('/recipes');
    }
}
