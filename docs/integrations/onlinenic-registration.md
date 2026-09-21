# Paid-order OnlineNIC registration

The first registration lifecycle is intentionally narrow and supports `.com` only:

No production registration should be run until provider test validation, explicit production credentials, and operator approval are in place.

`paid order → ProvisionDomainRegistration job → validate platform contact IDs → CheckDomain → CreateDomain → InfoDomain → local Domain → completed order`.

The customer owns the SaaS account and local domain entitlement. In v1 the platform owner controls the OnlineNIC registrar contacts. Registrant, administrative, technical, and billing contact IDs are backend-only configuration; they may be identical if the provider account permits it. Customers neither supply nor edit registrar contacts, and no customer contact data is sent to OnlineNIC. Missing configuration fails closed with a safe operator-review reason after payment. Paymob payer/billing data is separate, encrypted on the order, and never used as registrar contact data.

The Paymob callback only changes the payment/order state and dispatches the job after commit to the database queue, even if the default queue connection is `sync`. Run `php artisan queue:work database` to process it. The job has `tries = 1`; it never retries a registrar write automatically.

Every CreateDomain write creates a `RegistrarOperation` with a persisted `cltrid` before the provider call. Operations store only safe request metadata (domain, period, and nameserver count) and provider transaction/status data. Platform contact IDs are never stored on the order or sent to React. The domain password is generated with cryptographically secure random bytes, stored encrypted, and never logged or sent to the customer UI.

The job re-checks availability immediately before registration. If the domain is unavailable, the paid payment remains paid and the order becomes failed with a customer-safe reason. Clear provider rejection also fails the order. A lost/ambiguous write marks its operation `ambiguous` and leaves the order in `provisioning`; it is not resent. `ReconcileDomainRegistration` uses read-only `InfoDomain`. If the domain is confirmed, it creates the local domain and completes the order; unresolved results remain for later review.

If a worker stops after a confirmed CreateDomain but before local Domain creation, an operator can dispatch the same read-only reconciliation job for the completed registration operation; it does not resend CreateDomain. Legacy `orders.registration_data` and `orders.provider_contact_ids` remain for schema compatibility but are deprecated and unused by new checkout, payment, and provisioning flows. An older unpaid order without `billing_data` remains readable but cannot start a new Paymob intention until its payment billing data is supplied through an operator-approved migration or a new checkout.

Successful registration creates one user-owned local Domain with active lifecycle status, provider dates, nameservers, and nullable provider status fields. `Locked`, auto-renew, privacy, transfers, SSL, and DNS zone management are not lifecycle features in this milestone. Nameservers are delegation, not DNS records. The DNS tab reserves future provider-neutral `DnsProvider` work; no DNS-zone provider is selected yet. My Domains and Domain Control Center use only the authenticated user's local domains.
