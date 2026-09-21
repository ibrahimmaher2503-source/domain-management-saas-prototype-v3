# Domain Management SaaS Constitution

## Architecture

- The application architecture is Laravel + Inertia + React + TypeScript.
- React components render state and dispatch intent; business logic lives in Laravel application/domain code.
- The browser must never call OnlineNIC directly.
- OnlineNIC access is exclusively `RegistrarGateway -> OnlineNicGateway -> OnlineNIC API`.
- OnlineNIC command/action names must not leak into product or domain logic.
- SaaS users and workspaces belong to our platform database, not OnlineNIC.

## Product and data rules

- Domain operations are capability-driven by TLD and provider support.
- Provider cost and customer price are separate concepts.
- Every provider write creates an internal operation record.
- Register, renew, transfer, privacy, nameserver mutation, and SSL operations are auditable.
- Ambiguous registrar writes are reconciled; they are never blindly retried.
- Secrets are never logged: OnlineNIC credentials, payment cards, transfer auth codes, passwords, and sensitive tokens.
- Workspace authorization is enforced server-side. UI hiding is never authorization.
- DNS zone CRUD is not an approved capability. Do not implement A, AAAA, CNAME, MX, TXT, SRV, CAA, DNSSEC, or zone import/export until a validated DNS-DIY API is approved.
- SSL product status must never be presented as generic website HTTPS health.

## Delivery rules

- Important mutations require authorization, validation, an application action/service, an operation or audit record where applicable, and tests.
- Prefer small focused classes and avoid premature abstraction except at required boundaries such as registrar and payment gateways.
- Every implementation task finishes with tests passing.
