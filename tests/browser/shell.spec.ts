import { test, expect } from '@playwright/test';

test('customer can navigate the migrated domain control center', async ({ page, context }) => {
    const email = `customer-${Date.now()}@example.com`;
    await page.goto('/register');
    await page.getByLabel('Name').fill('Prototype Customer');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password123');
    await page.getByLabel('Confirm Password').fill('password123');
    await page.getByRole('button', { name: 'Register' }).click();
    await expect(page.getByRole('main').getByRole('heading', { name: 'Overview' })).toBeVisible();

    await context.clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('password123');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page.getByRole('main').getByRole('heading', { name: 'Overview' })).toBeVisible();
    await page.getByRole('link', { name: 'Domains' }).first().click();
    await page.getByRole('link', { name: 'example.com', exact: true }).click();
    await expect(page.getByRole('main').getByRole('heading', { name: 'example.com' })).toBeVisible();
    for (const tab of ['Nameservers', 'Security', 'Activity']) {
        await page.getByRole('button', { name: tab, exact: true }).click();
        await expect(page.getByRole('button', { name: tab, exact: true })).toHaveClass(/border-\[#171717\]/);
    }
});
