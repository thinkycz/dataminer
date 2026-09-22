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
        .getByRole('link', { name: 'New collector', exact: true })
        .click();
    await page.getByRole('radio', { name: 'JSON API', exact: true }).check();
    await page.getByLabel('Source URL').fill('https://example.com/products');
    await page.getByLabel('Collector name').fill('Office supplies');
    await page
        .getByRole('button', { name: 'Continue to field mapping' })
        .click();
    await page.waitForURL(/\/collectors\/\d+\/setup$/);
    return page.url().replace(/\/setup$/, '');
}

async function saveAndPreview(
    page: Page,
    email: string,
    collectorUrl: string,
): Promise<void> {
    await page.getByLabel('Source format').selectOption('json');
    await page.getByLabel('Records path').fill('data.items');
    await page.getByLabel('Source path or selector').first().fill('name');
    await page.getByRole('button', { name: 'Run test preview' }).click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    fixture(email, 'sample');
    await page.goto(collectorUrl);
    await expect(
        page.getByRole('button', { name: 'Activate setup' }),
    ).toBeVisible();
}

for (const [source, label] of [
    ['website', 'Website'],
    ['json', 'JSON API'],
    ['csv', 'CSV file'],
    ['xml', 'XML feed'],
]) {
    test(`source selection opens and retains a ${source} builder`, async ({
        page,
    }, testInfo) => {
        await registerPilot(page, 'redesign');
        await page
            .getByRole('link', { name: 'New collector', exact: true })
            .click();
        await expect(page.getByRole('radio')).toHaveCount(4);
        await page
            .getByRole('button', { name: 'Continue to field mapping' })
            .click();
        await expect(page.getByLabel('Collector name')).toBeFocused();
        await page.getByLabel('Collector name').fill('Office supplies');
        await page
            .getByRole('button', { name: 'Continue to field mapping' })
            .click();
        await expect(page.getByLabel('Source URL')).toBeFocused();
        await page
            .getByLabel('Source URL')
            .fill('https://example.com/products');
        await page.getByRole('radio', { name: label, exact: true }).check();
        await page.screenshot({
            path: testInfo.outputPath('setup.png'),
            fullPage: true,
        });
        await page
            .getByRole('button', { name: 'Continue to field mapping' })
            .click();
        await page.waitForURL(/\/collectors\/\d+\/setup$/);
        await expect(
            page.getByRole('heading', { name: 'Build your collector' }),
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
    await page.getByLabel('Source format').selectOption('json');
    await page.getByRole('button', { name: 'Load sample' }).click();
    await expect(page.getByText('Notebook')).toBeVisible();
    await page.getByRole('button', { name: 'Use path' }).first().click();
    await expect(page.getByLabel('Records path')).toHaveValue('data.items');
    await page.getByRole('button', { name: 'Add field' }).click();
    await page.getByLabel('Column name').last().fill('price');
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
    await page.getByLabel('Source format').selectOption('csv');
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
    await page.getByRole('button', { name: 'Product name' }).click();
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

test('saved credentials can be revoked and are removed from setup choices', async ({
    page,
}) => {
    await registerPilot(page, 'credential');
    await createCollector(page);
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
    const format = page.getByLabel('Source format');
    await format.selectOption('csv');
    await expect(page.getByLabel('Delimiter')).toBeVisible();
    await format.selectOption('xml');
    await expect(page.getByLabel('XML namespaces (JSON object)')).toBeVisible();
    await format.selectOption('website');
    await expect(
        page.getByRole('heading', { name: 'Select website content visually' }),
    ).toBeVisible();
    await expect(page.getByLabel('Repeated record selector')).toBeHidden();
    await page
        .getByText('Advanced CSS selectors', { exact: true })
        .first()
        .click();
    await expect(page.getByLabel('Repeated record selector')).toBeVisible();
    await format.selectOption('json');
    await expect(
        page.getByRole('heading', { name: 'Inspect source data' }),
    ).toBeVisible();
});

test('website content can be selected into records and columns without typing selectors', async ({
    page,
}, testInfo) => {
    await registerPilot(page, 'redesign');
    await page
        .getByRole('link', { name: 'New collector', exact: true })
        .click();
    await page.getByLabel('Collector name').fill('Visual catalog');
    await page.getByLabel('Source URL').fill('https://example.com/catalog');
    await page
        .getByRole('button', { name: 'Continue to field mapping' })
        .click();
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
        page.getByRole('button', { name: 'Run test preview' }),
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
    await expect(
        page.getByText('span.price', { exact: true }).last(),
    ).toBeVisible();
    await expect(page.getByLabel('Column name').first()).toHaveValue('price');
    await expect(
        page.getByRole('button', { name: 'Run test preview' }),
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
    await page.getByRole('button', { name: 'Activate setup' }).click();
    await page.getByRole('button', { name: 'Collect data' }).click();
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
    await page.getByRole('button', { name: 'Activate setup' }).click();
    await expect(
        page.getByRole('button', { name: 'Collect data' }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('recipe.png'),
        fullPage: true,
    });
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
    await page.getByRole('button', { name: 'Collect data' }).click();
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
    await page.getByRole('button', { name: 'Activate setup' }).click();
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
    await page.goto(collectorUrl);
    await expect(
        page.getByRole('button', { name: 'Collect data' }),
    ).toBeVisible();
});
