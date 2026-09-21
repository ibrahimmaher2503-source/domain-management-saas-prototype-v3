# Paymob domain-order payments

This integration uses Paymob's current Egypt Intention API and Unified Checkout redirect flow:

1. The server creates `POST /v1/intention/` with `Authorization: Token <secret>`.
2. The browser is redirected to Paymob's hosted URL using the returned `publicKey` and `clientSecret`.
3. Paymob POSTs a transaction callback to `notification_url`. This callback is the only source of truth for payment state.
4. The callback HMAC is verified with SHA-512 before any payment or order update.

Required environment values are in `.env.example`. Keep the secret key and HMAC secret server-side. `PAYMOB_MODE=test` and test credentials are required for development; CI uses HTTP fakes and never contacts Paymob.

The intention contains the order total in integer minor units, one domain-registration item, one configured card integration ID, the registrant's minimum billing details, `special_reference=order-{local order id}`, expiration, notification URL, and return URL. The normalized response stores the Paymob intention ID, Paymob order ID, client secret (inside provider metadata), reference, and status.

Local payments are `pending`, `paid`, `failed`, or `cancelled`. A verified callback must be non-pending, successful, correlated by Paymob order ID, and match amount and currency before setting `Payment=paid` and `Order=paid`. A definitive failed callback sets only the payment to `failed`; the order stays `awaiting_payment`. Browser return callbacks only render `paid`, `failed`, or `processing` from local state and never change it.

Payment start is protected by an order row lock and a reusable pending payment. Callback processing locks the payment and is idempotent for repeated transaction callbacks. No cards, tokens, refunds, wallets, recurring payments, or OnlineNIC provisioning are implemented here.

Before production: configure live credentials and a public HTTPS callback, verify the card integration belongs to the same mode as the keys, confirm the HMAC fixture against the current Paymob dashboard, and perform a manual sandbox payment.
