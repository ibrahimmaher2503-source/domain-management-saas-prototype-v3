import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Show from '@/Pages/SSL/Show';

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <>{children}</> }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
    router: { post: vi.fn() },
    useForm: (data: any) => ({ data, setData: vi.fn(), post: vi.fn() }),
}));
vi.stubGlobal('route', (name: string) => name);

const certificate = { id: 1, domain: 'example.com', product: 'dv', status: 'pending_validation', validation_type: 'dv', approver_email: 'admin@example.com', actions: { cancel: true, change_approver_email: true, resend_approver_email: true, reissue: false, resend_fulfillment_email: false } };

describe('SSL maintenance', () => {
    afterEach(cleanup);

    it('shows only pending-validation actions and confirmations', () => {
        render(<Show certificate={certificate} activity={[]} />);
        expect(screen.getByRole('button', { name: 'Resend validation email' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Confirm email change' })).toBeInTheDocument();
        expect(screen.getByText('I understand this cancels the certificate order.')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Request reissue' })).not.toBeInTheDocument();
        expect(screen.queryByText(/Auto-Renew|Contacts|SSL renewal/i)).not.toBeInTheDocument();
    });

    it('shows issued-only reissue and fulfillment actions', () => {
        render(<Show certificate={{ ...certificate, status: 'issued', actions: { cancel: false, change_approver_email: false, resend_approver_email: false, reissue: true, resend_fulfillment_email: true } }} activity={[]} />);
        expect(screen.getByRole('button', { name: 'Request reissue' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Resend certificate email' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Cancel certificate' })).not.toBeInTheDocument();
    });
});
