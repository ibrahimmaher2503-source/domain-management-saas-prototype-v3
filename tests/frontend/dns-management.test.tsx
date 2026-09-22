import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import DnsPanel from '@/Components/Domain/DnsPanel';

vi.stubGlobal('route', (name: string) => `/${name}`);
vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn(), patch: vi.fn(), delete: vi.fn() } }));

const zone = { status: 'pending', assigned_nameservers: ['amy.ns.cloudflare.com', 'bob.ns.cloudflare.com'], delegated: false, ambiguous: false, provider_synced_at: null };

describe('DNS management states', () => {
    it('offers connection only when not connected', () => {
        render(<DnsPanel domainId={1} domainName="example.com" zone={null} records={[]} />);
        expect(screen.getByRole('button', { name: 'Connect DNS' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Add Record' })).not.toBeInTheDocument();
    });

    it('shows assigned nameservers while pending without record controls', () => {
        render(<DnsPanel domainId={1} domainName="example.com" zone={zone} records={[]} />);
        expect(screen.getByText('amy.ns.cloudflare.com')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Confirm delegation' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Add Record' })).not.toBeInTheDocument();
    });

    it('shows active records, forms, and delete confirmation', () => {
        render(<DnsPanel domainId={1} domainName="example.com" zone={{ ...zone, status: 'active', delegated: true }} records={[{ id: 'r1', type: 'A', name: 'www.example.com', content: '192.0.2.1', ttl: 1, proxied: false, proxiable: true }]} />);
        expect(screen.getByText('192.0.2.1')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Add Record' }));
        expect(screen.getByText('Add Record', { selector: 'h3' })).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
        expect(screen.getByRole('dialog', { name: 'Delete DNS record' })).toBeInTheDocument();
    });
});
