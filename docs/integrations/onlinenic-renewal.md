# OnlineNIC manual domain renewal

Source: `API_EN_Version_3.4.md`, Domain Management sections `Renew Domain` and `Get Domain Price`.

## Flow

`Renewal quote → domain_renewal order → Paymob → paid-order fulfillment router → ProvisionDomainRenewal → RenewDomain → InfoDomain → local expiration sync → completed order`.

Opening the Renewal tab performs a read-only current-price lookup and creates no order. The quote verifies ownership, active OnlineNIC `.com` eligibility, period, and exact configured currency compatibility. `GetDomainPrice` receives `op=renew`; the centralized customer pricing policy converts the provider decimal amount to the customer decimal amount using integer minor-unit arithmetic.

The customer reviews payment billing data prefilled from their latest usable encrypted order for the domain. A renewal order stores its own encrypted billing data and links to the local domain through nullable `orders.domain_id`. The existing `registration_period` column is intentionally retained as a legacy name and represents the period in years for registration and renewal.

## Period policy

The OnlineNIC `RenewDomain` definition documents a 1–10 year period for most domains and separately limits `.co` to 1–5 years. This milestone supports `.com` only, so allowed renewal periods are 1–10 years.

## Write safety and reconciliation

Before `RenewDomain`, the job attempts an authoritative `InfoDomain` sync and records `expires_at_before` plus the renewal period in safe operation metadata. It persists a unique `cltrid` on a pending `domain_renewal` RegistrarOperation before sending the write. The adapter sends only the documented `domaintype`, `domain`, and `period` fields; its checksum uses those fields in that order.

A clear success completes the operation and then refreshes expiration through `InfoDomain`; the documented `RenewDomain.exDate` is sufficient if that follow-up read is temporarily unavailable. A clear rejection fails the order while leaving the payment paid. A lost or invalid response marks the operation ambiguous and never resends `RenewDomain`.

`ReconcileDomainRenewal` performs only `InfoDomain` reads. It confirms completion when the provider expiration is at least the prior expiration plus the requested period. An unchanged or unavailable read remains ambiguous. Reconciliation is bounded to three delayed reads; after that the operation remains action-required for future review.

Manual renewal only is implemented. There is no automatic charging, saved-card recurrence, automatic refund, auto-renew registrar setting, transfer, SSL, privacy, admin, or customer registrar contacts.
