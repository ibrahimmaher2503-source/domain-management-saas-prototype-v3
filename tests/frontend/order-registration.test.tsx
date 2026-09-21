import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';
import Show from '@/Pages/Orders/Show';

vi.stubGlobal('route', (name: string, id?: number) => `/${name}/${id ?? ''}`);
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));
vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/react')>('@inertiajs/react');
    return { ...actual, Head: () => null, Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a> };
});

const order = { id: 1, domain: 'example.com', status: 'paid', registration_period: 1, customer_price: '10.31', currency: 'EGP', nameservers: ['ns1.example.net', 'ns2.example.net'], created_at: '2026-09-22' };

describe('registration order status', () => {
    it('shows payment confirmation without provider internals', () => {
        render(<Show order={order} />);
        expect(screen.getByText('Payment confirmed. Your domain is being registered.')).toBeInTheDocument();
        expect(screen.queryByText(/cltrid|contact id|provider code|provider cost/i)).not.toBeInTheDocument();
    });

    it('shows reconciliation instead of retrying', () => {
        render(<Show order={{ ...order, status: 'provisioning', requires_reconciliation: true }} />);
        expect(screen.getByText("We're confirming the registration status with the registrar.")).toBeInTheDocument();
    });

    it('links a completed order to its real domain', () => {
        render(<Show order={{ ...order, status: 'completed', domain_id: 42 }} />);
        expect(screen.getByRole('link', { name: 'Manage Domain' })).toHaveAttribute('href', '/domains.show/42');
    });

    it('keeps failed registration distinct from failed payment', () => {
        render(<Show order={{ ...order, status: 'failed' }} />);
        expect(screen.getByText(/Your payment is safe. This order requires review./)).toBeInTheDocument();
    });
});
