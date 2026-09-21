# Domain Management

The post-purchase Domain Control Center is the single customer surface for every capability supported by the platform. Customers should not need to enter an upstream registrar panel for supported operations.

Expected areas:

- Overview
- Renewal
- Contacts
- Nameservers
- Child Nameservers / Glue
- Privacy
- Security
- Transfers
- SSL
- Billing
- Activity
- Advanced / Danger Zone

Capabilities are shown only when the domain's TLD/provider capability data supports them. DNS zone CRUD and DNSSEC remain explicitly out of scope. Transfer lock is a security state, not a lifecycle state. Authorization-code reveal requires re-authentication and must be audited. Operational errors stay on their relevant domain, transfer, or SSL detail page; there is no notification inbox in v1.
