<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Headers for an Inertia JSON page request.
     *
     * @return array<string, string>
     */
    protected function inertiaHeaders(): array
    {
        $version = \app(HandleInertiaRequests::class)->version(Request::create('/'));

        return [
            'X-Inertia' => 'true',
            ...($version === null ? [] : ['X-Inertia-Version' => $version]),
        ];
    }
}
