import { defineConfig, devices } from '@playwright/test';

export default defineConfig({ testDir: './tests/browser', use: { baseURL: 'http://127.0.0.1:8000', ...devices['Desktop Chrome'] }, webServer: { command: 'php artisan migrate:fresh --force && php artisan serve --host=127.0.0.1 --port=8000', url: 'http://127.0.0.1:8000', reuseExistingServer: true } });
