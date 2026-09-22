import { expect, test } from '@playwright/test';
import { registerPilot } from './pilot';
import { emailActionLink, latestEmail } from './mailbox';

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

    test('the delivered verification link verifies the signed-in account', async ({
        page,
    }) => {
        const email = await registerPilot(page, 'verify-link');
        await page.getByRole('link', { name: 'Settings', exact: true }).click();
        await page
            .getByRole('link', { name: 'Verify email', exact: true })
            .click();
        await page
            .getByRole('button', { name: 'Send verification email' })
            .click();
        await page.goto(
            emailActionLink(await latestEmail(email), '/email/verify'),
        );
        await expect(page).toHaveURL(/\/collectors$/);
        await expect(
            page.getByRole('alert').filter({ hasText: 'Email verified.' }),
        ).toBeVisible();
        await page.goto('/verify-email');
        await expect(
            page.getByRole('heading', { name: 'Email verified' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Send verification email' }),
        ).toHaveCount(0);
    });
});
