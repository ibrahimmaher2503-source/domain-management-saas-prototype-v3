# Prototype to Production Migration Map

## Primary source

`prototype/react/` was used as the visual source. Its information architecture, capability-aware status language, table density, card layout, responsive overflow, and domain control-center tabs were carried forward in TypeScript.

## Production mapping

| Prototype element | Production destination | Classification |
| --- | --- | --- |
| App shell/sidebar/topbar | `resources/js/Layouts/AppLayout.tsx`, `Components/AppSidebar.tsx`, `Components/AppTopbar.tsx` | Production layout |
| Page headings and status badges | `Components/PageHeader.tsx`, `Components/StatusBadge.tsx` | Reusable components |
| Overview dashboard | `Pages/Overview/Index.tsx` | Production page with mock data |
| Domains portfolio | `Pages/Domains/Index.tsx` | Production page with typed mock data |
| Domain Control Center | `Pages/Domains/Show.tsx` and `Components/Domain/*` | Production page/components with mock handlers |
| Transfers portfolio | `Pages/Transfers/Index.tsx` | Production page with mock data |
| SSL portfolio | `Pages/SSL/Index.tsx` | Production page with mock data |
| Billing presentation | `Pages/Billing/Index.tsx` | Production page with mock data |
| Account settings | `Pages/Settings/Index.tsx` | Production page |
| Domain/transfer/certificate/billing arrays | `resources/js/mocks/` | Temporary mock data |
| Capability and reconciliation helpers | `prototype/static/src/core.js` | Business-rule reference only |
| Existing CSS variables and prototype HTML | `prototype/static/`, `prototype/standalone/` | Reference-only implementation |

The standalone and static implementations were not copied into production. No domain model, provider client, mutation endpoint, checkout, or payment logic was migrated.
