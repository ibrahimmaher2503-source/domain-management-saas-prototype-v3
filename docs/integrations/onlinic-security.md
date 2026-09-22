# OnlineNIC domain security

Source: `API_EN_Version_3.4.md`, Domain Management sections `UpdateDomainStatus`, `InfoDomainExtra`, and `GetAuthcode`.

## Transfer lock

The application exposes a nullable `transfer_locked` state: `true` is confirmed locked, `false` is confirmed unlocked, and `null` is unknown. `InfoDomainExtra` documents registry `status`; `clientTransferProhibited` normalizes to locked, another conclusive returned status (including documented `ok`) normalizes to unlocked, and an omitted/unavailable status does not overwrite the current value.

`UpdateDomainStatus` documents both directions. Lock uses `addstatus=clientTransferProhibited`; unlock uses `remstatus=clientTransferProhibited`. Its checksum includes only `domaintype` and `domain` after the action. The guide explicitly names `remstatus`, describes adding/removing this status, and lists `.com` among supported extensions.

Every write has a unique `cltrid` persisted in a pending `RegistrarOperation` before dispatch. A clear success completes the operation. A transport or response ambiguity is never resent automatically: the operation remains ambiguous until `InfoDomainExtra` conclusively reports the requested state, at which point sync completes it.

## Auth/EPP code

`GetAuthcode` returns the customer transfer code in the provider response field named `password`. The adapter normalizes only that value as an auth code; it is distinct from the backend-only domain password used at registration.

The owner-only POST endpoint requires the user's current account password on every request and is rate-limited. Its response is `private, no-store`. The code is returned only by that endpoint and is never added to Inertia page props, databases, operation metadata, sessions, caches, URLs, activity text, or logs. The browser keeps it only in component memory and clears it when the modal closes.

Automated tests use a fake registrar. No OTE auth-code read or live transfer-lock mutation runs automatically because both expose or change sensitive registrar state.
