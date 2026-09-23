import { expect, test, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { registerPilot } from './pilot';

async function inspectSource(page: Page, x: number, y: number): Promise<void> {
    const preview = page.getByRole('button', {
        name: 'Inspect an item in the page preview',
    });
    await expect(preview).toBeEnabled();
    const bounds = await preview.boundingBox();
    if (!bounds) throw new Error('Source screenshot is missing.');
    await preview.click({
        position: {
            x: (bounds.width * x) / 1280,
            y: (bounds.height * y) / 800,
        },
    });
}

test('saved login, visual fields, detail pages and pagination produce downloadable website rows', async ({
    page,
}) => {
    test.setTimeout(90000);
    await registerPilot(page, 'website-workflow');
    await page
        .getByRole('link', { name: 'New collector', exact: true })
        .click();
    await page.getByLabel('Collector name').fill('Signed-in catalog');
    await page.getByLabel('Source URL').fill('https://1.1.1.1/e2e/website');
    await page
        .getByRole('button', { name: 'Continue to field mapping' })
        .click();
    await page.waitForURL(/\/collectors\/\d+\/setup$/);
    await page.getByRole('button', { name: 'Open page', exact: true }).click();

    const login = page.locator('details').filter({
        has: page.getByText('Login to a source site', { exact: true }),
    });
    await login.locator('summary').first().click();
    for (const [y, value] of [
        [125, 'catalog@example.com'],
        [225, 'catalog-password'],
    ] as const) {
        await login
            .getByRole('button', { name: 'Pick from screenshot' })
            .click();
        await inspectSource(page, 100, y);
        await page
            .getByRole('button', { name: 'Use as login input', exact: true })
            .first()
            .click();
        await login.getByLabel('Credential value').fill(value);
        await login.getByRole('button', { name: 'Type value once' }).click();
        await expect(login.getByLabel('Credential value')).toHaveValue('');
    }
    await login.getByRole('button', { name: 'Submit login' }).click();
    await login.getByRole('button', { name: 'Save signed-in session' }).click();
    await expect(
        page.getByLabel('Saved credentials').locator('option'),
    ).toHaveCount(2);
    await page.getByRole('button', { name: 'Open page', exact: true }).click();
    await page.getByRole('button', { name: '1. Pick a repeated item' }).click();
    await inspectSource(page, 500, 230);
    await page
        .getByRole('button', { name: 'Use as record', exact: true })
        .first()
        .click();
    await expect(page.getByText('2 matching items')).toBeVisible();
    await inspectSource(page, 100, 135);
    await page
        .getByRole('button', { name: 'Add as column', exact: true })
        .first()
        .click();
    await expect(page.getByLabel('Column name').first()).toHaveValue('title');
    await inspectSource(page, 70, 175);
    await page
        .getByRole('button', { name: 'Add as column', exact: true })
        .first()
        .click();
    await page.getByLabel('Data type').last().selectOption('number');

    await page.getByLabel('How to continue').selectOption('next_page');
    await page
        .getByRole('button', { name: 'Pick from screenshot' })
        .last()
        .click();
    await inspectSource(page, 80, 510);
    await page
        .getByRole('button', { name: 'Use for next page', exact: true })
        .first()
        .click();
    await expect(page.getByLabel('Next page path')).toHaveValue('a.next');

    const detail = page.locator('details').filter({
        has: page.getByText('Details-page fields', { exact: true }),
    });
    await detail.locator('summary').first().click();
    await detail
        .getByRole('button', { name: 'Pick from screenshot' })
        .first()
        .click();
    await inspectSource(page, 90, 220);
    await page
        .getByRole('button', { name: 'Use as detail link', exact: true })
        .first()
        .click();
    const screenshot = page.getByRole('img', {
        name: 'Screenshot of the source page',
        exact: true,
    });
    const catalogScreenshot = await screenshot.getAttribute('src');
    await detail
        .getByRole('button', { name: 'Open detail page', exact: true })
        .click();
    await expect(screenshot).not.toHaveAttribute('src', catalogScreenshot!);
    await detail
        .getByRole('button', { name: 'Add field', exact: true })
        .click();
    await detail.getByRole('button', { name: 'Remove', exact: true }).click();
    await expect(detail.getByLabel('Column name')).toHaveCount(0);
    await detail
        .getByRole('button', { name: 'Add field', exact: true })
        .click();
    await detail.getByLabel('Column name').fill('description');
    await detail
        .getByRole('button', { name: 'Pick from screenshot', exact: true })
        .last()
        .click();
    await expect(
        page.getByRole('heading', {
            name: 'Click screenshot for field',
            exact: true,
        }),
    ).toBeVisible();
    await inspectSource(page, 150, 110);
    await page
        .getByRole('button', { name: 'Use for selected field', exact: true })
        .first()
        .click();
    await expect(detail.getByLabel('Source path or selector')).toHaveValue(
        'p.description',
    );

    await page
        .getByRole('button', { name: 'Run test preview', exact: true })
        .click();
    await page.waitForURL(/\/runs\/[0-9a-f-]+$/);
    await expect(page.getByRole('status')).toContainText('Complete');
    await expect(
        page.getByRole('cell', { name: 'Graph paper notebook', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('cell', { name: 'Soft graphite pencil', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('cell', { name: 'White rubber eraser', exact: true }),
    ).toBeVisible();
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Download CSV', exact: true }).click();
    const download = await downloadPromise;
    const path = await download.path();
    if (!path) throw new Error('CSV download is missing.');
    expect(readFileSync(path, 'utf8')).toContain(
        'Eraser,3,"White rubber eraser"',
    );
});
