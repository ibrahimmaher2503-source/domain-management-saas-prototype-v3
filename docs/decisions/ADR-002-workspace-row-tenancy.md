# ADR-002: Workspace Row Tenancy

## Status

Accepted as the initial tenancy model.

## Decision

Users and workspaces are platform records. Customer resources, including domains, are owned by a workspace through explicit foreign keys and server-side authorization/policies on every read and mutation.

## Consequences

The model is simple to query and fits the initial SaaS scope. Tenant boundaries must be enforced in application actions and policies, never by URL filters or UI visibility. Database indexes and tests must make workspace scoping hard to omit.
