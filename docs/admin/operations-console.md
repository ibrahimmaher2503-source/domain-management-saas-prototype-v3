# Admin operations console

## Access model

V1 has two capabilities only: customer and admin. `users.is_admin` defaults to false, and every `/admin` route requires authenticated, verified, admin middleware. No RBAC, customer roles, impersonation, teams, or workspaces are introduced.

## Pages

- Overview: database-backed customer, domain, expiry, transfer, SSL, order, and external-operation metrics plus recent operational activity.
- Customers: searchable accounts with counts and a safe related-resource detail view.
- Domains: customer, state, expiry, provider, sync, DNS, lock, renewal, transfer, SSL, and safe activity views.
- Orders and payments: current product types, payment state, fulfillment state, and safe failure messages.
- Transfers and SSL: normalized/provider states, linked billing records, sync times, and safe activity.
- Operations: paginated registrar and DNS activity with stale, ambiguous, action-required, and paid-but-failed fulfillment counters.
- Providers: safe configuration presence and last-success state for OnlineNIC, Paymob, and Cloudflare.
- Pricing: read-only effective environment/config values and an on-demand `.com` provider-price read.

All large index tables use server-side pagination with 25 records per page. Filters remain in the URL.

## Reconciliation rules

Admin actions call only existing read paths: registrar domain info, transfer status, SSL order info, and Cloudflare zone reads. Registration, renewal, transfer, and SSL reconciliation jobs retain their existing confirmation rules. An ambiguous write is never automatically sent again. There are no manual register, renew, transfer, SSL cancel, nameserver, lock, DNS-delete, or raw-command controls.

## Redaction policy

Admin responses are allowlisted. They omit passwords and password hashes, billing data, domain passwords, Auth/EPP codes, OnlineNIC contact IDs and credentials, SSL CSR/private applicant data, Paymob/Cloudflare secrets, callback metadata, transaction IDs, raw provider messages/XML, checksums, and provider payloads. Provider codes are shown only as restricted diagnostic fields.

## Provider status and OnlineNIC balance

Provider pages expose configuration presence, never values. OnlineNIC `GetAccountBalance` is a read-only account command. Its result contains only amount, configured currency, and check time, and is cached for 60 seconds. Manual refresh clears that cache. Provider failure renders `Unavailable` and does not break the page.

## Pricing limitations

Pricing is intentionally read-only. Domain registration, renewal, and transfer currently share the existing domain markup setting. SSL selling prices come from the existing SSL product configuration. The UI cannot edit environment values and does not introduce database pricing.

## Audit log

Every admin-triggered refresh or reconciliation creates an `admin_audit_logs` entry with admin, action, resource type/id, optional safe metadata, and timestamp. Secrets and raw provider data are never stored in the audit log.
