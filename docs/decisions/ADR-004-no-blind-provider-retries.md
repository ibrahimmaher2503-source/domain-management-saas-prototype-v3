# ADR-004: No Blind Provider Retries

## Status

Accepted.

## Decision

Never automatically retry a registrar write when the response is ambiguous or the connection outcome is unknown. Record the operation, mark it for reconciliation, and verify provider state before any follow-up action.

## Consequences

This may leave an operation processing longer than a simple retry, but avoids duplicate registrations, renewals, transfers, or mutations. Read-only/idempotent work may use bounded retries only when its contract proves that safe behavior.
