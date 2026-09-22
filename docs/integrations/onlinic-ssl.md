# OnlineNIC SSL milestone 1

The implementation is limited to purchasing and reading status for certificates on domains already managed by the customer. Reissue, cancellation, resend, renewal, privacy, contacts, and auto-renew are intentionally not implemented.

## Source mapping

The source is `API_EN_Version_3.4.md`, sections 7.3.1, 7.3.2, 7.3.4, and 7.3.10. The adapter sends category `ssl` and the documented actions `Order`, `GetApproverEmailList`, `Info`, and `ParseCSR`. The documented product codes are QuickSSLPremium, TrueBizID, TrueBizIDWildCard, TrueBizIDEV, RapidSSL, and RapidSSLWildCard. Validity is 12/24/36/48 months except TrueBizIDEV, which supports 12/24.

There is no documented read-only SSL price command. Product checkout therefore uses `config/ssl.php` and explicit environment-backed customer price/currency. Provider cost remains null until a successful `Order` response returns `price`.

## Checkout and secrets

The customer selects one of My Domains, a configured product, validity, CSR, and an email returned by `GetApproverEmailList`. `ParseCSR` is authoritative and the returned domain must match the selected domain. Only documented administrator/technical fields are sent; registrar Contacts are not used. The CSR is encrypted in `orders.ssl_data`; private keys are never accepted or stored. CSR, applicant data, XML, checksums, and transaction IDs are not placed in activity or normal Inertia props.

Paymob remains the existing order/payment path. `ssl_certificate` is a normal Order type and uses the existing HMAC, amount, currency, callback idempotency, and billing data handling.

## Provider writes and status

The paid fulfillment job persists an operation before `Order`. It has one attempt and never retries an ambiguous write. A returned provider order ID is persisted immediately. `Info` is the authoritative read: `COMPLETE`→issued, `PENDING`→pending_validation, `PRE`→processing, `CANCELLED`→cancelled, and `REFUND`→failed. Unknown states become action_required. Reconciliation is bounded to three delayed reads; unresolved state remains action_required for manual refresh.

The guide's ParseCSR checksum table contains an inconsistent `orderid` reference while its request example has no order ID. The adapter uses the parameter values actually present in the documented ParseCSR request (productCode and CSR); this remains unverified against a live OTE account.
