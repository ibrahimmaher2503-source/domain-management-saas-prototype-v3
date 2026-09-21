# ADR-002: User Resource Ownership

## Status

Accepted for v1.

## Decision

Customer resources belong directly to a platform user. Domains, orders, payments, transfers, and certificate orders use `user_id` ownership in v1. There are no workspaces, teams, members, invitations, or workspace switching flows.

Authorization remains mandatory server-side: a user must never access another user's resources.

## Consequences

The first release has one straightforward customer account boundary. If organization features are added later, they require a new reviewed tenancy decision rather than silently adding workspace fields now.
