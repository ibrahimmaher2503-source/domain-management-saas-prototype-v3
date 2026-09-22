import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AppSidebar from '@/Components/AppSidebar';
import Operations from '@/Pages/Admin/Operations';

let admin = false;
vi.stubGlobal('route', (name?: string) => name ? `/${name}` : { current: () => false });
vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/react')>('@inertiajs/react');
    return { ...actual, Head: () => null, Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>, router: { get: vi.fn(), post: vi.fn() }, usePage: () => ({ props: { auth: { user: { id: 1, name: 'Owner', email: 'owner@example.com', is_admin: admin } } } }) };
});
vi.mock('@/Layouts/AdminLayout', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));
afterEach(cleanup);

describe('admin operations UI', () => {
    it('shows the admin entry only to admins', () => {
        const { rerender } = render(<AppSidebar />);
        expect(screen.queryByText('Admin')).not.toBeInTheDocument();
        admin = true;
        rerender(<AppSidebar />);
        expect(screen.getByText('Admin')).toBeInTheDocument();
    });

    it('shows safe reconciliation without provider write controls or internals', () => {
        render(<Operations filters={{}} alerts={{ stale_pending: 1, ambiguous: 1, action_required: 0, paid_failed_fulfillment: 0 }} operations={{ data: [{ source: 'registrar', id: 4, operation_id: 4, operation: 'domain_registration', resource: 'example.com', customer: 'Customer', provider: 'onlinenic', status: 'ambiguous', started_at: '2026-09-22T00:00:00Z', completed_at: null }], links: [] }} />);
        expect(screen.getByRole('button', { name: 'Reconcile' })).toBeInTheDocument();
        expect(screen.queryByText(/register again|renew again|SVTRID-SECRET|RAW-XML-SECRET/i)).not.toBeInTheDocument();
    });
});
