import { expect, test } from '@playwright/test';
import { registerPilot } from './pilot';
import { emailActionLink, latestEmail } from './mailbox';

test.describe('Password reset flow', () => {
    test('a password reset email leads to a usable new password', async ({
        page,
    }) => {
        const email = await registerPilot(page, 'reset');
        await page.getByRole('button', { name: 'Log out' }).click();
        await page.goto('/forgot-password');
        await page.getByLabel('Email').fill(email);
        await page.getByRole('button', { name: 'Send reset link' }).click();
        const mail = await latestEmail(email);
        const resetLink = emailActionLink(mail, '/reset-password');
        await page.goto(resetLink);
        await expect(page.getByLabel('Token')).toHaveCount(0);
        await page.getByLabel('New password').fill('reset-password1');
        await page.getByRole('button', { name: 'Update password' }).click();
        await expect(page).toHaveURL(/\/collectors$/);
        await page.getByRole('button', { name: 'Log out' }).click();
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password').fill('reset-password1');
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(
            page.getByRole('heading', { name: 'Data collectors' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Log out' }).click();
        await page.goto(resetLink);
        await page.getByLabel('New password').fill('another-password1');
        await page.getByRole('button', { name: 'Update password' }).click();
        await expect(
            page
                .getByRole('alert')
                .filter({ hasText: /token.*invalid|invalid.*token/i })
                .first(),
        ).toBeVisible();
    });

    test('forgot password shows validation error for unknown email', async ({
        page,
    }) => {
        await page.goto('/forgot-password');

        await page.getByLabel('Email').fill('nobody@example.com');
        await page.getByRole('button', { name: 'Send reset link' }).click();

        await expect(
            page
                .getByRole('alert')
                .filter({ hasText: /unable|user/i })
                .first(),
        ).toBeVisible();
    });

    test('reset password page carries the link token without asking users to enter it', async ({
        page,
    }) => {
        await page.goto(
            '/reset-password?email=foo%40example.com&token=sometoken',
        );

        await expect(page).toHaveTitle(/Reset password/);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.getByLabel('Token')).toHaveCount(0);
        await expect(page.getByLabel('New password')).toBeVisible();
    });
});
