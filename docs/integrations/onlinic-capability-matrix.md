# OnlineNIC capability matrix

This is a source-grounded planning matrix, not a promise that every TLD supports every command. The provider guide lists many numeric `domaintype` values and special rules; capability data must be verified per TLD before enabling a product action.

| Capability | Supported by guide | Required fields / constraints | Product decision |
|---|---|---|---|
| Availability | Yes | `domaintype`, Punycode domain | Enable after provider integration. |
| Registration | Yes | period, 2+ DNS values, contact IDs, domain password; TLD-specific checksum/fields | Enable per TLD policy. |
| Renewal | Yes | period and TLD term rules | Enable per TLD policy. |
| Domain info | Yes | domain and type | Enable, with sensitive-field redaction. |
| Registrar nameserver assignment | Yes | 2–6 nameserver values | Enable. |
| Registered host/glue objects | Yes | hostname, IP addresses; parent domain in account | Separate advanced capability. |
| Transfer lock/status | Yes for v1 `.com` controls | `UpdateDomainStatus` uses `addstatus`/`remstatus` with `clientTransferProhibited`; `InfoDomainExtra.status` confirms state | Owner security controls with ambiguous-write reconciliation. |
| Auth code | Yes | `GetAuthcode`; sensitive output | Owner-only, current-password verified, rate-limited, and never persisted. |
| Inner-reseller transfer | Yes | current account ID/password and transfer status | Separate workflow. |
| Registrar transfer | Yes | request/query/cancel; asynchronous codes and status | Reconciliation required. |
| ID Shield | Yes | apply, pause/resume, renew, delete; fees and limits | Separate paid add-on. |
| Auto-renew | Mentioned as VAS/status | `InfoDomainExtra` names it, but complete update semantics are not sufficiently specified | Do not claim as enabled until confirmed. |
| TLD-specific registration fields | Yes | EU, CN, ASIA CED, US, UK examples | Model as validated extension fields, not free-form UI. |
| Premium/TMCH | Partial | `GetTmNotice` exists; premium pricing/registration workflow is not fully mapped | Keep unsupported until verified. |
| SSL ordering/lifecycle | Yes | product, term, CSR, contacts, approver and organization data | Separate SSL module. |
| Account balance/profile | Yes | account-level commands | Admin/billing only. |
| DNS zone records | No evidence | No record/zone/DNSSEC commands in guide | Not an OnlineNIC capability. |

## TLD metadata to store

Store `tld`, provider `domaintype`, `punycode_required`, allowed registration/renewal periods, contact roles, special required fields (EU/CN/ASIA/US/UK), transfer rules, ID Shield availability, price-query support, and an `verified_at`/source reference. Unknown values must disable the action, not guess.
