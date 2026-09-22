import assert from 'node:assert/strict';
import http from 'node:http';
import test from 'node:test';
import { chromium } from 'playwright';
import {
    isPublicAddress,
    resolvePublicHost,
    createPublicProxy,
} from '../../resources/scraping/network-proxy.mjs';
import {
    validateWebsiteDefinition,
    extractWebsite,
} from '../../resources/scraping/website-adapter.mjs';
import {
    startBrowserService,
    inspectPoint,
    snapshot,
} from '../../resources/scraping/browser-service.mjs';

test('proxy rejects private, mapped, reserved, and mixed DNS answers', async () => {
    for (const address of [
        '127.0.0.1',
        '10.0.0.1',
        '169.254.169.254',
        '192.168.1.2',
        '::1',
        'fc00::1',
        'fe80::1',
        '::ffff:127.0.0.1',
        '2001:db8::1',
    ]) {
        assert.equal(isPublicAddress(address), false, address);
    }
    assert.equal(isPublicAddress('8.8.8.8'), true);
    assert.equal(isPublicAddress('2606:4700:4700::1111'), true);
    await assert.rejects(
        resolvePublicHost('example.com', async () => [
            { address: '8.8.8.8' },
            { address: '127.0.0.1' },
        ]),
        /Non-public/,
    );
    await assert.rejects(resolvePublicHost('localhost'), /Local hostname/);
});

test('proxy blocks local HTTP and CONNECT targets before connecting', async () => {
    const proxy = await createPublicProxy();
    try {
        const response = await fetch(proxy.url, {
            headers: { host: '127.0.0.1', connection: 'close' },
        });
        assert.equal(response.status, 403);
        const status = await new Promise((resolve) => {
            const request = http.request(proxy.url, {
                method: 'CONNECT',
                path: '127.0.0.1:443',
            });
            request.on('connect', (response) => resolve(response.statusCode));
            request.on('error', (error) => resolve(error.message));
            request.end();
        });
        assert.equal(status, 403);
    } finally {
        await proxy.close();
    }
});

const definition = {
    schema_version: 1,
    source_type: 'website',
    url: 'https://example.com/',
    website: { record_selector: '.item' },
    fields: [{ name: 'title', path: '.title', type: 'string', required: true }],
    pagination: { mode: 'next_page', next_path: '.next' },
};

test('website extraction stops on repeated pagination URL and marks partial', async () => {
    validateWebsiteDefinition(definition);
    const page = {
        url: () => 'https://example.com/',
        goto: async () => {},
        evaluate: async () => [{ title: 'One' }],
        locator: () => ({
            count: async () => 1,
            first: () => ({
                getAttribute: async () => '/',
                waitFor: async () => {},
            }),
        }),
    };
    const result = await extractWebsite(page, definition);
    assert.deepEqual(result, {
        rows: [{ title: 'One' }],
        pages: 1,
        complete: false,
        diagnostics: ['repeated_page'],
    });
});

test('website adapter extracts raw controlled DOM for PHP mapping', async () => {
    const browser = await chromium.launch({
        headless: true,
        chromiumSandbox: true,
    });
    try {
        const page = await browser.newPage();
        await page.route('https://example.com/', (route) =>
            route.fulfill({
                contentType: 'text/html',
                body: '<article class="item"><h2 class="title"> Alpha </h2><span class="price">12.5</span></article><article class="item"><h2 class="title"> Beta </h2><span class="price">7</span></article>',
            }),
        );
        const result = await extractWebsite(page, {
            ...definition,
            fields: [
                {
                    name: 'title',
                    path: '.title',
                    type: 'string',
                    required: true,
                    transforms: [{ op: 'trim' }, { op: 'lowercase' }],
                },
                {
                    name: 'price',
                    path: '.price',
                    type: 'number',
                    required: true,
                },
            ],
            pagination: { mode: 'none' },
        });
        assert.deepEqual(result, {
            rows: [
                { title: 'Alpha', price: '12.5' },
                { title: 'Beta', price: '7' },
            ],
            pages: 1,
            complete: true,
            diagnostics: [],
        });
        const picture = await snapshot(page, '.item');
        assert.equal(picture.metadata.matches.length, 2);
        assert.equal(picture.metadata.accessChallenge, false);
        assert.ok(picture.screenshot.length > 100);
        const first = picture.metadata.matches[0];
        const selected = await inspectPoint(page, first.x + 5, first.y + 5);
        assert.ok(
            selected.some(
                (candidate) =>
                    candidate.selector === '.item' ||
                    candidate.selector === 'article.item',
            ),
        );
        const expired = await extractWebsite(page, {
            ...definition,
            website: {
                record_selector: '.item',
                signed_in_selector: '.account',
            },
            pagination: { mode: 'none' },
        });
        assert.deepEqual(expired.diagnostics, ['auth_expired']);
        await page.setContent(
            '<form><input name="email"><input name="password" type="password"></form>',
        );
        const loginPoint = await page
            .locator('input[name=email]')
            .evaluate((element) => {
                const rect = element.getBoundingClientRect();
                return {
                    x: rect.x + rect.width / 2,
                    y: rect.y + rect.height / 2,
                };
            });
        assert.ok(
            (await inspectPoint(page, loginPoint.x, loginPoint.y)).some(
                (candidate) => candidate.selector === 'input[name=email]',
            ),
        );
        await page.setContent(
            '<title>Just a moment...</title><main>Verify you are human with Cloudflare</main>',
        );
        assert.equal((await snapshot(page)).metadata.accessChallenge, true);
    } finally {
        await browser.close();
    }
});

test('website pagination keeps legitimate duplicate rows on one page', async () => {
    const browser = await chromium.launch({
        headless: true,
        chromiumSandbox: true,
    });
    try {
        const page = await browser.newPage();
        await page.route('https://example.com/**', (route) =>
            route.fulfill({
                contentType: 'text/html',
                body: route.request().url().endsWith('/second')
                    ? '<article class="item"><h2 class="title">Third</h2></article>'
                    : '<article class="item"><h2 class="title">Same</h2></article><article class="item"><h2 class="title">Same</h2></article><a class="next" href="/second">Next</a>',
            }),
        );
        const result = await extractWebsite(page, definition);
        assert.deepEqual(result.rows, [
            { title: 'Same' },
            { title: 'Same' },
            { title: 'Third' },
        ]);
        assert.equal(result.complete, true);
        assert.equal(result.pages, 2);
    } finally {
        await browser.close();
    }
});

test('website detail failures make extraction partial', async () => {
    const browser = await chromium.launch({
        headless: true,
        chromiumSandbox: true,
    });
    try {
        const context = await browser.newContext();
        const page = await context.newPage();
        await context.route('https://example.com/**', (route) =>
            route.fulfill({
                status: route.request().url().endsWith('/detail') ? 500 : 200,
                contentType: 'text/html',
                body: '<article class="item"><h2 class="title">One</h2><a class="detail" href="/detail">Details</a></article><article class="item"><h2 class="title">Two</h2></article>',
            }),
        );
        const result = await extractWebsite(page, {
            ...definition,
            website: {
                record_selector: '.item',
                detail_url_selector: '.detail',
                detail_fields: [
                    {
                        name: 'description',
                        path: '.description',
                        type: 'string',
                        required: true,
                    },
                ],
            },
            pagination: { mode: 'none' },
        });
        assert.equal(result.complete, false);
        assert.deepEqual(result.diagnostics, [
            'detail_failed',
            'required_field_missing:description',
            'detail_missing',
        ]);
    } finally {
        await browser.close();
    }
});

test('private service requires secret and creates isolated contexts', async () => {
    let contexts = 0;
    const browserFactory = {
        launch: async (options) => {
            assert.equal(options.chromiumSandbox, true);
            assert.deepEqual(
                Object.keys(options.env).filter(
                    (name) =>
                        ![
                            'HOME',
                            'PATH',
                            'TMPDIR',
                            'LANG',
                            'PLAYWRIGHT_BROWSERS_PATH',
                        ].includes(name),
                ),
                [],
            );
            return {
                close: async () => {},
                newContext: async (contextOptions) => {
                    assert.equal(contextOptions.serviceWorkers, 'block');
                    contexts++;
                    return {
                        route: async () => {},
                        routeWebSocket: async () => {},
                        on: () => {},
                        close: async () => {},
                        newPage: async () => ({
                            setDefaultTimeout: () => {},
                            setDefaultNavigationTimeout: () => {},
                            goto: async () => {},
                            url: () => 'about:blank',
                        }),
                    };
                },
            };
        },
    };
    const secret = 'a'.repeat(40);
    const service = await startBrowserService({ secret, browserFactory });
    try {
        const unauthorized = await fetch(`${service.url}/sessions`, {
            method: 'POST',
        });
        assert.equal(unauthorized.status, 401);
        const health = await fetch(`${service.url}/health`, {
            headers: { authorization: `Bearer ${secret}` },
        });
        assert.deepEqual(await health.json(), { status: 'ok', sessions: 0 });
        const ids = [];
        for (let index = 0; index < 2; index++) {
            const response = await fetch(`${service.url}/sessions`, {
                method: 'POST',
                headers: { authorization: `Bearer ${secret}` },
            });
            assert.equal(response.status, 201);
            ids.push((await response.json()).sessionId);
        }
        assert.notEqual(ids[0], ids[1]);
        assert.equal(contexts, 2);
    } finally {
        await service.close();
    }
});
