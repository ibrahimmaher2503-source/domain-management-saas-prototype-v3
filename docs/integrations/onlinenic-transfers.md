# OnlineNIC registrar transfers

Source: `API_EN_Version_3.4.md` sections 7.2.7, 8.5, and `GetDomainPrice`.

## Transfer in

The platform prices an incoming transfer with `GetDomainPrice(domaintype, domain, op=transfer, period=1)`. The response does not state a currency, so checkout is enabled only when the configured OnlineNIC account currency exactly matches the customer billing currency. The existing customer markup and Paymob order/payment flow are reused. A successful transfer includes a one-year renewal according to the provider notes.

`RequestRegTransfer` uses exactly `domaintype`, `domain`, and `mailway`. The platform sends `mailway=Off`, meaning OnlineNIC sends the transfer confirmation on its own behalf. `On` means delivery on behalf of the reseller. The request does not contain an Auth/EPP code; none is added. Code `1001` with `pending` is accepted as asynchronous success.

`QueryRegTransfer(domaintype, domain)` is the authoritative read. Documented status mappings are:

- `Pending transfer; no response to our confirmation email for transferring`, `pendingTransfer`, `pendingTransfer, system has not sent confirmation emai`, and the request example's `pending` → `pending`.
- `transferSuccessfully` → `completed`.
- `transfer failed; no pay` and `clientRejected` → `failed`.
- `clientCanceled; transfer operation expired`, `clientCanceled; client canceled`, and `clientCanceled; system canceled` → `cancelled`.
- Any other value → `action_required`; it is never treated as success.

After completion, `InfoDomain` supplies provider-backed domain fields. An existing domain for the same user is attached; a domain owned by another local user is never reassigned and leaves the transfer `action_required`.

## Cancellation and write safety

`CancelRegTransfer` uses exactly `domaintype` and `domain`. The source says a request cannot be cancelled after it transferred into OnlineNIC or failed. The application therefore requires explicit confirmation and a fresh `QueryRegTransfer` result normalized as pending/processing before cancellation. A `RegistrarOperation` with a unique `cltrid` and safe domain-only metadata is persisted before each request or cancellation write. Jobs have one attempt. Ambiguous writes are never resent; bounded reconciliation performs only `QueryRegTransfer` reads (one minute, then five and fifteen minutes).

## Transfer out

The document provides no inter-registrar transfer-out initiation command. The product only directs the owner to unlock the domain and retrieve the existing Auth/EPP code in Security, then start the transfer at the gaining registrar. Transfers never store or expose that code in Inertia props.

## Not proved by the source

The guide does not document an eligibility/preflight command, a provider transfer ID in registrar-transfer responses, extra cancellation states beyond the terminal restriction, or a currency in `GetDomainPrice`. No Contacts, Auto-Renew, SSL, Privacy/ID Shield, or Admin behavior is part of this flow.
