import type { DomainDetail, DomainListItem } from '@/types/domain';

export const domains: DomainListItem[] = [
    { id: 1, name: 'example.com', status: 'Active', expiresAt: 'Sep 21, 2027', autoRenew: true, transferLocked: true, nameservers: ['ns1.example.net', 'ns2.example.net'], privacyStatus: 'Active', sslStatus: 'Active' },
    { id: 2, name: 'northstar.io', status: 'Expiring', expiresAt: 'Sep 26, 2026', autoRenew: true, transferLocked: true, nameservers: ['ns1.platformdns.com', 'ns2.platformdns.com'], privacyStatus: 'Active', sslStatus: 'Active' },
    { id: 3, name: 'orbitlabs.dev', status: 'Transfer pending', expiresAt: 'Jan 11, 2027', autoRenew: false, transferLocked: false, nameservers: ['ns1.vercel-dns.com', 'ns2.vercel-dns.com'], privacyStatus: 'Unsupported', sslStatus: 'Pending' },
    { id: 4, name: 'madebyacme.co', status: 'Active', expiresAt: 'May 2, 2027', autoRenew: true, transferLocked: true, nameservers: ['ns1.oldhost.com', 'ns2.oldhost.com'], privacyStatus: 'Active', sslStatus: 'Off' },
];

export const domainDetails: Record<number, DomainDetail> = Object.fromEntries(domains.map((domain) => [domain.id, { ...domain, registeredAt: 'Sep 21, 2022', registrant: 'Ibrahim Maher', administrative: 'Sarah Chen', technical: 'Ibrahim Maher', billing: 'Ibrahim Maher', transferStatus: domain.status === 'Transfer pending' ? 'Processing' : 'Ready', renewalPrice: domain.name.endsWith('.io') ? '$42.00 / year' : '$14.99 / year' }]));
