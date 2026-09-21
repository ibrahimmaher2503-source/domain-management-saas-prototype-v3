import type { CertificateItem } from '@/types/domain';
export const certificates: CertificateItem[] = [
    { id: 1, domain: 'example.com', product: 'DV Certificate', status: 'Active', expiresAt: 'Dec 14, 2026' },
    { id: 2, domain: 'madebyacme.co', product: 'DV Certificate', status: 'Approval Required', expiresAt: 'Not issued' },
    { id: 3, domain: 'orbitlabs.dev', product: 'DV Certificate', status: 'Pending', expiresAt: 'Pending issuance' },
];
