# Billing Domain

Billing is a platform concern, not an OnlineNIC mirror. Customer prices, provider costs, taxes/fees, payment attempts, invoices, renewals, and refunds have separate meanings and persistence rules. Billing resources belong directly to the authenticated user in v1.

Renewal and registration actions create customer-facing orders and internal provider operation records. Payment failures are actionable product states. Provider writes that return ambiguously remain in processing/reconciliation until state is verified; they are not blindly retried.

Payment-card data and payment-provider secrets are never logged or stored unless a compliant payment boundary explicitly requires a tokenized reference.
