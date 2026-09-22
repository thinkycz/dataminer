import { expect, test } from '@playwright/test';
import { registerPilot } from './pilot';

test.describe('Full user journey', () => {
    test('user can register, view recipes, update profile, and log out', async ({
        page,
    }) => {
        await registerPilot(page, 'e2e');
        await expect(
            page.getByRole('heading', { name: 'Data collectors' }),
        ).toBeVisible();

        await page.goto('/settings');
        const emailInput = page.getByLabel('Email');
        await emailInput.fill(`e2e-updated-${Date.now()}@example.com`);
        await page.getByRole('button', { name: 'Save profile' }).click();

        await page.waitForURL(/\/settings$/);

        await page.getByRole('button', { name: 'Log out' }).click();
        await page.waitForURL(/\/login|\/$/);
    });

    test('unknown user cannot enter the collector workspace', async ({
        page,
    }) => {
        await page.goto('/login');

        await page.getByLabel('Email').fill('unknown-e2e@example.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();

        await expect(page.getByRole('alert').first()).toBeVisible();
        await expect(page).toHaveURL(/\/login$/);
        await page.goto('/collectors');
        await expect(
            page.getByRole('heading', { name: 'Log in' }),
        ).toBeVisible();
    });
});
