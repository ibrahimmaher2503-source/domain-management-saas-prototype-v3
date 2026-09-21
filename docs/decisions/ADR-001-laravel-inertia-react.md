# ADR-001: Laravel + Inertia + React

## Status

Accepted for the production application.

## Decision

Use Laravel for HTTP, authorization, application/domain orchestration, persistence, queues, and scheduling; use Inertia to deliver server-owned page state to React; use React + TypeScript for the browser UI; use Vite for frontend builds.

## Consequences

This keeps authorization and business rules server-side while preserving a component-based frontend. Inertia is the default state transport, so additional client-state libraries require evidence of need. The existing React/Vite prototype can inform UI migration but is not itself the production application. V1 is a direct user-account product without workspaces, teams, notifications, or cart state.
