import { readFileSync } from 'node:fs';
import { startBrowserService } from './browser-service.mjs';

const environment = readFileSync(
    new URL('../../.env', import.meta.url),
    'utf8',
);
const rawSecret = environment
    .match(/^BROWSER_SERVICE_SECRET=(.*)$/m)?.[1]
    ?.trim();
const secret = rawSecret?.replace(/^(["'])(.*)\1$/, '$2');

const service = await startBrowserService({ secret, port: 3210 });
process.stdout.write(`Browser service listening on ${service.url}\n`);

let closing = false;
async function close() {
    if (closing) return;
    closing = true;
    await service.close();
}

process.on('SIGINT', () => void close());
process.on('SIGTERM', () => void close());
