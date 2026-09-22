import type { Page } from '@playwright/test';

export async function registerPilot(
    page: Page,
    prefix: string,
): Promise<string> {
    const email = `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com`;
    await page.route('**/register', async (route) => {
        if (route.request().method() !== 'POST') {
            await route.continue();
            return;
        }
        await route.continue({
            headers: {
                ...route.request().headers(),
                'X-E2E-Pilot-Email': email,
            },
        });
    });
    await page.goto('/register');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password1');
    await page.getByLabel('Confirm password').fill('password1');
    await page.getByLabel('Language').selectOption('en');
    await page.getByRole('button', { name: 'Register', exact: true }).click();
    await page.waitForURL(/\/recipes$/);
    return email;
}
