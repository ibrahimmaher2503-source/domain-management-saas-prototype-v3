# Security Checklist

- [x] Standard Laravel CSRF and auth middleware retained.
- [x] `.env` excluded; no credentials committed.
- [x] User-resource authorization established as the v1 rule.
- [x] Provider secrets prohibited from logs.
- [ ] Add policies with each future owned resource.
- [ ] Add rate limits to sensitive future registrar/payment operations.
