# OnlineNIC OTE validation results

Status: **NOT RUN**. This is the complete Phase A/Phase B evidence matrix. The harness defaults to read-only and does not connect until OTE configuration and a separate explicit authorization are supplied.

| Operation | Result | Provider code | Observed normalized behavior | Notes | Implementation change required? |
|---|---|---:|---|---|---|
| Login | BLOCKED | | Not attempted | No real OTE configuration supplied | No evidence yet |
| Logout | BLOCKED | | Not attempted | No real OTE configuration supplied | No evidence yet |
| GetAccountBalance | BLOCKED | | Not attempted; balance is sensitive | No real OTE configuration supplied | No evidence yet |
| CheckDomain | BLOCKED | | Not attempted | Disposable OTE names required | No evidence yet |
| GetDomainPrice reg | BLOCKED | | Not attempted | .com period 1 required | No evidence yet |
| GetDomainPrice renew | BLOCKED | | Not attempted | Periods 1 and optionally 2 | No evidence yet |
| GetDomainPrice transfer | BLOCKED | | Not attempted | Currency must be observed, not invented | No evidence yet |
| InfoDomain | BLOCKED | | Not attempted | OTE-owned domain required | No evidence yet |
| InfoDomainExtra | BLOCKED | | Not attempted | OTE-owned domain required | No evidence yet |
| GetAuthcode | BLOCKED | | Not attempted | Value must never be printed or persisted | No evidence yet |
| CreateDomain | BLOCKED | | Not attempted | Explicit disposable-domain write run required | No evidence yet |
| UpdateDomainDns | BLOCKED | | Not attempted | Only newly registered OTE domain | No evidence yet |
| UpdateDomainStatus lock | BLOCKED | | Not attempted | Lock lifecycle not forced | No evidence yet |
| UpdateDomainStatus unlock | BLOCKED | | Not attempted | Unlock lifecycle not forced | No evidence yet |
| RenewDomain | BLOCKED | | Not attempted | Balance and explicit authorization required | No evidence yet |
| QueryRegTransfer | BLOCKED | | Not attempted | No external eligible transfer resource | No evidence yet |
| RequestRegTransfer | BLOCKED | | Not attempted | No external eligible transfer resource | No evidence yet |
| CancelRegTransfer | BLOCKED | | Not attempted | No external eligible transfer resource | No evidence yet |
| GetApproverEmailList | BLOCKED | | Not attempted | OTE/test domain required | No evidence yet |
| ParseCSR | BLOCKED | | Not attempted | Disposable CSR required; checksum remains unverified | No evidence yet |
| SSL Order | BLOCKED | | Not attempted | No automatic balance-consuming order | No evidence yet |
| SSL Info | BLOCKED | | Not attempted | OTE SSL order ID required | No evidence yet |
| SSL Cancel | BLOCKED | | Not attempted | Suitable OTE order lifecycle required | No evidence yet |
| SSL ChangeApproverEmail | BLOCKED | | Not attempted | Suitable OTE order lifecycle required | No evidence yet |
| SSL ResendApproverEmail | BLOCKED | | Not attempted | Suitable OTE order lifecycle required | No evidence yet |
| SSL Reissue | BLOCKED | | Not attempted | Suitable OTE order lifecycle required | No evidence yet |
| SSL ResendFulfillmentEmail | BLOCKED | | Not attempted | Suitable OTE order lifecycle required | No evidence yet |

## Evidence classification

No documentation mismatch, implementation bug, OTE-specific behavior, or unknown provider behavior is claimed. No OnlineNIC protocol or checksum implementation was changed in this milestone.
