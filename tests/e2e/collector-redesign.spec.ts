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
            selector?: string;
        };
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
                  ? {
                        candidates:
                            request.selector === 'price'
                                ? [
                                      {
                                          selector: 'span.price',
                                          tag: 'span',
                                          text: '$12',
                                          count: 2,
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
                                  ],
                    }
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
    await expect(
        page.getByRole('button', { name: 'Run test preview' }),
    ).toBeEnabled();
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
