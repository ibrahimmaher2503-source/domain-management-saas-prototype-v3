# Backend Architecture

```text
Controllers -> Application Actions -> Domain Services -> Gateway contracts -> Infrastructure integrations
```

Controllers stay thin. They authorize the workspace, validate input through request objects, call one application action, and return an Inertia response or redirect with safe status information.

Application actions coordinate a use case, persistence, operation records, and dispatching. Domain services hold provider-neutral rules such as TLD capability checks, pricing separation, and transfer readiness. Gateway contracts define registrar intent without OnlineNIC names. Infrastructure integrations implement those contracts and translate OnlineNIC responses into normalized results and explicit ambiguous/reconciliation states.

OnlineNIC XML/socket/protocol code must not appear in controllers, models, React components, or domain actions. Provider credentials stay in configuration/secret storage and are never logged.
