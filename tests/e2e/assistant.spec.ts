import { expect, test } from '@playwright/test';
import { registerPilot } from './pilot';

test.describe('Scraping recipe workspace', () => {
    test('user can create and review a recipe definition', async ({ page }) => {
        await registerPilot(page, 'recipes');
        await page
            .getByRole('link', { name: 'Create your first collector' })
            .click();
        await page
            .getByLabel('Source URL')
            .fill('https://example.com/products');
        await page
            .getByText('Collection notes (optional)', { exact: true })
            .click();
        await page
            .getByLabel('Data to collect')
            .fill('Extract the public name and price from every product card.');
        await page.getByLabel('Collector name').fill('Public product catalog');
        await page.getByRole('button', { name: 'Choose data' }).click();

        await page.waitForURL(/\/collectors\/\d+\/setup$/);
        await expect(
            page.getByRole('heading', { name: 'Public product catalog' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Source', exact: true }).click();
        await expect(page.getByLabel('Source format')).toBeVisible();
    });

    test('mobile workspace exposes recipes runs and settings', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await registerPilot(page, 'recipes');
        await page
            .getByRole('button', { name: 'Account', exact: true })
            .click();
        await expect(
            page.getByRole('link', { name: 'Data collectors' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Activity' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Settings' }),
        ).toBeVisible();
    });
});
