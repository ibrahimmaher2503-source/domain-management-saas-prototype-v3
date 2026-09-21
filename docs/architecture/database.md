# Database Architecture

No migrations are created in this phase.

- Workspaces own customer resources and are the tenancy boundary.
- Domains belong to workspaces; every domain query and mutation is workspace-authorized server-side.
- Registrar operation data is persisted locally so asynchronous work, reconciliation, and audit history are inspectable.
- Provider state and normalized product state are distinct: provider payloads are integration data, while product state is stable platform data.
- Provider cost and customer prices are stored separately and must not be inferred from each other.
- Sensitive data is minimized; data that must persist is encrypted and excluded from logs and ordinary responses.
- Future schema work should include explicit foreign keys, indexes for workspace ownership and operation lookup, and immutable audit/event semantics where required.
