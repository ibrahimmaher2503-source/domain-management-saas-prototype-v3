# Cloudflare DNS management

OnlineNIC is the registrar and controls nameserver delegation. Cloudflare is the authoritative DNS host and controls zone records. The two are distinct external systems.

## Setup

Set `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ACCOUNT_ID` in the server environment. The optional `CLOUDFLARE_API_BASE` defaults to `https://api.cloudflare.com/client/v4`. Use an API token scoped to the intended account/zones with zone creation/read and DNS read/write permissions. Never expose the token to the browser.

## Flow

Connect reserves a local zone row before external writes. It looks for an existing Cloudflare zone in the configured account, creates a full zone only if no earlier create was attempted, stores its ID and assigned nameservers, then passes those nameservers to the existing OnlineNIC nameserver service. Cloudflare remains `pending` until a separate status read reports `active`. The local status is not inferred from OnlineNIC write acceptance.

Cloudflare zone creation and OnlineNIC delegation are not atomic. If the zone exists but registrar delegation fails, the zone stays pending and is not deleted. Refresh status reads Cloudflare and OnlineNIC; Confirm delegation uses the existing registrar write guard, so an ambiguous OnlineNIC write is never blindly resent. An ambiguous Cloudflare create is also never resent automatically: the next read searches by account and exact zone name. If that cannot find a zone, operator reconciliation is required.

When active, records are read directly from Cloudflare and not copied into local tables. A/AAAA/CNAME/MX/TXT/SRV/CAA create, PATCH update, and delete are supported. SRV and CAA use Cloudflare `data` objects. TTL `1` means Auto. Record names must stay inside the customer's zone. Deletion requires confirmation.

Record writes are tracked before dispatch. Network failures and server errors are ambiguous; subsequent writes are blocked until a fresh list confirms a matching new/updated record or an absent deleted record. No automatic write retry occurs. Provider payloads, tokens, and raw error messages are never put into customer activity; only type and operation outcome are shown. Unresolved ambiguity requires operator review.

Official API references: [Create zone](https://developers.cloudflare.com/api/resources/zones/methods/create/), [Zone details](https://developers.cloudflare.com/api/resources/zones/methods/get/), [List records](https://developers.cloudflare.com/api/resources/dns/subresources/records/methods/list/), [Create record](https://developers.cloudflare.com/api/resources/dns/subresources/records/methods/create/), [Update record](https://developers.cloudflare.com/api/resources/dns/subresources/records/methods/edit/), [Delete record](https://developers.cloudflare.com/api/resources/dns/subresources/records/methods/delete/).

Automated tests use a fake DNS provider and fake registrar. They make no live Cloudflare or OnlineNIC writes. A live disposable-zone smoke test requires explicit operator approval.
