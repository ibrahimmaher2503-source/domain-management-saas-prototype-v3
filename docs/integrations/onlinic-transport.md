# OnlineNIC transport contract

The guide specifies a long-lived TCP connection to `ote.onlinenic.com:30009` for testing and `www.onlinenic.com:30009` for production. The server sends a greeting before login. Messages are XML envelopes; exact framing/timeouts must be verified against a live sandbox before implementation.

## Session rules

1. Open TCP connection and read greeting.
2. Verify the greeting response before sending `Login`.
3. Send `Login` with unique `cltrid` and the provider checksum formula.
4. Reuse the authenticated session for at most 150 requests, then logout/reconnect.
5. Validate response checksum, category, action, client transaction ID, and provider transaction ID.
6. Logout on graceful shutdown; close the socket on protocol, timeout, or checksum failure.

## Request metadata

`cltrid` must be globally unique enough for operations and reconciliation. Persist it with the local operation. Persist `svtrid` when returned. Never persist plaintext account passwords in operation payloads; retain only encrypted provider credentials in secret storage.

## Timeouts and retries

Use bounded connect, read, and write timeouts. A dropped connection after a mutating request is **ambiguous**, not failed. Do not blindly retry `CreateDomain`, `RenewDomain`, transfers, contact/registrant changes, nameserver/host writes, ID Shield writes, SSL orders, or cancellations. Reconcile with `InfoDomain`, transfer queries, ID Shield info, SSL `Info`, or the relevant host/contact query before offering retry.

Safe bounded retries are limited to idempotent reads (`CheckDomain`, `InfoDomain`, price, balance, status queries) and a fresh session login after a transport failure. Authentication/checksum failures are not retryable without operator/configuration action.

## Provider response handling

Treat code `1000` as completed, `1001` as accepted/pending, and `1300`/`1301` as transfer acknowledgement states. All other codes are normalized into the error model. Preserve the raw provider message in restricted audit storage, but expose a safe, localized message to users.
