import { spawn, spawnSync } from 'node:child_process';
import { realpathSync, statSync } from 'node:fs';
import { startTestBrowserService } from './website-fixture.mjs';

const database = process.env.DATAMINER_E2E_DATABASE;
if (
    process.env.APP_ENV !== 'testing' ||
    process.env.DB_CONNECTION !== 'sqlite' ||
    process.env.SESSION_DRIVER !== 'cookie' ||
    typeof database !== 'string' ||
    !/^\/private\/tmp\/dataminer-e2e-[0-9a-f-]+\.sqlite$/.test(database) ||
    realpathSync(database) !== database ||
    !statSync(database).isFile()
) {
    throw new Error(
        'E2E server requires a dedicated /private/tmp/dataminer-e2e-<id>.sqlite database.',
    );
}

const migration = spawnSync(
    'php',
    ['artisan', 'migrate', '--env=testing', '--force', '--no-interaction'],
    {
        env: process.env,
        stdio: 'inherit',
    },
);
if (migration.status !== 0) {
    process.exit(migration.status ?? 1);
}

const browserService = await startTestBrowserService();
const server = spawn(
    'php',
    ['-S', '127.0.0.1:8000', '-t', 'public', 'tests/e2e/server.php'],
    {
        env: {
            ...process.env,
            BROWSER_SERVICE_URL: browserService.url,
            BROWSER_SERVICE_SECRET: browserService.secret,
        },
        stdio: 'inherit',
    },
);
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => server.kill(signal));
}
server.on('exit', async (code) => {
    await browserService.close();
    process.exit(code ?? 1);
});
