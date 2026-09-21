# OnlineNIC error model

Provider codes are mapped to stable application errors. The provider message and `<value>` remain diagnostic data and must be redacted where they contain credentials, auth codes, CSR/certificate material, or personal data.

| Provider condition | Application error | Retry | Recovery |
|---|---|---|---|
| TCP connect/read/write timeout or closed socket | `ProviderUnavailable` | Reads only | Retry read; reconcile ambiguous writes. |
| Invalid login/checksum/authentication | `ProviderAuthenticationFailed` | No | Check secret/account/server configuration. |
| 1000 completed | none | n/a | Mark completed and persist provider IDs. |
| 1001 accepted/action pending | `ProviderOperationPending` | No duplicate write | Queue reconciliation; show pending. |
| 1300/1301 transfer acknowledgement | `ProviderOperationPending` | No duplicate transfer | Poll transfer state. |
| 2104 billing fail | `ProviderInsufficientBalance` | No automatic retry | Ask for balance/top-up, then explicit retry. |
| 2106 transfer ineligible | `ProviderRejectedOperation` | No | Explain eligibility/lock/age restriction. |
| 2300 object pending transfer | `ProviderRejectedOperation` | No | Wait or cancel existing transfer. |
| 2307 object has no ID Shield | `ProviderRejectedOperation` | No | Apply ID Shield first if product policy allows. |
| Any other non-success code | `ProviderRejectedOperation` | No by default | Preserve code/message for support mapping. |
| Malformed XML, missing required field, invalid checksum | `ProviderInvalidResponse` | No blind retry | Quarantine response and alert integration health. |
| Requested action not mapped for TLD | `UnsupportedCapability` | No | Use capability matrix; require provider confirmation. |

## Local state

Use separate status dimensions: operation (`pending`, `processing`, `action_required`, `completed`, `failed`, `ambiguous`), domain lifecycle, and transfer status. Never collapse an asynchronous transfer into a synchronous success. An ambiguous write is recoverable only through provider state reconciliation or support evidence.
