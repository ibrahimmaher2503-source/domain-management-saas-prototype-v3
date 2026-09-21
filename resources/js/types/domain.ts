export type DomainStatus = 'Active' | 'Expiring' | 'Transfer pending' | 'Suspended';
export type ServiceStatus = 'Active' | 'Pending' | 'Off' | 'Unsupported';

export type DomainListItem = {
    id: number;
    name: string;
    status: DomainStatus;
    expiresAt: string;
    autoRenew: boolean;
    transferLocked: boolean;
    nameservers: string[];
    privacyStatus: ServiceStatus;
    sslStatus: ServiceStatus;
};

export type DomainDetail = DomainListItem & {
    registeredAt: string;
    registrant: string;
    administrative: string;
    technical: string;
    billing: string;
    transferStatus: 'Ready' | 'Processing' | 'Action required';
    renewalPrice: string;
};

export type TransferItem = { id: number; domain: string; type: 'Transfer in' | 'Transfer out'; status: 'Pending' | 'Processing' | 'Action Required' | 'Completed' | 'Failed'; updatedAt: string; nextAction?: string };
export type CertificateItem = { id: number; domain: string; product: string; status: 'Active' | 'Approval Required' | 'Expiring' | 'Pending'; expiresAt: string };
export type BillingItem = { id: number; description: string; domain?: string; amount: string; status: 'Paid' | 'Pending' | 'Failed'; date: string };
