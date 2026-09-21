# Architecture

See `docs/architecture/`. Request path: Browser -> Laravel/Inertia -> Application/Domain -> RegistrarGateway -> provider integration. PostgreSQL is the future system of record; Redis is environment-configured for queues and cache.
