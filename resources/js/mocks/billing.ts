import type { BillingItem } from '@/types/domain';
export const billingItems: BillingItem[] = [
    { id: 1, description: 'Domain renewal', domain: 'example.com', amount: '$14.99', status: 'Paid', date: 'Sep 21, 2026' },
    { id: 2, description: 'SSL certificate', domain: 'example.com', amount: '$49.00', status: 'Paid', date: 'Aug 14, 2026' },
    { id: 3, description: 'Domain renewal', domain: 'northstar.io', amount: '$42.00', status: 'Failed', date: 'Sep 18, 2026' },
];
