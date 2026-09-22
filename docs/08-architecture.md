# Architecture

See `docs/architecture/`. Request path: Browser -> Laravel/Inertia -> Application/Domain -> RegistrarGateway -> provider integration. MySQL or MariaDB is the system of record and backs queues, sessions, and cache.
