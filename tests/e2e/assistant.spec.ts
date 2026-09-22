import { expect, test } from '@playwright/test';
import { registerPilot } from './pilot';

test.describe('Scraping recipe workspace', () => {
    test('user can create and review a recipe definition', async ({ page }) => {
        await registerPilot(page, 'recipes');
        await page.getByRole('link', { name: 'New collector' }).click();
        await page
            .getByLabel('Website address')
            .fill('https://example.com/products');
        await page.getByRole('button', { name: 'Next', exact: true }).click();
        await page
            .getByLabel('Data to collect')
            .fill('Extract the public name and price from every product card.');
        await page.getByRole('button', { name: 'Next', exact: true }).click();
        await page.getByLabel('Collector name').fill('Public product catalog');
        await page.getByRole('button', { name: 'Save collector' }).click();

        await page.waitForURL(/\/recipes\/\d+\/setup$/);
        await expect(
            page.getByRole('heading', { name: 'Build your collector' }),
        ).toBeVisible();
        await expect(page.getByLabel('Source format')).toBeVisible();
    });

    test('mobile workspace exposes recipes runs and settings', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await registerPilot(page, 'recipes');
        await expect(
            page.getByRole('link', { name: 'Data collectors' }),
        ).toBeVisible();
        await expect(page.getByRole('link', { name: 'Results' })).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Settings' }),
        ).toBeVisible();
    });
});
