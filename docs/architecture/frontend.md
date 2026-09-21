# Frontend Architecture

The intended Inertia React organization is:

```text
resources/js/
  Components/   reusable presentational pieces
  Layouts/      authenticated and workspace shells
  Pages/        route-level screens
  lib/          small UI-only helpers
  types/        shared frontend types
  app.tsx       Inertia bootstrap
```

Inertia owns server-provided application state by default. Components may own ephemeral interaction state, but business rules and authorization remain server-side. Do not add Redux or Zustand unless a demonstrated cross-page client-state need exists. Do not add React Query initially; introduce it only for a real independent server-state use case not covered by Inertia.

The existing React prototype is a visual reference, not a backend-connected frontend. Reuse its information architecture, capability-aware states, and CSS tokens selectively during the production migration.
