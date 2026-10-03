import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { registerPilot } from './pilot';

function fixture(email: string, state: string): string {
    return execFileSync(
        'php',
        [resolve('tests/e2e/collector-fixture.php'), email, state],
        {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: process.env.DATAMINER_E2E_DATABASE,
                QUEUE_CONNECTION: 'database',
            },
            encoding: 'utf8',
        },
    ).trim();
}

async function createCollector(page: Page): Promise<string> {
    await page
        .getByRole('link', {
            name: /New collector|Create your first collector/,
            exact: true,
        })
        .click();
    await page.getByRole('radio', { name: 'JSON API', exact: true }).check();
    await page.getByLabel('Source URL').fill('https://example.com/products');
    await page.getByLabel('Collector name').fill('Office supplies');
    await page.getByRole('button', { name: 'Choose data' }).click();
    await page.waitForURL(/\/collectors\/\d+\/setup$/);
    return page.url().replace(/\/setup$/, '');
}

async function saveAndPreview(
    page: Page,
    email: string,
    collectorUrl: string,
): Promise<void> {
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await page.getByLabel('Source format').selectOption('json');
    await page.getByLabel('Records path').fill('data.items');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await page.getByText('Column options', { exact: true }).first().click();
    await page.getByLabel('Source path or selector').first().fill('name');
    await page.getByRole('button', { name: 'Preview data' }).click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    fixture(email, 'sample');
    await page.goto(collectorUrl);
    await expect(
        page.getByRole('button', { name: 'Use this setup' }),
    ).toBeVisible();
}

for (const [source, label] of [
    ['website', 'Website'],
    ['json', 'JSON API'],
    ['csv', 'CSV link'],
    ['xml', 'XML feed'],
]) {
    test(`source selection opens and retains a ${source} builder`, async ({
        page,
    }, testInfo) => {
        await registerPilot(page, 'redesign');
        await page
            .getByRole('link', {
                name: /New collector|Create your first collector/,
                exact: true,
            })
            .click();
        await expect(page.getByRole('radio')).toHaveCount(4);
        await page.getByRole('button', { name: 'Choose data' }).click();
        await expect(page.getByLabel('Source URL')).toBeFocused();
        await page
            .getByLabel('Source URL')
            .fill('https://example.com/products');
        await expect(page.getByLabel('Collector name')).toHaveValue(
            'example.com',
        );
        await page.getByLabel('Collector name').fill('Office supplies');
        await page.getByRole('radio', { name: label, exact: true }).check();
        await page.screenshot({
            path: testInfo.outputPath('setup.png'),
            fullPage: true,
        });
        await page.getByRole('button', { name: 'Choose data' }).click();
        await page.waitForURL(/\/collectors\/\d+\/setup$/);
        await expect(
            page.getByRole('heading', { name: 'Office supplies' }),
        ).toBeVisible();
        await expect(page.getByLabel('Source format')).toHaveValue(source!);
        await page.reload();
        await expect(page.getByLabel('Source format')).toHaveValue(source!);
        await expect(page.getByText('export async')).not.toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath('builder.png'),
            fullPage: true,
        });
    });
}

test('manual setup explores a sample and saves corrected source fields', async ({
    page,
}) => {
    await registerPilot(page, 'redesign');
    await createCollector(page);
    await page.route('**/collectors/*/sample', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                sample: { data: { items: [{ name: 'Notebook', price: 12 }] } },
            }),
        });
    });
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await page.getByLabel('Source format').selectOption('json');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await page.getByRole('button', { name: 'Load sample' }).click();
    await expect(
        page.getByRole('cell', { name: 'Notebook', exact: true }),
    ).toBeVisible();
    await page.getByText('Choose a list of items', { exact: true }).click();
    await page.getByRole('button', { name: 'Selected', exact: true }).click();
    await expect(page.getByLabel('Records path')).toHaveValue('data.items');
    await page.getByRole('button', { name: 'Add column' }).click();
    await page.getByLabel('Column name').last().fill('price');
    await page.getByText('Column options', { exact: true }).last().click();
    await page.getByLabel('Source path or selector').last().fill('price');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page.getByLabel('Column name').last()).toHaveValue('price');
    await page.reload();
    await expect(page.getByLabel('Column name').last()).toHaveValue('price');
    await expect(page.getByLabel('Records path')).toHaveValue('data.items');
});

test('CSV headers can be chosen as fields without entering paths', async ({
    page,
}) => {
    await registerPilot(page, 'csv-picker');
    await createCollector(page);
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await page.getByLabel('Source format').selectOption('csv');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await page.route('**/collectors/*/sample', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                sample: '"Product name",price\n"Notebook",12\n',
            }),
        });
    });
    await page.getByRole('button', { name: 'Load sample' }).click();
    await page
        .getByRole('checkbox', {
            name: 'Keep Product name column',
            exact: true,
        })
        .check();
    await expect(page.getByLabel('Column name').last()).toHaveValue(
        'Product_name',
    );
    await expect(page.getByLabel('Source path or selector').last()).toHaveValue(
        'Product name',
    );
    await expect(
        page.getByText('No repeated object list was detected'),
    ).toHaveCount(0);
    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.reload();
    await expect(page.getByLabel('Source path or selector').last()).toHaveValue(
        'Product name',
    );
});

for (const source of ['json', 'csv', 'xml']) {
    test(`${source} sample selection extracts real rows through the saved mapping`, async ({
        page,
    }, testInfo) => {
        await registerPilot(page, `source-${source}`);
        await page
            .getByRole('link', {
                name: /New collector|Create your first collector/,
                exact: true,
            })
            .click();
        await page
            .getByRole('radio', {
                name:
                    source === 'json'
                        ? 'JSON API'
                        : source === 'csv'
                          ? 'CSV link'
                          : 'XML feed',
                exact: true,
            })
            .check();
        await page.getByLabel('Collector name').fill(`${source} catalog`);
        await page
            .getByLabel('Source URL')
            .fill(`https://1.1.1.1/e2e/catalog.${source}`);
        await page.getByRole('button', { name: 'Choose data' }).click();
        await page.waitForURL(/\/collectors\/\d+\/setup$/);
        await page.getByRole('button', { name: 'Load sample' }).click();
        if (source !== 'csv')
            await expect(page.getByLabel('Records path')).not.toHaveValue('');
        const titlePath =
            source === 'json'
                ? 'details.title'
                : source === 'csv'
                  ? 'Product name'
                  : './ns:details/ns:title';
        await page
            .getByRole('checkbox', {
                name: `Keep ${source === 'csv' ? titlePath : 'title'} column`,
                exact: true,
            })
            .check();
        await expect(page.getByLabel('Column name')).toHaveCount(1);
        await page
            .getByRole('checkbox', {
                name: 'Keep price column',
                exact: true,
            })
            .check();
        await page.getByText('Column options', { exact: true }).last().click();
        await page.getByLabel('Data type').last().selectOption('number');
        await page.getByText('Column options', { exact: true }).last().click();
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({
            path: testInfo.outputPath('selected-columns.png'),
            fullPage: true,
        });
        await page.getByRole('button', { name: 'Preview data' }).click();
        await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
        await expect(
            page.getByRole('cell', { name: 'Notebook', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('cell', { name: 'Pencil', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('cell', { name: '12', exact: true }),
        ).toBeVisible();
        await expect(page.getByRole('status')).toContainText('Complete');
        await expect(
            page.getByRole('link', { name: 'Download CSV', exact: true }),
        ).toBeVisible();
        await page
            .getByRole('button', { name: 'Use this setup', exact: true })
            .click();
        await page.waitForURL(/\/collectors\/\d+$/);
        const workspaceUrl = page.url();
        await page
            .getByRole('button', { name: 'Collect now', exact: true })
            .click();
        await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
        await expect(page.getByRole('status')).toContainText('Complete');
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page
                .getByRole('link', { name: 'Download CSV', exact: true })
                .click(),
        ]);
        expect(await download.failure()).toBeNull();
        await page.goto(workspaceUrl);
        await page.getByRole('link', { name: 'Schedule', exact: true }).click();
        await expect(
            page.getByLabel('Time zone', { exact: true }),
        ).toBeVisible();
    });
}

test('saved credentials can be revoked and are removed from setup choices', async ({
    page,
}) => {
    await registerPilot(page, 'credential');
    await createCollector(page);
    await page
        .locator('summary')
        .filter({ hasText: 'Saved credentials' })
        .click();
    await page.getByLabel('Secret value').fill('example-token');
    await page.getByRole('button', { name: 'Save credentials' }).click();
    await expect(page.getByLabel('Secret value')).toHaveValue('');
    await expect(
        page.getByLabel('Saved credentials').locator('option'),
    ).toHaveCount(2);
    await page.getByRole('button', { name: 'Revoke' }).click();
    await expect(
        page.getByLabel('Saved credentials').locator('option'),
    ).toHaveCount(1);
    await page.reload();
    await expect(
        page.getByLabel('Saved credentials').locator('option'),
    ).toHaveCount(1);
    await expect(page.getByRole('button', { name: 'Revoke' })).toHaveCount(0);
});

test('the builder exposes source-specific mapping controls', async ({
    page,
}) => {
    await registerPilot(page, 'redesign');
    await createCollector(page);
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    const format = page.getByLabel('Source format');
    await format.selectOption('csv');
    await expect(page.getByLabel('Delimiter')).toBeVisible();
    await format.selectOption('xml');
    await expect(page.getByLabel('XML namespaces (JSON object)')).toBeHidden();
    await page.getByText('Advanced XML namespaces', { exact: true }).click();
    await expect(page.getByLabel('XML namespaces (JSON object)')).toBeVisible();
    await format.selectOption('website');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: 'Select website content visually' }),
    ).toBeVisible();
    await expect(page.getByLabel('Repeated record selector')).toBeHidden();
    await page
        .getByText('Advanced CSS selectors', { exact: true })
        .first()
        .click();
    await expect(page.getByLabel('Repeated record selector')).toBeVisible();
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await format.selectOption('json');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: 'A peek at your source' }),
    ).toBeVisible();
});

test('challenge screens offer manual page interaction while keeping field selection separate', async ({
    page,
}) => {
    await registerPilot(page, 'page-interaction');
    await createCollector(page);
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await page.getByLabel('Source format').selectOption('website');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    let challenge = true;
    const clicks: Array<{ action: string; x: number; y: number }> = [];
    const screenshot = `data:image/svg+xml;base64,${Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="800"><rect width="1280" height="800" fill="white"/></svg>').toString('base64')}`;
    await page.route('**/collectors/*/browser', async (route) => {
        const request = route.request().postDataJSON();
        if (request.action === 'act') clicks.push(request.input);
        await route.fulfill({
            json: {
                ...(request.action === 'open' ? { opened: true } : {}),
                screenshot,
                metadata: {
                    viewport: { width: 1280, height: 800 },
                    accessChallenge: challenge,
                    matches: [],
                },
            },
        });
    });
    await page.getByRole('button', { name: 'Open page', exact: true }).click();
    await expect(
        page.getByRole('button', { name: 'Select data', exact: true }),
    ).toBeDisabled();
    await page.getByRole('button', { name: 'Use page', exact: true }).click();
    const preview = page.getByRole('button', {
        name: 'Interact with the website preview',
        exact: true,
    });
    await expect(preview).toBeEnabled();
    await preview.press('Enter');
    expect(clicks).toHaveLength(0);
    const bounds = await preview.boundingBox();
    if (!bounds) throw new Error('Source screenshot is missing.');
    await preview.click({
        position: { x: bounds.width / 4, y: bounds.height / 2 },
    });
    await expect.poll(() => clicks.length).toBe(1);
    expect(clicks[0]?.action).toBe('click');
    expect(clicks[0]?.x).toBeCloseTo(320, -1);
    expect(clicks[0]?.y).toBeCloseTo(400, -1);
    challenge = false;
    await page
        .getByRole('button', { name: 'Refresh screenshot', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Select data', exact: true })
        .click();
    await expect(
        page.getByRole('button', {
            name: 'Inspect an item in the page preview',
            exact: true,
        }),
    ).toBeEnabled();
    await expect(page.getByLabel('Column name').first()).toHaveValue('name');
});

test('website content can be selected into records and columns without typing selectors', async ({
    page,
}, testInfo) => {
    await registerPilot(page, 'redesign');
    await page
        .getByRole('link', {
            name: /New collector|Create your first collector/,
            exact: true,
        })
        .click();
    await page.getByLabel('Collector name').fill('Visual catalog');
    await page.getByLabel('Source URL').fill('https://example.com/catalog');
    await page.getByRole('button', { name: 'Choose data' }).click();
    await page.waitForURL(/\/collectors\/\d+\/setup$/);

    const screenshot = `data:image/svg+xml;base64,${Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="800"><rect width="1280" height="800" fill="white"/><text x="60" y="100">Notebook $12</text></svg>').toString('base64')}`;
    await page.route('**/collectors/*/browser', async (route) => {
        const request = route.request().postDataJSON() as {
            action: string;
            input?: { x?: number; y?: number };
        };
        const x = request.input?.x ?? 0;
        const y = request.input?.y ?? 0;
        const candidates =
            y > 300
                ? [
                      {
                          selector: x > 500 ? 'a.next' : 'a.product-link',
                          tag: 'a',
                          text: x > 500 ? 'Next' : 'Details',
                          count: 2,
                      },
                  ]
                : x > 500
                  ? [
                        {
                            selector: 'input[name=email]',
                            tag: 'input',
                            text: '',
                            count: 1,
                        },
                    ]
                  : [
                        {
                            selector: 'article.product-card',
                            tag: 'article',
                            text: 'Notebook $12',
                            count: 2,
                        },
                        {
                            selector: 'span.price',
                            tag: 'span',
                            text: '$12',
                            count: 2,
                        },
                    ];
        const body =
            request.action === 'open'
                ? {
                      opened: true,
                      screenshot,
                      metadata: {
                          viewport: { width: 1280, height: 800 },
                          accessChallenge: false,
                      },
                  }
                : request.action === 'inspect'
                  ? { candidates }
                  : {
                        screenshot,
                        metadata: {
                            viewport: { width: 1280, height: 800 },
                            matches: [
                                {
                                    index: 0,
                                    x: 20,
                                    y: 20,
                                    width: 200,
                                    height: 100,
                                },
                                {
                                    index: 1,
                                    x: 20,
                                    y: 150,
                                    width: 200,
                                    height: 100,
                                },
                            ],
                        },
                    };
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(body),
        });
    });

    await expect(
        page.getByRole('button', { name: 'Preview data' }),
    ).toBeDisabled();
    await page.getByRole('button', { name: 'Open page' }).click();
    const preview = page.getByRole('button', {
        name: 'Inspect an item in the page preview',
    });
    await expect(preview).toBeVisible();
    await preview.click({ position: { x: 80, y: 80 } });
    await expect(
        page.getByText('Choose the highlighted element below.'),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('interactive-picker.png'),
        fullPage: true,
    });
    await page.getByRole('button', { name: 'Use as record' }).first().click();
    await expect(page.getByText('2 matching items')).toBeVisible();
    await expect(
        page.getByText('Click text or a link inside a record'),
    ).toBeVisible();
    await preview.click({ position: { x: 80, y: 80 } });
    await page.getByRole('button', { name: 'Add as column' }).last().click();
    await expect(page.getByLabel('Source path or selector').last()).toHaveValue(
        'span.price',
    );
    await expect(page.getByLabel('Column name').first()).toHaveValue('price');
    await expect(
        page.getByRole('button', { name: 'Preview data' }),
    ).toBeEnabled();
    const login = page.locator('details').filter({
        has: page.getByText('Login to a source site', { exact: true }),
    });
    await login.locator('summary').first().click();
    await login.getByRole('button', { name: 'Pick from screenshot' }).click();
    await preview.click({ position: { x: 300, y: 80 } });
    await page.getByRole('button', { name: 'Use as login input' }).click();
    await expect(login.getByText('input[name=email]')).toBeVisible();
    await login.getByLabel('Credential value').fill('example-login-value');
    await login.getByRole('button', { name: 'Type value once' }).click();
    await expect(login.getByLabel('Credential value')).toHaveValue('');
    const detail = page.locator('details').filter({
        has: page.getByText('Details-page fields', { exact: true }),
    });
    await detail.locator('summary').first().click();
    await detail
        .getByRole('button', { name: 'Pick from screenshot' })
        .first()
        .click();
    await preview.click({ position: { x: 80, y: 200 } });
    await page.getByRole('button', { name: 'Use as detail link' }).click();
    await expect(detail.getByText('a.product-link')).toBeVisible();
    await page.getByText('More pages', { exact: true }).click();
    await page.getByLabel('How to continue').selectOption('next_page');
    await page
        .getByRole('button', { name: 'Pick from screenshot' })
        .last()
        .click();
    await preview.click({ position: { x: 300, y: 200 } });
    await page.getByRole('button', { name: 'Use for next page' }).click();
    await expect(page.getByLabel('Next page path')).toHaveValue('a.next');
    await page
        .getByText('Advanced CSS selectors', { exact: true })
        .first()
        .click();
    await expect(page.getByLabel('Repeated record selector')).toHaveValue(
        'article.product-card',
    );
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            response.url().includes('/collectors/'),
    );
    await page.getByRole('button', { name: 'Save draft' }).click();
    await saved;
    await page.reload();
    await page
        .getByText('Advanced CSS selectors', { exact: true })
        .first()
        .click();
    await expect(page.getByLabel('Repeated record selector')).toHaveValue(
        'article.product-card',
    );
    await page.setViewportSize({ width: 390, height: 844 });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
});

test('collectors can be searched and a queued collection can be stopped', async ({
    page,
}) => {
    const email = await registerPilot(page, 'search-stop');
    const collectorUrl = await createCollector(page);
    await page.goto('/collectors');
    await page.getByLabel('Search your collectors').fill('nothing matches');
    await page.getByLabel('Search your collectors').press('Enter');
    await expect(page.getByText('No collectors found')).toBeVisible();
    await page.getByRole('button', { name: 'Clear' }).click();
    await expect(
        page.getByRole('link', { name: 'Office supplies' }),
    ).toBeVisible();
    await page.goto(`${collectorUrl}/setup`);
    await saveAndPreview(page, email, collectorUrl);
    await page.getByRole('button', { name: 'Use this setup' }).click();
    await page.getByRole('button', { name: 'Collect now' }).click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    await page.getByRole('button', { name: 'Stop collection' }).click();
    await expect(page.getByText('Collection stopped')).toBeVisible();
});

test('manual collector preview, activation, schedule, and results are separate steps', async ({
    page,
}, testInfo) => {
    const email = await registerPilot(page, 'redesign');
    const collectorUrl = await createCollector(page);
    await saveAndPreview(page, email, collectorUrl);
    await page.getByRole('button', { name: 'Use this setup' }).click();
    await expect(
        page.getByRole('button', { name: 'Collect now' }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('recipe.png'),
        fullPage: true,
    });
    await page
        .getByRole('link', { name: 'Schedule', exact: true })
        .first()
        .click();
    await page.getByLabel('Repeat').selectOption('daily');
    await page.getByLabel('Time zone').fill('Europe/Prague');
    await page.getByLabel('Local time').fill('09:30');
    await page.getByRole('button', { name: 'Save schedule' }).click();
    await expect(page.getByText(/Active · Next run/)).toBeVisible();
    await page.getByLabel('Repeat').selectOption('weekly');
    await expect(page.getByLabel('Day of week').locator('option')).toHaveText([
        'Mon',
        'Tue',
        'Wed',
        'Thu',
        'Fri',
        'Sat',
        'Sun',
    ]);
    await page.getByLabel('Day of week').selectOption('3');
    await page.getByRole('button', { name: 'Save schedule' }).click();
    await page.getByRole('button', { name: 'Pause', exact: true }).click();
    await expect(page.getByText(/Paused · Next run/)).toBeVisible();
    await page.getByRole('button', { name: 'Resume', exact: true }).click();
    await expect(page.getByText(/Active · Next run/)).toBeVisible();
    const notifications = page.getByRole('checkbox', {
        name: 'Email me about changes, failures, and recovery',
    });
    await notifications.check();
    await page.reload();
    await expect(notifications).toBeChecked();
    await page.getByRole('link', { name: 'Overview', exact: true }).click();
    await page.getByRole('button', { name: 'Collect now' }).click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    fixture(email, 'complete');
    await page.reload();
    await expect(
        page.getByRole('cell', { name: 'Notebook', exact: true }),
    ).toBeVisible();
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Download CSV', exact: true }).click();
    expect((await downloadPromise).suggestedFilename()).toMatch(
        /^dataset-.*\.csv$/,
    );
    await page.getByText('More download options', { exact: true }).click();
    const jsonDownload = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Download JSON' }).click();
    expect((await jsonDownload).suggestedFilename()).toMatch(
        /^dataset-.*\.json$/,
    );
    await page.getByLabel('Search visible columns').fill('missing notebook');
    await page.getByLabel('Search visible columns').press('Enter');
    await expect(page.getByText('No matching rows')).toBeVisible();
    await page.getByRole('button', { name: 'Clear' }).click();
    await expect(
        page.getByRole('cell', { name: 'Notebook', exact: true }),
    ).toBeVisible();
    await page
        .getByRole('columnheader', { name: 'Price' })
        .getByRole('button')
        .click();
    await expect(
        page.getByRole('columnheader', { name: 'Price' }),
    ).toHaveAttribute('aria-sort', 'ascending');
    await page.getByText('Columns and filters').click();
    await page.getByRole('checkbox', { name: 'Price' }).uncheck();
    await page.getByRole('button', { name: 'Apply changes' }).click();
    await expect(page.getByRole('columnheader', { name: 'Price' })).toHaveCount(
        0,
    );
    await page.screenshot({
        path: testInfo.outputPath('results-desktop.png'),
        fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
});

test('empty, failed, and stopped collections remain understandable on mobile', async ({
    page,
}, testInfo) => {
    const email = await registerPilot(page, 'redesign');
    const collectorUrl = await createCollector(page);
    await saveAndPreview(page, email, collectorUrl);
    await page.getByRole('button', { name: 'Use this setup' }).click();
    await page.setViewportSize({ width: 390, height: 844 });
    fixture(email, 'long');
    await page.reload();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
    for (const [state, title] of [
        ['failed', 'Collection could not finish'],
        ['cancelled', 'Collection stopped'],
        ['empty', 'No data was found'],
        ['queued', 'Your data will appear here'],
        ['running', 'Gathering your data'],
    ]) {
        const id = fixture(email, state);
        await page.goto(`/runs/${id}`);
        await expect(page.getByText(title, { exact: true })).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Download CSV', exact: true }),
        ).toHaveCount(0);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`${state}-mobile.png`),
            fullPage: true,
        });
    }
    const partialId = fixture(email, 'partial');
    await page.goto(`/runs/${partialId}`);
    await expect(
        page.getByRole('cell', { name: 'Notebook', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('Collection incomplete', { exact: false }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Download CSV', exact: true }),
    ).toHaveCount(0);
    await page.goto(collectorUrl);
    await expect(
        page.getByRole('button', { name: 'Collect now' }),
    ).toBeVisible();
});

test('guided draft edits survive step navigation, warn before leaving, and restore after saving', async ({
    page,
}) => {
    await registerPilot(page, 'draft-guard');
    await createCollector(page);
    await page.getByLabel('Column name').fill('product');
    await expect(page.getByRole('status')).toHaveText('Unsaved changes');
    await page.getByRole('button', { name: 'Back', exact: true }).click();
    await expect(page.getByLabel('Source format')).toBeVisible();
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await expect(page.getByLabel('Column name')).toHaveValue('product');
    const dialog = page.waitForEvent('dialog');
    const navigate = page
        .getByRole('link', { name: 'Activity', exact: true })
        .click();
    const prompt = await dialog;
    expect(prompt.message()).toContain('without saving');
    await prompt.dismiss();
    await navigate;
    await expect(page).toHaveURL(/\/setup$/);
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Draft saved');
    await page.reload();
    await expect(page.getByLabel('Column name')).toHaveValue('product');
});

test('preview activation is explicit and an edited draft cannot reuse a stale preview', async ({
    page,
}) => {
    const email = await registerPilot(page, 'preview-state');
    const collectorUrl = await createCollector(page);
    await saveAndPreview(page, email, collectorUrl);
    await page.getByRole('link', { name: 'View sample', exact: true }).click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    const sampleUrl = page.url();
    await expect(
        page.getByRole('button', { name: 'Use this setup', exact: true }),
    ).toBeVisible();
    await page
        .getByRole('link', { name: 'Edit selection', exact: true })
        .click();
    await page.getByLabel('Column name').fill('renamed');
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Draft saved');
    await page.goto(sampleUrl);
    await expect(
        page.getByText('Your draft has changed since this preview.', {
            exact: false,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Use this setup', exact: true }),
    ).toHaveCount(0);
    await page.goto(collectorUrl);
    await expect(
        page.getByRole('button', { name: 'Collect now', exact: true }),
    ).toHaveCount(0);
});

test('pastel workspace stays usable across screen sizes and top navigation stays visible', async ({
    page,
}, testInfo) => {
    await registerPilot(page, 'responsive');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect(page.locator('.button').first()).toHaveCSS(
        'transition-duration',
        '0s',
    );
    for (const width of [390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await expect(
            page.getByRole('link', {
                name: 'Create your first collector',
                exact: true,
            }),
        ).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`home-${width}.png`),
            fullPage: true,
        });
    }
    await page.setViewportSize({ width: 390, height: 844 });
    const navigation = page.getByRole('navigation', {
        name: 'Main navigation',
        exact: true,
    });
    await expect(
        navigation.getByRole('link', { name: 'Data collectors', exact: true }),
    ).toBeVisible();
    await expect(
        navigation.getByRole('link', { name: 'Activity', exact: true }),
    ).toBeVisible();
    const menu = page.getByRole('button', { name: 'Account', exact: true });
    await menu.click();
    await expect(menu).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('link', { name: 'Settings', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('button', { name: 'Log out', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
    await expect(menu).toBeFocused();
    await menu.click();
    await page
        .getByRole('heading', {
            name: 'Meet your first collector',
            exact: true,
        })
        .click();
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
    await page
        .getByRole('link', { name: 'Create your first collector', exact: true })
        .click();
    await page.getByLabel('Source URL').fill('https://www.example.com/catalog');
    await expect(page.getByLabel('Collector name')).toHaveValue('example.com');
    await page.getByLabel('Collector name').fill('My own name');
    await page
        .getByLabel('Source URL')
        .fill('https://another.example.com/catalog');
    await expect(page.getByLabel('Collector name')).toHaveValue('My own name');
    for (const width of [390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({
            path: testInfo.outputPath(`create-${width}.png`),
            fullPage: true,
        });
    }
});

test('a queued preview becomes activatable without reloading and activation does not start a collection', async ({
    page,
}) => {
    const email = await registerPilot(page, 'preview-live');
    await createCollector(page);
    await page
        .getByRole('button', { name: 'Preview data', exact: true })
        .click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    await expect(
        page.getByRole('button', { name: 'Use this setup', exact: true }),
    ).toHaveCount(0);
    fixture(email, 'sample');
    await expect(
        page.getByRole('button', { name: 'Use this setup', exact: true }),
    ).toBeVisible({ timeout: 15000 });
    await page
        .getByRole('button', { name: 'Use this setup', exact: true })
        .click();
    await page.waitForURL(/\/collectors\/\d+$/);
    await expect(
        page.getByRole('heading', {
            name: 'Your collector is ready!',
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Collect now', exact: true }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'History', exact: true }).click();
    await expect(
        page.getByText('Full collection', { exact: true }),
    ).toHaveCount(0);
});

test('Czech and Slovak workspaces preserve layout and readable navigation', async ({
    page,
}, testInfo) => {
    await registerPilot(page, 'locale-layout');
    for (const locale of ['cs', 'sk']) {
        await page.goto('/settings');
        await page.locator('#locale').selectOption(locale);
        await page
            .locator('form')
            .filter({ has: page.locator('#email') })
            .getByRole('button', {
                name: /Save profile|Uložit profil|Uložiť profil/,
            })
            .click();
        await page.goto('/collectors');
        for (const width of [390, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            expect(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
            ).toBe(true);
            await expect(page.locator('html')).toHaveAttribute('lang', locale);
            await page.screenshot({
                path: testInfo.outputPath(`${locale}-${width}.png`),
                fullPage: true,
            });
        }
        await page.goto('/collectors/create');
        await page.locator('#start_url').fill('https://example.com/catalog');
        for (const width of [390, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.evaluate(() => window.scrollTo(0, 0));
            expect(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
            ).toBe(true);
            await page.screenshot({
                path: testInfo.outputPath(`${locale}-source-${width}.png`),
                fullPage: true,
            });
        }
        await page.locator('form button[type="submit"]').click();
        await page.waitForURL(/\/collectors\/\d+\/setup$/);
        for (const width of [390, 768, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            expect(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
            ).toBe(true);
            await page.screenshot({
                path: testInfo.outputPath(`${locale}-builder-${width}.png`),
                fullPage: true,
            });
        }
    }
});

test('save errors reveal the source step and preserve the entered draft', async ({
    page,
}, testInfo) => {
    await registerPilot(page, 'save-error');
    await createCollector(page);
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await page.getByLabel('Source URL').fill('not-a-url');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('URL');
    await expect(page.getByLabel('Source URL')).toBeVisible();
    await expect(page.getByLabel('Source URL')).toHaveValue('not-a-url');
    await expect(page.getByRole('status')).toContainText('Could not save');
    await page.getByLabel('Source URL').fill('https://example.com/fixed');
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Draft saved');
    await page.reload();
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await expect(page.getByLabel('Source URL')).toHaveValue(
        'https://example.com/fixed',
    );
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    for (const width of [390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`builder-${width}.png`),
            fullPage: true,
        });
    }
    await page.goto('/collectors');
    for (const width of [390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: testInfo.outputPath(`collectors-${width}.png`),
            fullPage: true,
        });
    }
});

test('a failed preview can be corrected with sample selection and tested again', async ({
    page,
}) => {
    const email = await registerPilot(page, 'preview-retry');
    await createCollector(page);
    await page
        .getByRole('button', { name: 'Preview data', exact: true })
        .click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    fixture(email, 'preview_failed');
    await page.reload();
    await expect(page.getByRole('status')).toContainText('Needs attention');
    await expect(
        page.getByRole('button', { name: 'Use this setup', exact: true }),
    ).toHaveCount(0);
    await page
        .getByRole('link', { name: 'Edit selection', exact: true })
        .first()
        .click();
    await page.route('**/collectors/*/sample', (route) =>
        route.fulfill({ json: { sample: [{ title: 'Notebook' }] } }),
    );
    await page
        .getByRole('button', { name: 'Load sample', exact: true })
        .click();
    await page
        .getByRole('checkbox', { name: 'Keep title column', exact: true })
        .check();
    await page
        .getByRole('button', { name: 'Preview data', exact: true })
        .click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    fixture(email, 'sample');
    await expect(
        page.getByRole('cell', { name: 'Notebook', exact: true }),
    ).toBeVisible({ timeout: 15000 });
    await expect(
        page.getByRole('button', { name: 'Use this setup', exact: true }),
    ).toBeVisible();
});

test('changing the source clears the previous sample before choosing new data', async ({
    page,
}) => {
    await registerPilot(page, 'sample-source');
    await createCollector(page);
    await page.route('**/collectors/*/sample', (route) =>
        route.fulfill({ json: { sample: [{ name: 'Original source' }] } }),
    );
    await page
        .getByRole('button', { name: 'Load sample', exact: true })
        .click();
    await expect(
        page.getByRole('cell', { name: 'Original source', exact: true }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await page
        .getByLabel('Source URL')
        .fill('https://example.com/another-source');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await expect(
        page.getByRole('cell', { name: 'Original source', exact: true }),
    ).toHaveCount(0);
    await expect(
        page.getByText('Let’s take a look at your data', { exact: true }),
    ).toBeVisible();
});

test('unsaved setup survives cancelling browser Back and logout', async ({
    page,
}) => {
    await registerPilot(page, 'history-draft');
    await createCollector(page);
    const setupUrl = page.url();
    await page.getByLabel('Column name').fill('unsaved_column');
    const backDialog = page.waitForEvent('dialog');
    await page.evaluate(() => window.history.back());
    await (await backDialog).dismiss();
    await expect(page).toHaveURL(setupUrl);
    await expect(page.getByLabel('Column name')).toHaveValue('unsaved_column');
    await page.getByRole('button', { name: 'Account', exact: true }).click();
    const logoutDialog = page.waitForEvent('dialog');
    const logout = page
        .getByRole('button', { name: 'Log out', exact: true })
        .click();
    await (await logoutDialog).dismiss();
    await logout;
    await expect(
        page.getByRole('button', { name: 'Log out', exact: true }),
    ).toBeEnabled();
    await expect(page).toHaveURL(setupUrl);
    await expect(page.getByLabel('Column name')).toHaveValue('unsaved_column');
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(page.getByRole('status')).toHaveText('Draft saved');
    await page.goBack();
    await expect(page).not.toHaveURL(setupUrl);
});

test('setup steps retain edits and preview links return to the correct step', async ({
    page,
}) => {
    const email = await registerPilot(page, 'step-navigation');
    const collectorUrl = await createCollector(page);
    await page.getByLabel('Column name').fill('Product');
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await page.getByLabel('Source URL').fill('https://example.com/catalog');
    await page
        .getByRole('button', { name: 'Choose data', exact: true })
        .click();
    await expect(page.getByLabel('Column name')).toHaveValue('Product');
    await expect(
        page.getByRole('button', { name: 'Preview', exact: true }),
    ).toHaveCount(0);
    await page.getByRole('button', { name: 'Source', exact: true }).click();
    await expect(page.getByLabel('Source URL')).toHaveValue(
        'https://example.com/catalog',
    );
    await page
        .getByRole('button', { name: 'Choose data', exact: true })
        .click();
    await saveAndPreview(page, email, collectorUrl);
    await page.getByRole('link', { name: 'View sample', exact: true }).click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    const previewUrl = page.url();
    await page.getByRole('link', { name: 'Source', exact: true }).click();
    await expect(page.getByLabel('Source URL')).toBeVisible();
    await expect(page.getByLabel('Source URL')).toHaveValue(
        'https://example.com/catalog',
    );
    await page.goto(previewUrl);
    await page.getByRole('link', { name: 'Choose data', exact: true }).click();
    await expect(page.getByLabel('Column name')).toBeVisible();
    await expect(page.getByLabel('Column name')).toHaveValue('Product');
});
