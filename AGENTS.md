# Project Working Rules

- Production app: Laravel + Inertia + React + TypeScript in the repository root.
- Prototype reference: `prototype/`; do not rewrite or migrate it without explicit approval.
- V1 resources belong directly to users. Do not add workspaces, teams, members, notifications, or cart.
- Keep controllers thin and business rules out of React.
- OnlineNIC belongs behind the future registrar gateway; never call it from the browser.
- Run `php artisan test`, `npm test`, and `npm run build` before handoff.
