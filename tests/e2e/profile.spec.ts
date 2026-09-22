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
});
