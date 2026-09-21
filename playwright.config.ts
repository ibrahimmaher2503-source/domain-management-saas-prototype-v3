import { defineConfig, devices } from '@playwright/test';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const database = join(mkdtempSync(join(tmpdir(), 'mark-playwright-')), 'database.sqlite');
writeFileSync(database, '');

export default defineConfig({ testDir: './tests/browser', use: { baseURL: 'http://127.0.0.1:8001', ...devices['Desktop Chrome'] }, webServer: { command: 'php artisan migrate --force && php artisan serve --host=127.0.0.1 --port=8001', url: 'http://127.0.0.1:8001', reuseExistingServer: false, env: { APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database } } });
