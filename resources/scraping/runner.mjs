import dns from 'node:dns/promises';
import net from 'node:net';
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright';
import { isCompletedSample } from './sample-limit.mjs';

const [recipePath, encodedInput, encodedLimits, encodedBrowser] =
    process.argv.slice(2);
const browserOptions = encodedBrowser
    ? JSON.parse(Buffer.from(encodedBrowser, 'base64').toString('utf8'))
    : { headless: true };
const input = JSON.parse(
    Buffer.from(encodedInput, 'base64url').toString('utf8'),
);
const limits = JSON.parse(
    Buffer.from(encodedLimits, 'base64url').toString('utf8'),
);
let rows = 0;
let bytes = 0;
let requests = 0;
let pages = 0;
let requestLimitReached = false;
const sampleLimit = new Error('Test sample row limit reached.');

function send(value) {
    process.stdout.write(`${JSON.stringify(value)}\n`);
}

function isBlockedIp(address) {
    if (net.isIPv4(address)) {
        const octets = address.split('.').map(Number);
        return (
            octets[0] === 0 ||
            octets[0] === 10 ||
            octets[0] === 127 ||
            (octets[0] === 169 && octets[1] === 254) ||
            (octets[0] === 172 && octets[1] >= 16 && octets[1] <= 31) ||
            (octets[0] === 100 && octets[1] >= 64 && octets[1] <= 127) ||
            (octets[0] === 192 && octets[1] === 0) ||
            (octets[0] === 192 && octets[1] === 168) ||
            (octets[0] === 192 && octets[1] === 0 && octets[2] === 2) ||
            (octets[0] === 198 && octets[1] >= 18 && octets[1] <= 19) ||
            (octets[0] === 198 && octets[1] === 51 && octets[2] === 100) ||
            (octets[0] === 203 && octets[1] === 0 && octets[2] === 113) ||
            octets[0] >= 224
        );
    }

    const normalized = address.toLowerCase();
    return (
        normalized === '::1' ||
        normalized === '::' ||
        normalized.startsWith('fc') ||
        normalized.startsWith('fd') ||
        normalized.startsWith('fe8') ||
        normalized.startsWith('fe9') ||
        normalized.startsWith('fea') ||
        normalized.startsWith('feb') ||
        normalized.startsWith('2001:db8:') ||
        normalized.startsWith('ff') ||
        normalized.startsWith('::ffff:')
    );
}

async function assertPublicUrl(rawUrl) {
    const url = new URL(rawUrl);
    if (!['http:', 'https:'].includes(url.protocol))
        throw new Error('Blocked non-HTTP(S) request');
    if (
        url.hostname === 'localhost' ||
        url.hostname.endsWith('.localhost') ||
        url.hostname === 'metadata.google.internal'
    ) {
        throw new Error('Blocked local or metadata request');
    }
    const addresses = await dns.lookup(url.hostname, {
        all: true,
        verbatim: true,
    });
    if (
        addresses.length === 0 ||
        addresses.some(({ address }) => isBlockedIp(address))
    ) {
        throw new Error('Blocked private, reserved, or link-local request');
    }
}

function canonicalRow(row) {
    if (!row || Array.isArray(row) || typeof row !== 'object')
        throw new Error('Rows must be objects');
    const canonical = {};
    for (const [key, value] of Object.entries(row)) {
        if (!/^[A-Za-z0-9_.-]{1,120}$/.test(key))
            throw new Error('Invalid row key');
        if (
            value !== null &&
            !['string', 'number', 'boolean'].includes(typeof value)
        )
            throw new Error('Row values must be scalar');
        canonical[key] = value;
    }
    return canonical;
}

const controller = new AbortController();
const timeout = setTimeout(
    () => controller.abort(new Error('Run timed out')),
    limits.seconds * 1000,
);
const heartbeat = setInterval(() => {
    const progress = Math.min(
        99,
        Math.max(1, Math.floor((rows / limits.rows) * 100)),
    );
    send({ type: 'progress', progress, rows, bytes, requests, pages });
}, 1000);
const browser = await chromium.launch({
    headless: browserOptions.headless,
    channel: browserOptions.channel ?? undefined,
});

try {
    const context = await browser.newContext({
        serviceWorkers: 'block',
        acceptDownloads: false,
    });
    const page = await context.newPage();
    await page.route('**/*', async (route) => {
        if (
            ['image', 'font', 'media'].includes(route.request().resourceType())
        ) {
            return route.abort('blockedbyclient');
        }
        if (requests >= limits.requests) {
            if (!requestLimitReached)
                send({
                    type: 'log',
                    level: 'warning',
                    message: 'Request limit reached.',
                });
            requestLimitReached = true;
            return route.abort('blockedbyclient');
        }
        requests += 1;
        try {
            await assertPublicUrl(route.request().url());
            await route.continue();
        } catch (error) {
            send({
                type: 'log',
                level: 'warning',
                message: String(error.message).slice(0, 500),
            });
            await route.abort('blockedbyclient');
        }
    });
    page.on('framenavigated', () => {
        pages += 1;
    });
    page.on('response', async (response) => {
        const length = Number(response.headers()['content-length'] ?? 0);
        bytes += Number.isFinite(length) ? length : 0;
        if (bytes > limits.bytes)
            controller.abort(new Error('Byte limit exceeded'));
    });

    if (!browserOptions.headless) {
        await page.goto(input.start_url, { waitUntil: 'domcontentloaded' });
        if ((await page.title()).includes('Just a moment')) {
            send({
                type: 'log',
                level: 'warning',
                message:
                    'Complete the site verification manually in the open browser window. The collector will continue afterwards.',
            });
            await page.waitForFunction(
                () => !document.title.includes('Just a moment'),
                undefined,
                { timeout: 90000 },
            );
        }
    }

    const recipe = await import(
        `${pathToFileURL(recipePath).href}?checksum=${Date.now()}`
    );
    if (typeof recipe.scrape !== 'function')
        throw new Error('Recipe does not export scrape(context)');

    try {
        await recipe.scrape(
            Object.freeze({
                page,
                browser,
                input: Object.freeze(input),
                signal: controller.signal,
                emit(row) {
                    if (controller.signal.aborted)
                        throw controller.signal.reason;
                    if (rows >= limits.rows) {
                        if (input.kind === 'test') throw sampleLimit;
                        throw new Error('Row limit exceeded');
                    }
                    const payload = canonicalRow(row);
                    const encoded = JSON.stringify(payload);
                    bytes += Buffer.byteLength(encoded);
                    if (bytes > limits.bytes)
                        throw new Error('Byte limit exceeded');
                    rows += 1;
                    send({ type: 'row', sequence: rows, payload });
                },
                log(level, message) {
                    send({
                        type: 'log',
                        level: String(level).slice(0, 20),
                        message: String(message).slice(0, 1000),
                    });
                },
            }),
        );
    } catch (error) {
        const boundedSample = isCompletedSample(
            input.kind,
            rows,
            requestLimitReached,
            error,
            sampleLimit,
        );
        if (!boundedSample) throw error;
        send({
            type: 'log',
            level: 'info',
            message: `Test sample stopped at its configured limit with ${rows} rows. This is not a full collection.`,
        });
    }

    if (rows === 0) {
        send({
            type: 'log',
            level: 'warning',
            message: `No rows collected. Page title: ${await page.title()}`,
        });
    }
    send({ type: 'summary', rows, bytes, requests, pages });
} finally {
    clearTimeout(timeout);
    clearInterval(heartbeat);
    await browser.close();
}
