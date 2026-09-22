import { expect, test } from '@playwright/test';
import { registerPilot } from './pilot';

test.describe('Locale switcher', () => {
    test.beforeEach(async ({ page }) => {
        await registerPilot(page, 'locale');
    });

    test('switching the locale flips the nav and heading strings', async ({
        page,
    }) => {
        await expect(
            page.getByRole('heading', { name: 'Data collectors' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();

        await page.goto('/settings');

        const switcher = page.locator('select#locale');
        await switcher.selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await expect(
            page.getByRole('heading', { name: 'Nastavení' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Odhlásit se' }),
        ).toBeVisible();

        await switcher.selectOption('sk');
        await page.getByRole('button', { name: 'Uložit profil' }).click();

        await expect(
            page.getByRole('heading', { name: 'Nastavenia' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Odhlásiť sa' }),
        ).toBeVisible();

        await switcher.selectOption('en');
        await page.getByRole('button', { name: 'Uložiť profil' }).click();

        await expect(
            page.getByRole('heading', { name: 'Settings' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Log out' }),
        ).toBeVisible();
    });

    test('navigating to settings shows the localized page title', async ({
        page,
    }) => {
        await page.goto('/settings');

        const switcher = page.locator('select#locale');
        await switcher.selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await page
            .getByRole('link', { name: 'Sběrače dat', exact: true })
            .click();
        await page.waitForURL(/\/recipes$/);
        await page
            .getByRole('link', { name: 'Nastavení', exact: true })
            .click();
        await page.waitForURL(/\/settings$/);

        await expect(
            page.getByRole('heading', { name: 'Nastavení' }),
        ).toBeVisible();
    });
});
