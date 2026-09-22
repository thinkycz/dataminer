import { expect, test } from '@playwright/test';
import { registerPilot } from './pilot';

test.describe('Profile management', () => {
    test.beforeEach(async ({ page }) => {
        await registerPilot(page, 'profile');
    });

    test('user can change locale and see the translated settings page', async ({
        page,
    }) => {
        await page.goto('/settings');

        await page.getByLabel('Language').selectOption('cs');
        await page.getByRole('button', { name: 'Save profile' }).click();

        await expect(page).toHaveURL(/\/settings$/);
        await expect(
            page.getByRole('heading', { name: 'Nastavení' }),
        ).toBeVisible();
        await expect(page.getByLabel('Jazyk')).toHaveValue('cs');
        await expect(
            page
                .getByRole('alert')
                .filter({ hasText: /Profile updated|Profil byl aktualizován/ }),
        ).toBeVisible();
    });

    test('user can change password and log in with the new password', async ({
        page,
    }) => {
        await page.goto('/settings');
        const accountEmail = await page.getByLabel('Email').inputValue();
        await page.getByLabel('Current password').fill('password1');
        await page.getByLabel('New password').fill('new-password1');
        await page.getByRole('button', { name: 'Update password' }).click();
        await page.waitForURL(/\/login$/);
        await expect(page.getByRole('alert')).toContainText(
            'Password updated.',
        );
        await page.getByLabel('Email').fill(accountEmail);
        await page.getByLabel('Password').fill('new-password1');
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(
            page.getByRole('heading', { name: 'Data collectors' }),
        ).toBeVisible();
    });
});
