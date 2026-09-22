# Architecture Overview

## Request path

```text
Browser
  -> Laravel + Inertia
  -> Application / Domain layer
  -> RegistrarGateway
  -> OnlineNicGateway
  -> OnlineNIC API
```

The browser owns presentation and user intent only. Laravel owns authentication, authorization, validation, orchestration, persistence, and Inertia responses. The application/domain layer expresses product actions in provider-neutral terms. `RegistrarGateway` is the provider-neutral boundary. `OnlineNicGateway` translates that boundary to OnlineNIC protocol details. In v1, customer resources belong directly to the authenticated user.

## Platform services

```text
Laravel -> MySQL / MariaDB
        -> Database-backed cache, sessions, and queues
        -> Queue workers
        -> Scheduler
```

MySQL or MariaDB is the system of record for platform, user, domain, pricing, order, operation, audit, cache, session, and queue data. Queue workers handle long-running or asynchronous provider work. The scheduler runs bounded reconciliation and renewal jobs; it does not bypass authorization or operation records.

## Ownership

- Frontend: rendering, accessible interaction, local view state, and Inertia navigation.
- Controllers: HTTP boundary, authorization entry point, request mapping, and response shaping.
- Application actions: use-case orchestration and transaction boundaries.
- Domain services: provider-neutral business rules and capability decisions.
- Gateway contracts: stable provider-neutral operations and result types.
- Integrations: OnlineNIC protocol translation, credentials, transport, and provider error mapping.
- Models/repositories: persistence and query boundaries; no provider protocol code.
