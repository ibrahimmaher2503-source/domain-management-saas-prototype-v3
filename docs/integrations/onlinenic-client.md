# OnlineNIC client foundation

This layer is infrastructure only. It connects to the documented TCP endpoint, consumes the provider greeting, logs in, sends generic XML commands, parses normalized responses, and closes the session. It intentionally contains no domain, transfer, privacy, SSL, payment, or admin business operation.

## Structure

- `OnlineNicClient`: session lifecycle, request limit, safe logging, and ambiguity handling.
- `OnlineNicTransport`: testable `connect`/`write`/`read`/`close` boundary.
- `TcpSocketTransport`: the only socket implementation; it owns stream timeouts.
- `OnlineNicXmlBuilder` / `OnlineNicResponseParser`: provider XML boundary.
- `OnlineNicAuthenticator`: documented request checksum construction.
- `OnlineNicTransactionIdGenerator`: unique `codex-` transaction IDs.
- `OnlineNicCommand`: the small provider-specific command seam for future actions.

## Lifecycle

Call `connect()`, then `login()`, then `execute($command)` for generic commands. `logout()` sends the provider logout action when possible and always closes the socket. `close()` is safe for cleanup without a provider request.

The client permits 150 authenticated command requests per session, matching the supplied guide. It refuses the next command once the limit is reached; a future session manager can reconnect and authenticate before continuing.

## Errors and ambiguity

Transport failure before `write()` becomes `ProviderUnavailable`. A successful write followed by a failed or unreliable read becomes `ProviderAmbiguousResponse`; the client never retries it. Callers must reconcile provider state before deciding what to do. Clear provider rejection codes are normalized to provider exceptions and retain the provider code/message.

## Configuration

Values are read from `config/onlinenic.php` and `.env`: host, port, client ID, password, connect timeout, and read timeout. Credentials are never logged or committed.

## Logging

Only provider, action, `cltrid`, `svtrid`, provider code, duration, and success are logged. Raw XML, passwords, auth codes, personal contact data, SSL material, and payment data are not logged by default.

## Future commands

Add a small command object implementing `OnlineNicCommand` with a category, provider action, and ordered payload. Keep domain/application DTOs and business rules outside this client. Do not add `CheckDomain`, `GetDomainPrice`, registration, renewal, or other registrar operations here.
