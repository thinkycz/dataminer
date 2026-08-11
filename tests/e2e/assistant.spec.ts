import { expect, test, type Page } from '@playwright/test';

async function register(page: Page) {
    const email = `recipes-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com`;
    await page.goto('/register');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password1');
    await page.getByLabel('Confirm password').fill('password1');
    await page.getByLabel('Locale').selectOption('en');
    await page.getByRole('button', { name: 'Register' }).click();
    await page.waitForURL(/\/recipes/);
}

test.describe('Scraping recipe workspace', () => {
    test('user can create and review a recipe definition', async ({ page }) => {
        await register(page);
        await page.getByRole('link', { name: 'New recipe' }).click();
        await page.getByLabel('Name').fill('Public product catalog');
        await page.getByLabel('Start URL').fill('https://example.com/products');
        await page
            .getByLabel('Extraction instructions')
            .fill('Extract the public name and price from every product card.');
        await page.getByRole('button', { name: 'Create recipe' }).click();

        await page.waitForURL(/\/recipes\/\d+$/);
        await expect(
            page.getByRole('heading', { name: 'Public product catalog' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Generate candidate' }),
        ).toBeVisible();
    });

    test('mobile workspace exposes recipes runs and settings', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await register(page);
        await expect(page.getByRole('link', { name: 'Recipes' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Runs' })).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Settings' }),
        ).toBeVisible();
    });
});
