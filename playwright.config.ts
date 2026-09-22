import { defineConfig, devices } from '@playwright/test';
import { randomUUID } from 'node:crypto';
import { closeSync, existsSync, openSync, realpathSync } from 'node:fs';

const existing = process.env.DATAMINER_E2E_DATABASE;
const database =
    existing ?? `/private/tmp/dataminer-e2e-${randomUUID()}.sqlite`;
if (!/^\/private\/tmp\/dataminer-e2e-[0-9a-f-]+\.sqlite$/.test(database)) {
    throw new Error(
        'Refusing to run browser tests against a non-isolated database.',
    );
}
if (!existsSync(database)) {
    closeSync(openSync(database, 'wx', 0o600));
}
if (realpathSync(database) !== database) {
    throw new Error('Refusing a linked browser test database.');
}
process.env.DATAMINER_E2E_DATABASE = database;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    timeout: 30000,
    use: {
        baseURL: 'http://127.0.0.1:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        actionTimeout: 10000,
        navigationTimeout: 15000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'node tests/e2e/start-server.mjs',
        url: 'http://127.0.0.1:8000/up',
        reuseExistingServer: false,
        timeout: 60000,
        env: {
            APP_ENV: 'testing',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            DATAMINER_E2E_DATABASE: database,
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'cookie',
            SESSION_SECURE_COOKIE: 'false',
            MAIL_MAILER: 'log',
            E2E_DISABLE_THROTTLE: 'true',
        },
    },
});
