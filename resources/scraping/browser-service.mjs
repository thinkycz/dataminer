import crypto from 'node:crypto';
import http from 'node:http';
import net from 'node:net';
import { chromium } from 'playwright';
import { createPublicProxy, isPublicAddress } from './network-proxy.mjs';
import { extractWebsite } from './website-adapter.mjs';

const MAX_SESSIONS = 4;
const SESSION_MS = 15 * 60_000;
const MAX_BODY = 1_000_000;

function bounded(value, fallback, maximum) {
    return Number.isInteger(value) && value > 0
        ? Math.min(value, maximum)
        : fallback;
}

async function bodyOf(request) {
    const chunks = [];
    let bytes = 0;
    for await (const chunk of request) {
        bytes += chunk.length;
        if (bytes > MAX_BODY) throw new Error('Request body too large');
        chunks.push(chunk);
    }
    const body = Buffer.concat(chunks).toString('utf8');
    return body ? JSON.parse(body) : {};
}

function send(response, status, value) {
    const payload = JSON.stringify(value);
    if (payload.length > 10_000_000) throw new Error('Response too large');
    response.writeHead(status, {
        'content-type': 'application/json',
        'cache-control': 'no-store',
    });
    response.end(payload);
}

function validateUrl(raw) {
    const url = new URL(raw);
    if (
        !['http:', 'https:'].includes(url.protocol) ||
        url.username ||
        url.password
    ) {
        throw new Error('Only HTTP(S) URLs are supported');
    }
    const hostname = url.hostname.replace(/^\[|\]$/g, '');
    if (
        /(^|\.)(localhost|local|internal|test|invalid)$/i.test(hostname) ||
        (net.isIP(hostname) && !isPublicAddress(hostname))
    ) {
        throw new Error('Local address blocked');
    }
    return url.href;
}

async function authExpired(session, response) {
    if ([401, 403].includes(response?.status())) return true;
    return session.signedInSelector
        ? (await session.page.locator(session.signedInSelector).count()) === 0
        : false;
}

export async function snapshot(page, selector) {
    const metadata = await page.evaluate((itemSelector) => {
        const elements = itemSelector
            ? [...document.querySelectorAll(itemSelector)].slice(0, 100)
            : [];
        return {
            title: document.title,
            url: location.href,
            accessChallenge:
                /cloudflare|verify you are human|just a moment|potvrďte, že jste|overte, že ste/i.test(
                    `${document.title} ${document.body?.innerText.slice(0, 2000) ?? ''}`,
                ),
            viewport: { width: innerWidth, height: innerHeight },
            matches: elements.map((element, index) => {
                const rect = element.getBoundingClientRect();
                return {
                    index,
                    x: rect.x,
                    y: rect.y,
                    width: rect.width,
                    height: rect.height,
                    tag: element.tagName.toLowerCase(),
                    text: element.textContent?.trim().slice(0, 120) ?? '',
                };
            }),
        };
    }, selector ?? null);
    if (selector) {
        await page.evaluate((matches) => {
            const overlay = document.createElement('div');
            overlay.id = '__dataminer_overlay__';
            overlay.style.cssText =
                'position:fixed;inset:0;pointer-events:none;z-index:2147483647';
            for (const match of matches) {
                const box = document.createElement('div');
                box.style.cssText = `position:absolute;box-sizing:border-box;border:2px solid #e11d48;left:${match.x}px;top:${match.y}px;width:${match.width}px;height:${match.height}px`;
                overlay.append(box);
            }
            document.body.append(overlay);
        }, metadata.matches);
    }
    try {
        return {
            screenshot: (
                await page.screenshot({ type: 'jpeg', quality: 65 })
            ).toString('base64'),
            metadata,
        };
    } finally {
        if (selector)
            await page.evaluate(() =>
                document.getElementById('__dataminer_overlay__')?.remove(),
            );
    }
}

async function inspect(page) {
    return page.evaluate(() => {
        const groups = new Map();
        const escape = (value) => CSS.escape(value);
        for (const element of document.querySelectorAll('body *')) {
            if (groups.size > 1000) break;
            const classes = [...element.classList]
                .filter(
                    (name) =>
                        !/^(hover|focus|active|selected|open)/i.test(name),
                )
                .sort()
                .slice(0, 3);
            const selector =
                element.tagName.toLowerCase() +
                classes.map((name) => `.${escape(name)}`).join('');
            const group = groups.get(selector) ?? [];
            if (group.length < 20) group.push(element);
            groups.set(selector, group);
        }
        return [...groups]
            .filter(
                ([, elements]) =>
                    elements.length >= 2 && elements[0].children.length > 0,
            )
            .map(([selector, elements]) => ({
                selector,
                count: document.querySelectorAll(selector).length,
                sample: elements[0].textContent?.trim().slice(0, 160) ?? '',
                fields: [
                    ...elements[0].querySelectorAll(
                        'a,h1,h2,h3,time,span,p,strong,img',
                    ),
                ]
                    .slice(0, 15)
                    .map((child) => ({
                        selector:
                            child.tagName.toLowerCase() +
                            [...child.classList]
                                .sort()
                                .slice(0, 2)
                                .map((name) => `.${escape(name)}`)
                                .join(''),
                        sample: child.textContent?.trim().slice(0, 80) ?? '',
                    })),
            }))
            .sort(
                (a, b) =>
                    b.count - a.count || a.selector.localeCompare(b.selector),
            )
            .slice(0, 30);
    });
}

export async function inspectPoint(page, x, y) {
    if (!Number.isFinite(x) || !Number.isFinite(y))
        throw new Error('Valid coordinates required');
    return page.evaluate(
        ({ x, y }) => {
            const candidates = [];
            let element = document.elementFromPoint(x, y);
            while (
                element &&
                element !== document.body &&
                candidates.length < 8
            ) {
                const tag = element.tagName.toLowerCase();
                let selector = tag;
                if (element.id) selector = `#${CSS.escape(element.id)}`;
                else if (
                    ['input', 'select', 'textarea'].includes(tag) &&
                    element.getAttribute('name')
                )
                    selector += `[name=${CSS.escape(element.getAttribute('name'))}]`;
                else {
                    const classes = [...element.classList].sort().slice(0, 3);
                    selector += classes
                        .map((name) => `.${CSS.escape(name)}`)
                        .join('');
                    if (!classes.length) {
                        const siblings = [
                            ...element.parentElement.children,
                        ].filter(
                            (sibling) => sibling.tagName === element.tagName,
                        );
                        if (siblings.length > 1)
                            selector += `:nth-of-type(${siblings.indexOf(element) + 1})`;
                    }
                }
                candidates.push({
                    selector,
                    tag,
                    text: element.textContent?.trim().slice(0, 160) ?? '',
                    count: document.querySelectorAll(selector).length,
                });
                element = element.parentElement;
            }
            return candidates;
        },
        { x, y },
    );
}

export async function startBrowserService({
    secret,
    host = '127.0.0.1',
    port = 0,
    allowPrivateBind = false,
    browserFactory = chromium,
} = {}) {
    if (typeof secret !== 'string' || secret.length < 32)
        throw new Error('Service secret must be at least 32 characters');
    if (
        !['127.0.0.1', '::1'].includes(host) &&
        !(host === '0.0.0.0' && allowPrivateBind)
    )
        throw new Error(
            'Service must bind to loopback or an explicitly private network',
        );
    const browser = await browserFactory.launch({
        headless: true,
        chromiumSandbox: true,
        env: Object.fromEntries(
            ['HOME', 'PATH', 'TMPDIR', 'LANG', 'PLAYWRIGHT_BROWSERS_PATH']
                .filter((name) => process.env[name])
                .map((name) => [name, process.env[name]]),
        ),
        args: [
            '--proxy-bypass-list=<-loopback>',
            '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1',
            '--disable-background-networking',
            '--force-webrtc-ip-handling-policy=disable_non_proxied_udp',
        ],
    });
    const sessions = new Map();
    const closeSession = async (id) => {
        const session = sessions.get(id);
        if (!session) return;
        sessions.delete(id);
        try {
            await session.context.close();
        } finally {
            await session.proxy.close();
        }
    };
    const reaper = setInterval(() => {
        for (const [id, session] of sessions) {
            if (Date.now() - session.touched > SESSION_MS)
                void closeSession(id).catch(() => {});
        }
    }, 30_000);
    reaper.unref();
    const server = http.createServer(async (request, response) => {
        try {
            const token =
                request.headers.authorization?.replace(/^Bearer /, '') ?? '';
            if (
                token.length !== secret.length ||
                !crypto.timingSafeEqual(Buffer.from(token), Buffer.from(secret))
            ) {
                return send(response, 401, { error: 'Unauthorized' });
            }
            const path = new URL(request.url, 'http://localhost').pathname;
            if (request.method === 'GET' && path === '/health') {
                return send(response, 200, {
                    status: 'ok',
                    sessions: sessions.size,
                });
            }
            if (request.method === 'POST' && path === '/sessions') {
                if (sessions.size >= MAX_SESSIONS)
                    return send(response, 429, {
                        error: 'Session limit reached',
                    });
                const input = await bodyOf(request);
                const proxy = await createPublicProxy({
                    maxRequests: bounded(input.limits?.requests, 300, 1000),
                    maxBytes: bounded(
                        input.limits?.bytes,
                        40_000_000,
                        100_000_000,
                    ),
                });
                let context;
                const budget = {
                    requests: 0,
                    maxRequests: bounded(input.limits?.requests, 300, 1000),
                    maxBytes: bounded(
                        input.limits?.bytes,
                        40_000_000,
                        100_000_000,
                    ),
                    exceeded: false,
                };
                try {
                    context = await browser.newContext({
                        proxy: { server: proxy.url },
                        serviceWorkers: 'block',
                        acceptDownloads: false,
                        storageState: input.storageState ?? undefined,
                        viewport: { width: 1280, height: 800 },
                    });
                    context.on('page', (opened) => {
                        if (context.pages().length > 4)
                            void opened.close().catch(() => {});
                    });
                    await context.route('**/*', (route) => {
                        try {
                            if (++budget.requests > budget.maxRequests) {
                                budget.exceeded = true;
                                return route.abort('blockedbyclient');
                            }
                            validateUrl(route.request().url());
                            return route.continue();
                        } catch {
                            return route.abort('blockedbyclient');
                        }
                    });
                    await context.routeWebSocket('**/*', (websocket) =>
                        websocket.close(),
                    );
                    const page = await context.newPage();
                    page.setDefaultTimeout(10_000);
                    page.setDefaultNavigationTimeout(15_000);
                    const id = crypto.randomUUID();
                    if (
                        input.signedInSelector &&
                        (typeof input.signedInSelector !== 'string' ||
                            input.signedInSelector.length > 500)
                    ) {
                        throw new Error('Invalid signed-in selector');
                    }
                    let navigationResponse;
                    if (input.url)
                        navigationResponse = await page.goto(
                            validateUrl(input.url),
                            {
                                waitUntil: 'domcontentloaded',
                            },
                        );
                    const session = {
                        context,
                        page,
                        proxy,
                        touched: Date.now(),
                        signedInSelector: input.signedInSelector ?? null,
                        budget,
                    };
                    const expired = input.url
                        ? await authExpired(session, navigationResponse)
                        : false;
                    sessions.set(id, session);
                    return send(response, 201, {
                        sessionId: id,
                        authExpired: expired,
                    });
                } catch (error) {
                    await context?.close();
                    await proxy.close();
                    throw error;
                }
            }
            const match = path.match(
                /^\/sessions\/([a-f0-9-]{36})(?:\/(navigate|act|snapshot|inspect|extract|state))?$/,
            );
            if (!match) return send(response, 404, { error: 'Not found' });
            const [, id, action] = match;
            const session = sessions.get(id);
            if (!session)
                return send(response, 404, { error: 'Session expired' });
            session.touched = Date.now();
            if (request.method === 'DELETE' && !action) {
                await closeSession(id);
                return send(response, 200, { closed: true });
            }
            if (request.method === 'GET' && action === 'state') {
                return send(response, 200, {
                    storageState: await session.context.storageState(),
                });
            }
            if (request.method === 'GET' && action === 'inspect') {
                return send(response, 200, {
                    candidates: await inspect(session.page),
                });
            }
            if (request.method === 'POST' && action === 'inspect') {
                const input = await bodyOf(request);
                return send(response, 200, {
                    candidates: await inspectPoint(
                        session.page,
                        input.x,
                        input.y,
                    ),
                });
            }
            if (request.method === 'POST' && action === 'snapshot') {
                const input = await bodyOf(request);
                const result = await snapshot(session.page, input.selector);
                result.metadata.authExpired = await authExpired(session);
                return send(response, 200, result);
            }
            if (request.method === 'POST' && action === 'navigate') {
                const input = await bodyOf(request);
                const navigationResponse = await session.page.goto(
                    validateUrl(input.url),
                    {
                        waitUntil: 'domcontentloaded',
                    },
                );
                return send(response, 200, {
                    url: session.page.url(),
                    authExpired: await authExpired(session, navigationResponse),
                });
            }
            if (request.method === 'POST' && action === 'act') {
                const input = await bodyOf(request);
                if (
                    input.action === 'click' &&
                    Number.isFinite(input.x) &&
                    Number.isFinite(input.y)
                )
                    await session.page.mouse.click(input.x, input.y);
                else if (input.action === 'click')
                    await session.page.locator(input.selector).first().click();
                else if (input.action === 'type')
                    await session.page
                        .locator(input.selector)
                        .first()
                        .fill(String(input.value ?? '').slice(0, 10_000));
                else if (input.action === 'press')
                    await session.page.keyboard.press(input.key);
                else if (input.action === 'scroll')
                    await session.page.mouse.wheel(
                        0,
                        Math.max(
                            -2000,
                            Math.min(2000, Number(input.deltaY) || 0),
                        ),
                    );
                else throw new Error('Unsupported browser action');
                return send(response, 200, {
                    url: session.page.url(),
                    authExpired: await authExpired(session),
                });
            }
            if (request.method === 'POST' && action === 'extract') {
                const input = await bodyOf(request);
                const timer = setTimeout(
                    () => void closeSession(id).catch(() => {}),
                    bounded(
                        input.limits?.seconds ??
                            input.definition?.limits?.seconds,
                        60,
                        300,
                    ) *
                        1000 +
                        15_000,
                );
                let result;
                try {
                    result = await extractWebsite(
                        session.page,
                        input.definition,
                        input.limits,
                    );
                } finally {
                    clearTimeout(timer);
                }
                const stats = session.proxy.stats();
                const exhausted =
                    session.budget.exceeded ||
                    stats.bytes > session.budget.maxBytes ||
                    stats.requests > session.budget.maxRequests;
                return send(response, 200, {
                    ...result,
                    ...stats,
                    requests: session.budget.requests,
                    complete: result.complete && !exhausted,
                    diagnostics: exhausted
                        ? [...result.diagnostics, 'network_limit']
                        : result.diagnostics,
                });
            }
            return send(response, 405, { error: 'Method not allowed' });
        } catch (error) {
            return send(response, 400, {
                error: String(error.message).slice(0, 200),
            });
        }
    });
    await new Promise((resolve) => server.listen(port, host, resolve));
    return {
        url: `http://${host}:${server.address().port}`,
        close: async () => {
            clearInterval(reaper);
            for (const id of [...sessions.keys()]) await closeSession(id);
            await new Promise((resolve) => server.close(resolve));
            await browser.close();
        },
    };
}

if (
    process.argv[1] &&
    import.meta.url === new URL(`file://${process.argv[1]}`).href
) {
    const secret = process.env.BROWSER_SERVICE_SECRET;
    const port = Number(process.env.BROWSER_SERVICE_PORT ?? 3210);
    const host = process.env.BROWSER_SERVICE_HOST ?? '127.0.0.1';
    const allowPrivateBind =
        process.env.BROWSER_SERVICE_ALLOW_PRIVATE_BIND === '1';
    const service = await startBrowserService({
        secret,
        port,
        host,
        allowPrivateBind,
    });
    process.stdout.write(`Browser service listening on ${service.url}\n`);
}
