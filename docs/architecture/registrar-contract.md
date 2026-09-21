# Registrar contract

The application talks to a provider-neutral registrar contract. OnlineNIC is an adapter behind it; no application layer should know XML, socket framing, provider checksums, numeric `domaintype`, or provider action names.

## Contract operations

```text
checkDomain(CheckDomainData): DomainAvailability
getDomainPrice(DomainPriceQuery): DomainPrice
getDomainInfo(DomainLookup): DomainInfo
registerDomain(RegisterDomainData): RegistrationResult
renewDomain(RenewDomainData): RenewalResult
updateNameservers(UpdateNameserversData): OperationResult
getAuthCode(DomainLookup): AuthCodeResult
setTransferLock(TransferLockData): OperationResult
getTransferStatus(TransferLookup): TransferStatus
requestTransfer(TransferRequestData): TransferResult
cancelTransfer(TransferLookup): OperationResult
checkContact(ContactLookup): ContactAvailability
createContact(ContactData): ContactResult
updateContact(ContactData): OperationResult
changeRegistrant(ChangeRegistrantData): OperationResult
getPrivacyStatus(PrivacyLookup): PrivacyStatus
applyPrivacy(PrivacyData): OperationResult
updatePrivacy(PrivacyData): OperationResult
renewPrivacy(PrivacyData): OperationResult
removePrivacy(PrivacyData): OperationResult
```

SSL and account operations are separate bounded contracts: `orderCertificate`, `getCertificate`, `cancelCertificate`, `reissueCertificate`, `parseCsr`, `getBalance`, and `getAccountProfile`. DNS zone record CRUD is deliberately absent because the source guide does not document it.

## DTO boundaries

DTOs should carry normalized domain names plus the provider-facing Punycode form, TLD metadata, period, contact role IDs/data, nameserver list, idempotency key, and an audit reason for mutations. Results carry normalized status, provider `cltrid`/`svtrid`, provider code, safe message, timestamps, and a reconciliation hint. Auth codes, domain passwords, checksums, CSR/certificates, and raw XML are sensitive fields.

## Normalized statuses

Use `pending`, `processing`, `action_required`, `completed`, `failed`, and `ambiguous` for operations. Keep domain lifecycle and transfer lifecycle separate. `1001`, `1300`, and `1301` map to pending/acknowledged states, not completed registration.

## Error surface

Adapters throw or return stable categories: `ProviderUnavailable`, `ProviderAuthenticationFailed`, `ProviderRejectedOperation`, `ProviderInsufficientBalance`, `ProviderInvalidResponse`, `ProviderOperationPending`, `UnsupportedCapability`, and `AmbiguousProviderResult`. The adapter owns provider code mapping; controllers own authorization and user-safe messages.

## Retry/reconciliation contract

Reads may be retried with bounded backoff. Mutations are not blindly retried after a transport failure. The service must persist the operation before dispatch, record `cltrid`, and enqueue a reconciliation read for ambiguous or pending results. Reconciliation is authoritative for domain info, transfer status, ID Shield status, SSL info, and host/contact queries.

## Capability policy

Capability checks happen before dispatch using the TLD matrix. Required fields are explicit typed fields for EU/CN/ASIA/US/UK rules; unknown TLD requirements fail closed. Provider credentials and transport settings are backend-only configuration.
