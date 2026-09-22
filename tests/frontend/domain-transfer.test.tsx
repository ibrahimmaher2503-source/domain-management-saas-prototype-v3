import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import TransferPanel from '@/Components/Domain/TransferPanel';
import TransfersIndex from '@/Pages/Transfers/Index';
import DomainQuickActions from '@/Components/Domain/DomainQuickActions';

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <>{children}</> }));
vi.stubGlobal('route', (name: string, value?: unknown) => `/${name}/${typeof value === 'number' ? value : ''}`);

describe('domain transfers', () => {
    afterEach(cleanup);
    it('renders real transfer rows with safe normalized state', () => {
        render(<TransfersIndex transfers={[{ id: 1, domain: 'example.com', direction: 'in', status: 'pending', requested_at: '2026-09-22T00:00:00Z', updated_at: '2026-09-22T01:00:00Z' }]} />);
        expect(screen.getByText('example.com')).toBeInTheDocument();
        expect(screen.getByText('Incoming')).toBeInTheDocument();
        expect(screen.queryByText(/cltrid|svtrid|Auth Code/i)).not.toBeInTheDocument();
    });

    it('uses Security for transfer-out and keeps excluded features absent', () => {
        render(<><TransferPanel domainId={1} transferLocked={false} /><DomainQuickActions /></>);
        expect(screen.getByText('Retrieve the Auth/EPP code securely.')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Open Security' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Auto Renew|Privacy/ })).not.toBeInTheDocument();
    });
});
