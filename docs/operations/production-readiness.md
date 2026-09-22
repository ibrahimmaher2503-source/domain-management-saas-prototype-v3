# Production readiness

This application is a Laravel + Inertia/React domain-management service. V1 resources belong directly to the authenticated user. OnlineNIC, Paymob, and Cloudflare are server-side integrations; browser code never calls them.

## Required runtime

- PHP 8.3+, Composer dependencies installed with `--no-dev` for production.
- MySQL or MariaDB with the PHP `pdo_mysql` extension. Run migrations with `php artisan migrate --force`.
- Database-backed cache, sessions, and queues. Redis is not required.
- A long-running queue worker and one scheduler process/cron entry.
- TLS terminated by a trusted proxy or the web server. Set `APP_URL` to `https://...` and list only real proxy addresses in `TRUSTED_PROXIES`.

Copy `.env.production.example` into the secret manager/template used by the deployment. Fill secrets at deploy time; do not commit a populated `.env`.

## Preflight and health

Run `php artisan app:preflight` before enabling traffic. It is read-only and exits non-zero for missing keys, debug mode, non-HTTPS production configuration, database failure, or non-persistent production drivers. Provider credentials are reported as warnings because preflight never makes external provider calls.

`GET /health/ready` is the load-balancer readiness probe. It checks the application and database only, returning 200 when ready and 503 otherwise. It does not prove OnlineNIC, Paymob, or Cloudflare availability.

## Queue and scheduler

Workers must run `php artisan queue:work database --sleep=3 --tries=1 --timeout=160 --max-time=3600`. The worker timeout is longer than every job timeout (30–150 seconds) and `stopwaitsecs` must be at least 190 seconds. Failed jobs are retained in `failed_jobs`; admin operations expose only the count and safe status metadata.

On cPanel without a process supervisor, run a non-overlapping worker from cron every minute: `flock -n /tmp/domain-saas-queue.lock php /absolute/path/artisan queue:work database --stop-when-empty --tries=1 --timeout=160 --max-jobs=50`. Replace the path with the real private application path. Do not place the application root under `public_html`; expose only its `public/` directory.

Run the scheduler every minute:

```text
* * * * * cd /srv/domain-management && php artisan schedule:run >> /dev/null 2>&1
```

The unresolved-operation sweep runs every five minutes, is bounded to 50 records, dispatches read-only reconciliation jobs, and uses a shared lock (`withoutOverlapping`/`onOneServer`). It must not be replaced by a write/retry loop.

## Deploy and rollback

1. Put the application in maintenance mode only if the release changes require it.
2. Deploy code and the locked dependency manifests.
3. Run `composer install --no-dev --optimize-autoloader`, `npm ci`, and `npm run build` in the build/release environment.
4. Run `php artisan migrate --force`, `php artisan storage:link` (safe if already present), `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
5. Run `php artisan app:preflight`, then restart workers with `php artisan queue:restart` and reload PHP-FPM/web workers.
6. Probe `/health/ready`, then remove maintenance mode.

Rollback is code-first: switch the release symlink/image back, clear/rebuild cached configuration if environment keys changed, restart workers, and keep the database at the newest compatible schema. Never reverse a migration or restore production data as an ad-hoc rollback without an approved backup restore plan.

## Security and observability

Production must use `APP_DEBUG=false`, secure/encrypted HttpOnly cookies, SameSite=Lax, security headers, and the exact POST Paymob callback route with its size limit. Paymob callback logs contain only outcome and safe provider/order identifiers after HMAC verification; provider credentials, HMAC payloads, client secrets, and raw external responses are not logged. Review application logs, queue failures, readiness failures, and admin unresolved-operation counts on every release.

The Nginx example denies dotfiles and sensitive directories/files. Keep `storage/app` private; expose only `public/` and the intended public storage symlink. Do not serve `.env`, logs, SQL dumps, SQLite files, tests, or configuration files.

## Secret and release gates

Run `php scripts/check-committed-secrets.php` against the staged tree and inspect the diff before publication. The check is a guard, not proof that a secret never existed in history; if a credential was ever committed, rotate it and inspect repository history before release.

The CI workflow runs PHP tests, frontend tests, the production build, browser tests, prototype tests, a diff check, and the committed-secret scan. CI uses no real provider credentials.
