# Registrar Boundary

Product code depends on a provider-neutral `RegistrarGateway` concept. A future implementation may be `OnlineNicRegistrarGateway`, and another registrar can be added without changing product use cases.

The gateway boundary should expose product intent such as search, registration, renewal, transfer, contact, nameserver, privacy, lock, authorization-code, and certificate operations. OnlineNIC command names, XML shapes, socket details, and provider-specific status codes stay inside `Integrations/OnlineNic`.

The interface is intentionally not implemented in this foundation phase. First establish the application and data contracts, then implement one provider behind the boundary with explicit operation records and reconciliation for ambiguous writes.
