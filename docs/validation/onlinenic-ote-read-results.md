# OnlineNIC OTE read-only results

Status: **NOT RUN**. This checkpoint is intentionally a template until a separately authorized OTE run is performed. No OnlineNIC connection was made while creating this file.

| Operation | Result | Provider code | Observed normalized behavior | Implementation change required? |
|---|---|---:|---|---|
| TCP connection / greeting | BLOCKED | | OTE configuration and explicit run authorization required | No evidence yet |
| Login / session reuse | BLOCKED | | Not attempted | No evidence yet |
| Logout | BLOCKED | | Not attempted | No evidence yet |
| GetAccountBalance | BLOCKED | | Not attempted; exact balance must not be published | No evidence yet |
| CheckDomain (likely available .com) | BLOCKED | | Dedicated disposable test name required | No evidence yet |
| CheckDomain (known unavailable .com) | BLOCKED | | A safely known unavailable name is required | No evidence yet |
| GetDomainPrice reg .com/1 | BLOCKED | | Not attempted; no historical hardcoded comparison | No evidence yet |
| GetDomainPrice renew .com/1 | BLOCKED | | Not attempted | No evidence yet |
| GetDomainPrice renew .com/2 | BLOCKED | | Not attempted | No evidence yet |
| GetDomainPrice transfer .com/1 | BLOCKED | | Not attempted; currency remains unknown until observed | No evidence yet |
| InfoDomain | BLOCKED | | Known OTE-owned domain required | No evidence yet |
| InfoDomainExtra | BLOCKED | | Known OTE-owned domain required | No evidence yet |
| GetAuthcode | BLOCKED | | Known OTE-owned domain required; value will never be recorded | No evidence yet |
| QueryRegTransfer | BLOCKED | | Eligible OTE transfer resource required | No evidence yet |
| GetApproverEmailList | BLOCKED | | Dedicated OTE/test domain required | No evidence yet |
| ParseCSR | BLOCKED | | Disposable CSR file required; private key must never be supplied | No evidence yet |
| SSL Info | BLOCKED | | OTE SSL order ID required | No evidence yet |

No provider codes, credentials, contact IDs, auth codes, CSR material, or raw XML are present in this report.
