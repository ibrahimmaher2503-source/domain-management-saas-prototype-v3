import type { TransferItem } from '@/types/domain';
export const transfers: TransferItem[] = [
    { id: 1, domain: 'orbitlabs.dev', type: 'Transfer in', status: 'Processing', updatedAt: '2 hours ago' },
    { id: 2, domain: 'legacy-example.com', type: 'Transfer out', status: 'Action Required', updatedAt: 'Yesterday', nextAction: 'Unlock domain' },
    { id: 3, domain: 'framekit.app', type: 'Transfer in', status: 'Completed', updatedAt: 'Jun 12, 2026' },
];
