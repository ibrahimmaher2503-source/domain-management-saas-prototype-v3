# Registrar contract

The application talks to a provider-neutral registrar contract. OnlineNIC is an adapter behind it; no application layer should know XML, socket framing, provider checksums, numeric `domaintype`, or provider action names.

## Active v1 contract operations

```text
checkDomain(CheckDomainData): DomainAvailability
getDomainPrice(DomainPriceQuery): DomainPrice
getDomainInfo(domain): DomainInfo
registerDomain(DomainRegistrationData): RegistrationResult
updateNameservers(UpdateNameserversData, cltrid): OperationResult
```

Registrar contact IDs for registration are platform-owned backend configuration. Customer billing data is separate and never becomes an OnlineNIC contact. Customer contact management is not in v1. Low-level OnlineNIC contact commands may remain isolated but are not part of the active gateway contract or registration flow.

## Future registrar capabilities (not implemented)

```text
renewDomain(RenewDomainData): RenewalResult
getAuthCode(DomainLookup): AuthCodeResult
setTransferLock(TransferLockData): OperationResult
getTransferStatus(TransferLookup): TransferStatus
requestTransfer(TransferRequestData): TransferResult
cancelTransfer(TransferLookup): OperationResult
getPrivacyStatus(PrivacyLookup): PrivacyStatus
applyPrivacy(PrivacyData): OperationResult
updatePrivacy(PrivacyData): OperationResult
renewPrivacy(PrivacyData): OperationResult
removePrivacy(PrivacyData): OperationResult
```

SSL and account operations are future separate bounded contracts. DNS zone record CRUD is absent from the registrar contract; a future provider-neutral `DnsProvider` will own it after an authoritative provider is selected.

## DTO boundaries

DTOs carry normalized domain names, period, platform contact role IDs, nameserver list, and provider transaction IDs as needed. Results carry normalized status, provider `cltrid`/`svtrid`, provider code, safe message, and timestamps. Domain passwords, checksums, and raw XML are sensitive fields.

## Normalized statuses

Use `pending`, `processing`, `action_required`, `completed`, `failed`, and `ambiguous` for operations. Keep domain lifecycle and transfer lifecycle separate. `1001`, `1300`, and `1301` map to pending/acknowledged states, not completed registration.

## Error surface

Adapters throw or return stable categories: `ProviderUnavailable`, `ProviderAuthenticationFailed`, `ProviderRejectedOperation`, `ProviderInsufficientBalance`, `ProviderInvalidResponse`, `ProviderOperationPending`, `UnsupportedCapability`, and `AmbiguousProviderResult`. The adapter owns provider code mapping; controllers own authorization and user-safe messages.

## Retry/reconciliation contract

Reads may be retried with bounded backoff. Mutations are not blindly retried after a transport failure. The service must persist the operation before dispatch, record `cltrid`, and enqueue a reconciliation read for ambiguous or pending results. Reconciliation is authoritative for domain info, transfer status, ID Shield status, SSL info, and host/contact queries.

## Capability policy

Capability checks happen before dispatch using the TLD matrix. Required fields are explicit typed fields for EU/CN/ASIA/US/UK rules; unknown TLD requirements fail closed. Provider credentials and transport settings are backend-only configuration.
