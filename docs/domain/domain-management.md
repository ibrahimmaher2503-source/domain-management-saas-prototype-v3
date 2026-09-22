# Domain Management

The Domain Control Center is the customer's view of a domain entitlement owned in our SaaS account. Registrar contacts are controlled by the platform owner in v1 and are not customer-facing.

Planned customer areas: Overview, Nameservers, DNS, Security, Renewal, Transfers, SSL, Billing, and Activity. Overview now refreshes confirmed registrar information, and Nameservers changes registrar delegation with reconciliation for uncertain writes. DNS is reserved as a distinct area for future zone-record management (A, AAAA, CNAME, MX, TXT, SRV, CAA) through a provider-neutral `DnsProvider`; no authoritative DNS provider has been selected. OnlineNIC `UpdateDomainDns` assigns nameservers and is not DNS record CRUD.

Future capabilities must be gated by actual provider/TLD support. Transfer lock is a security state, not a lifecycle state. Operational errors remain on their relevant detail pages; there is no notification inbox in v1.
