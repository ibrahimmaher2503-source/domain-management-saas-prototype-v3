import { test, expect } from '@playwright/test';

test('customer sees their real empty domain portfolio', async ({ page, context }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const email = `customer-${Date.now()}@example.com`;
    await page.goto('/register');
    await page.getByLabel('Name').fill('Prototype Customer');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password123');
    await page.getByLabel('Confirm Password').fill('password123');
    await page.getByRole('button', { name: 'Register' }).click();
    await expect(page.getByRole('main').getByRole('heading', { name: 'Overview' })).toBeVisible();
    await expect.poll(() => page.evaluate(() => getComputedStyle(document.body).fontFamily)).toContain('Geist');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

    await context.clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('password123');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page.getByRole('main').getByRole('heading', { name: 'Overview' })).toBeVisible();
    await page.getByRole('link', { name: 'Domains' }).first().click();
    await expect(page.getByRole('heading', { name: 'No domains yet' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Search domain' })).toBeVisible();
});
