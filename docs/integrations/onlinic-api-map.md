# OnlineNIC API map

Source of truth: `C:\Users\N\Downloads\mark\API_EN_Version_3.4.md`, sections 7.1–8. The provider is a case-sensitive XML-over-TCP protocol on port 30009. Test credentials in the source document are intentionally omitted.

## Transport and envelope

Every request has `category`, `action`, zero or more `params`, unique `cltrid`, and `chksum`. A successful response has `code`, `msg`, optional `value`/`resData`, `cltrid`, `svtrid`, and `chksum`. IDs and checksums must never be exposed in normal application logs. Passwords are MD5-derived for the checksum; this is a provider protocol requirement, not an application password-storage recommendation.

## Commands

| Area | Provider action | Inputs / outputs | Class | Notes |
|---|---|---|---|---|
| Session | `Login` | `clid`; response envelope | read/session | Checksum includes `md5(clpass)`; max 150 requests per session. |
| Session | `Logout` | none | write/session | Closes the connection. |
| Contacts | `CheckContact` | `domaintype`, `contactid`; `avail` | read | 0 registered, 1 available. |
| Contacts | `CreateContact` | contact fields plus TLD fields; returns `contactid` | write | Required before registration for most TLDs. |
| Contacts | `UpdateContact` | `domaintype`, `domain`, `contacttype`, contact fields | write | TLD restrictions; use `ChangeRegistrant` where required. |
| Contacts | `ChangeRegistrant` | `domaintype`, `domain`, `name`, `org`; returns domain | write | Separate registrant mutation. |
| Domains | `CheckDomain` | `domaintype`, `domain`; availability and lookup key | read | IDN must be Punycode. |
| Domains | `InfoDomain` | `domaintype`, `domain`; dates, DNS, contacts, password/status data | read-sensitive | Auth/password material must be redacted and protected. |
| Domains | `CreateDomain` | `domaintype`, `mltype`, `domain`, `period`, DNS, contact IDs, password; dates | write-billing | TLD-specific required contacts and checksum composition. |
| Domains | `RenewDomain` | `domaintype`, `domain`, `period` | write-billing | Enabled for manual `.com` renewal, 1–10 years; no blind retries, reconcile ambiguity through `InfoDomain`. |
| Domains | `DeleteDomain` | `domaintype`, `domain` | destructive write | Provider documents quota/eligibility limits for some TLDs. |
| Domains | `UpdateDomainStatus` | `domaintype`, `domain`, `addstatus` or `remstatus` | write | `clientTransferProhibited` is added with `addstatus` and removed with `remstatus`; the source describes both operations and names `remstatus` explicitly. |
| Domains | `UpdateDomainExtra` | `domaintype`, `domain`, value-added service field | write | Provider-specific VAS; do not assume auto-renew/ID Shield semantics without mapping. |
| Domains | `UpdateDomainDns` | `domaintype`, `domain`, 2–6 `nameserver` values | write | Registrar nameserver assignment, not DNS zone hosting. |
| Domains | `UpdateDomainPwd` | domain/password fields | write-sensitive | Domain transfer/auth password mutation. |
| Domains | `InfoDomainExtra` | `domaintype`, `domain`; registry `status` and VAS data | read | The documented `status` is used to normalize `clientTransferProhibited`; a missing status remains unknown. |
| Domains | `GetAuthcode` | `domaintype`, `domain`; password/auth code | read-sensitive | Never show in logs or broad admin listings. |
| Domains | `GetTmNotice` | TLD/domain lookup fields | read | Trademark notice data; exact field rules are TLD-specific. |
| Domains | `GetDomainPrice` | `domaintype`, `domain`, `op`, `period` | read-billing | Application operations map `registration → reg`, `renewal → renew`, and `transfer → transfer`; returns current provider price but no currency. |
| Domains | `UpdateXxxMemberId` | TLD/member ID fields | write | Template action in source; exact supported TLDs require confirmation. |
| Hosts | `CheckHost` | `domaintype`, `hostname`; `avail` | read | Checks registered host object. |
| Hosts | `InfoHost` | `domaintype`, `hostname`; IP addresses | read | Main domain must be in the account. |
| Hosts | `CreateHost` | `domaintype`, `hostname`, one or more addresses | write | Registers glue/host object at the registry. |
| Hosts | `UpdateHost` | `domaintype`, `hostname`, address list | write | Main domain must be in the account. |
| Hosts | `DeleteHost` | `domaintype`, `hostname` | destructive write | Deletes registered host object. |
| ID Shield | `InfoIDShield` | `domaintype`, `domain`; status/dates | read-billing | Status includes `clientIdShield` and `clientPause`. |
| ID Shield | `AppIDShield` | `domaintype`, `domain` | write-billing | Fee and eligibility apply. |
| ID Shield | `UpdateIDShield` | `domaintype`, `domain`, `status` | write-billing | Pause/resume; provider documents free-operation limits. |
| ID Shield | `RenewIDShield` | `domaintype`, `domain` | write-billing | Extends service after domain renewal. |
| ID Shield | `DeleteIDShield` | `domaintype`, `domain` | destructive write | Must be enabled, not paused. |
| Reseller transfer | `QueryCustTransfer` | `domaintype`, `domain`, `op` (`in`/`out`); status/IDs/dates | read | Inner-reseller transfer. |
| Reseller transfer | `RequestCustTransfer` | `domaintype`, `domain`, password, current account ID | write-billing | Successful transfer renews by default per source notes. |
| Reseller transfer | `CustTransferSetPwd` | domain/password and transfer fields | write-sensitive | Losing reseller operation. |
| Registrar transfer | `QueryRegTransfer` | `domaintype`, `domain`; status | read | Transfer is asynchronous. |
| Registrar transfer | `RequestRegTransfer` | `domaintype`, `domain`, `mailway` | write-billing/async | No Auth/EPP field is documented. `mailway=On` means reseller-sent confirmation; `Off` means OnlineNIC-sent and is the platform choice. Source example returns code 1001/pending and a successful transfer renews one year. |
| Registrar transfer | `CancelRegTransfer` | `domaintype`, `domain` | destructive write | Cannot cancel after transfer success or failure. Application confirms a pending state before writing. |
| SSL | `Order` | product, validity, server, contacts, CSR/organization/approver fields; order ID/price | write-billing/async | Can purchase or renew. No documented pre-order price lookup; v1 uses explicit server-side product pricing. |
| SSL | `GetApproverEmailList` | domain; email list | read | Domain validation choices. |
| SSL | `Cancel` | order ID | destructive write | Cancel certificate order. |
| SSL | `Info` | order ID; full order/status/certificate data | read-sensitive | Certificate/CSR data is sensitive. |
| SSL | `ResendApproverEmail` | order ID | write/async | Sends provider email. |
| SSL | `ChangeApproverEmail` | order ID, approver email | write | Changes approval target. |
| SSL | `GetCerts` | date range; order records | read | Returns delimited `dataN` records. |
| SSL | `Reissue` | order ID, CSR and product fields | write/async | CSR required. |
| SSL | `ResendFulfillmentEmail` | order ID | write/async | Recovery for original certificate delivery. |
| SSL | `ParseCSR` | CSR/product fields; parsed CSR attributes | read | Does not issue a certificate. |
| Account | `GetAccountBalance` | none; balance | read-sensitive | Billing source of truth. |
| Account | `GetCustomerInfo` | none; account profile | read | Account data. |
| Account | `ModCustomerInfo` | account profile fields | write | Provider action spelling is `ModCustomerInfo` in the source. |

## Application classification

UI should expose only supported product operations: availability, registration, renewal, domain info, nameservers, transfer, privacy/ID Shield, SSL, and billing status. Admin/backend owns credentials, command construction, checksums, audit records, reconciliation, and redaction. No UI should accept raw `category`, `action`, `chksum`, `svtrid`, or provider XML.

## Important boundary

The guide documents registrar nameservers/host objects only. It does **not** document A/AAAA/CNAME/MX/TXT/SRV/CAA records, zone files, DNSSEC, or authoritative DNS hosting. DNS zone management is therefore not a confirmed OnlineNIC capability.
