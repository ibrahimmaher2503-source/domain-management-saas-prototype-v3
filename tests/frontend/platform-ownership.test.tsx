import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import Checkout from '@/Pages/Checkout/Domain';
import DomainShow from '@/Pages/Domains/Show';

vi.stubGlobal('route', (name: string) => `/${name}`);
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));
vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/react')>('@inertiajs/react');
    return { ...actual, Head: () => null, Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>, router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() }, useForm: (data: object) => ({ data, setData: vi.fn(), post: vi.fn(), processing: false }) };
});
afterEach(() => { cleanup(); vi.clearAllMocks(); });

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
        expect(screen.getByText('DNS is not hosted by this platform.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Connect DNS' })).toBeInTheDocument();
    });

    it('shows real overview and safe activity without provider internals', () => {
        render(<DomainShow domain={{ id: 1, name: 'example.com', status: 'active', registered_at: '2026-09-22', expires_at: '2027-09-22', nameservers: ['ns1.example.net', 'ns2.example.net'], provider_status: null, provider_synced_at: null }} activity={[{ id: 1, label: 'Domain registered', status: 'completed', at: '2026-09-22T12:00:00Z' }, { id: 2, label: 'Domain synced', status: 'completed', at: '2026-09-23T12:00:00Z' }]} />);

        expect(screen.getByText('Not synced yet')).toBeInTheDocument();
        expect(screen.getByText('ns1.example.net, ns2.example.net')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Refresh domain' }));
        expect(router.post).toHaveBeenCalledWith('/domains.sync', {}, expect.any(Object));
        fireEvent.click(screen.getByRole('button', { name: 'Activity' }));
        expect(screen.getByText('Domain registered')).toBeInTheDocument();
        expect(screen.getByText('Domain synced')).toBeInTheDocument();
        expect(screen.queryByText(/cltrid|svtrid|provider code|password/i)).not.toBeInTheDocument();
    });

    it('requires confirmation before submitting a nameserver change', () => {
        render(<DomainShow domain={{ id: 1, name: 'example.com', status: 'active', registered_at: null, expires_at: null, nameservers: ['ns1.example.net', 'ns2.example.net'], provider_status: null }} />);
        fireEvent.click(screen.getByRole('button', { name: 'Nameservers' }));
        fireEvent.click(screen.getByRole('button', { name: 'Edit Nameservers' }));
        expect(screen.getByText(/DNS services at the previous provider may stop working/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Save Changes' })).toBeDisabled();
        fireEvent.change(screen.getByLabelText('Nameserver 1'), { target: { value: 'ns1.new.example' } });
        fireEvent.click(screen.getByRole('checkbox'));
        fireEvent.click(screen.getByRole('button', { name: 'Save Changes' }));
        expect(router.put).toHaveBeenCalledWith('/domains.nameservers.update', { nameservers: ['ns1.new.example', 'ns2.example.net'] }, expect.any(Object));
    });
});
