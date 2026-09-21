import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';
import Checkout from '@/Pages/Checkout/Domain';
import DomainShow from '@/Pages/Domains/Show';

vi.stubGlobal('route', (name: string) => `/${name}`);
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));
vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/react')>('@inertiajs/react');
    return { ...actual, Head: () => null, Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>, useForm: (data: object) => ({ data, setData: vi.fn(), post: vi.fn(), processing: false }) };
});

describe('platform-owned registrar contacts', () => {
    it('collects payment billing information and nameservers, not registrar contacts', () => {
        render(<Checkout quote={{ domain: 'example.com', period: 1, customerPrice: '10.31', currency: 'EGP', availability: { domain: 'example.com' }, capability: { periods: [1, 2] } }} />);

        expect(screen.getByRole('heading', { name: 'Payment billing information' })).toBeInTheDocument();
        expect(screen.getByLabelText('First name')).toBeInTheDocument();
        expect(screen.getByLabelText('Email')).toBeInTheDocument();
        expect(screen.getByLabelText('Nameserver 1')).toBeInTheDocument();
        expect(screen.queryByText(/Registrant contact|Administrative contact|Technical contact|Billing contact|Use Registrant details/i)).not.toBeInTheDocument();
    });

    it('keeps DNS records distinct from nameserver delegation without showing contacts', () => {
        render(<DomainShow domain={{ id: 1, name: 'example.com', status: 'active', registered_at: '2026-09-22', expires_at: '2027-09-22', nameservers: ['ns1.example.net', 'ns2.example.net'], provider_status: null }} />);

        expect(screen.queryByText('Registrant')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Contacts' })).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Nameservers' }));
        expect(screen.getByText(/delegated/)).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'DNS' }));
        expect(screen.getByText(/DNS zone management is not connected yet/)).toBeInTheDocument();
        expect(screen.getByText(/A, AAAA, CNAME, MX, TXT, SRV, and CAA/)).toBeInTheDocument();
    });
});
