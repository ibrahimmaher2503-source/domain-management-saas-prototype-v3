import { test, expect } from '@playwright/test';

test('application shell opens', async ({ page }) => { await page.goto('/'); await expect(page).toHaveTitle(/Domain Management SaaS|Laravel/i); });
