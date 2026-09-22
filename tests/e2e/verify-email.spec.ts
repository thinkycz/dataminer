import { expect, test } from '@playwright/test';
import { registerPilot } from './pilot';

test.describe('Email verification', () => {
    test('send verification email shows success flash', async ({ page }) => {
        await registerPilot(page, 'verify');

        await page.goto('/verify-email');
        await page
            .getByRole('button', { name: 'Send verification email' })
            .click();

        await expect(
            page
                .getByRole('alert')
                .filter({ hasText: 'Verification email sent.' })
                .first(),
        ).toBeVisible();
    });

    test('verify-email page is reachable while logged in', async ({ page }) => {
        await registerPilot(page, 'verify');

        await page.goto('/verify-email');
        await expect(
            page.getByRole('heading', { name: 'Verify email' }),
        ).toBeVisible();
    });
});
