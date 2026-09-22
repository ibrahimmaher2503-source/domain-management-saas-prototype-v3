# Domain Management SaaS

The repository now contains the production Laravel + Inertia + React + TypeScript foundation at its root. The original UI prototype is preserved under `prototype/` and is reference-only until migration is approved.

## Production foundation

```bash
composer install
npm install
npm run build
php artisan serve
```

Authentication is provided by Laravel Breeze. MySQL/MariaDB-backed queue, session, and cache settings are documented in `.env.example`; Redis is not required.

## Prototype reference

This package contains two prototype forms of the approved domain registrar SaaS customer UI:

1. **`prototype/static/` zero-install prototype** — the most complete interactive version.
2. **`prototype/react/` React + Vite handoff** — componentized React reference implementation.
3. **`prototype/standalone/` standalone HTML reference**.

## Quick preview

```bash
cd prototype/static
# serve with any static server
```

The root prototype requires no package installation beyond the included static server command available in this environment. Any ordinary static server works.

## React version

```bash
cd prototype/react
npm install
npm run dev
```

## Implemented product surfaces

- Persistent SaaS app shell and customer navigation.
- Overview dashboard with portfolio metrics and Action Required state.
- Domains portfolio with lifecycle status separate from Transfer Lock.
- Domain Detail control center with tabs:
  - Overview
  - Renewal
  - Contacts
  - Nameservers
  - Privacy
  - Security
  - Transfers
  - SSL
  - Billing
  - Activity
- Capability-gated quick actions.
- Renewal management and payment failure UX.
- Registrant vs Change Registrant distinction.
- Nameservers and Registered Hosts / child nameservers.
- DNS Zone shown as externally managed (no DNS CRUD added).
- Privacy / ID Shield management.
- Security + re-authentication flow before auth-code reveal.
- Domain-scoped and global Transfers.
- Domain-scoped and global SSL certificate views.
- Billing and settings.
- Domain search and asynchronous registration messaging.
- Command palette in the zero-install prototype (`Cmd/Ctrl + K`).
- Responsive CSS for tablet/mobile.


## User dashboard v2 polish

The customer Overview dashboard is now treated as a portfolio cockpit rather than a generic metrics page. It includes:

- Portfolio health summary with healthy / processing / attention segmentation.
- Derived "Needs attention" list ordered by severity.
- Upcoming renewals sorted by expiration date.
- Auto-renew coverage and active transfer summary.
- Quick actions for buying, transferring, and billing.
- Recent domains and recent activity in the same control surface.
- Responsive layouts for desktop, tablet, and mobile.


## Customer UI v3 polish

The customer domain-management surfaces now use the same control-center quality level as the Overview dashboard:

- Domains portfolio summary with combined search and lifecycle filters.
- Rich portfolio rows separating lifecycle, renewal, Transfer Lock, Privacy, and SSL context.
- Domain Detail hero with domain health, expiration, auto-renew, and security KPIs.
- Action Required state placed above domain controls.
- Renewal builder with TLD-supported periods and dynamic customer renewal quote.
- Security posture surface with Transfer Lock, protected authorization code, and account protection.
- Domain-level transfer readiness, checklist, progress timeline, and cancel flow.
- Global Transfers portfolio view with processing/action-required/completed states.
- Dashboard deep links now open the requested Domain Detail tab.
- Selected multi-year renewal period is applied to the resulting expiration date.

## Product constraints preserved

- Unsupported TLD/provider capabilities are hidden instead of rendered as available actions.
- Transfer Lock is a security state, not a lifecycle state.
- Authorization codes are revealed only after a re-authentication step.
- Ambiguous registrar writes are presented as processing/reconciliation, not blind retry.
- Customer UI shows customer prices, not provider cost.
- DNS zone CRUD/DNSSEC/proxy/import/export are not represented as supported v1 features.
- SSL is modeled as a certificate product lifecycle, not generic website HTTPS monitoring.

## Tests

The production frontend uses Vitest. The preserved prototype logic is covered by Node's built-in test runner:

```bash
node --test prototype/tests/*.test.mjs
```
