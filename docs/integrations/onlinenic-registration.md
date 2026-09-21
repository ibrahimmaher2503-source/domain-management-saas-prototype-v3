# Paid-order OnlineNIC registration

The first registration lifecycle is intentionally narrow and supports `.com` only:

No production registration should be run until provider test validation, explicit production credentials, and operator approval are in place.

`paid order → ProvisionDomainRegistration job → CheckDomain → four CreateContact writes → CreateDomain → InfoDomain → local Domain → completed order`.

The Paymob callback only changes the payment/order state and dispatches the job after commit to the database queue, even if the default queue connection is `sync`. Run `php artisan queue:work database` to process it. The job has `tries = 1`; it never retries a registrar write automatically.

Every write creates a `RegistrarOperation` with a persisted `cltrid` before the provider call. Operations store only safe request metadata (domain, role, period, and nameserver count) and provider identifiers/status. Contact IDs returned by OnlineNIC are encrypted on the order. Contact and domain passwords are generated with cryptographically secure random bytes, sent only to OnlineNIC, and stored encrypted when needed; they are never logged or sent to the customer UI.

The job re-checks availability immediately before contacts. If the domain is unavailable, the paid payment remains paid and the order becomes failed with a customer-safe reason. Clear provider rejection also fails the order. A lost/ambiguous write marks its operation `ambiguous` and leaves the order in `provisioning`; it is not resent. `ReconcileDomainRegistration` uses read-only `InfoDomain`. If the domain is confirmed, it creates the local domain and completes the order; unresolved results remain for later review.

If a worker stops after a confirmed CreateDomain but before local Domain creation, an operator can dispatch the same read-only reconciliation job for the completed registration operation; it does not resend CreateDomain. Ambiguous contact creation cannot be retried automatically because OnlineNIC supplies the contact ID only in the response, leaving no known ID for CheckContact.

Successful registration creates one user-owned local Domain with active lifecycle status, provider dates, nameservers, and nullable provider status fields. `Locked`, auto-renew, privacy, transfers, SSL, and DNS zone management are not lifecycle features in this milestone. My Domains and Domain Control Center use only the authenticated user's local domains.
