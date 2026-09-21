# ADR-003: Registrar Gateway Boundary

## Status

Accepted; implementation deferred to the integration phase.

## Decision

Product use cases depend on a provider-neutral `RegistrarGateway`. `OnlineNicGateway` is the first infrastructure implementation and is the only layer allowed to know OnlineNIC protocol details.

## Consequences

OnlineNIC can be replaced or supplemented without rewriting domain logic. Gateway results must be normalized, capability-aware, and explicit about ambiguous writes. The gateway interface is not scaffolded in this audit phase.
