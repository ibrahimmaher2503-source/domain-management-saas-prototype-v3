import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { navigationItems } from '@/Components/AppSidebar';
import DomainQuickActions from '@/Components/Domain/DomainQuickActions';
import DomainTabs from '@/Components/Domain/DomainTabs';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import { domains } from '@/mocks/domains';

describe('migrated customer UI', () => {
    it('defines exactly the approved navigation', () => {
        expect(navigationItems.map(([, label]) => label)).toEqual(['Overview', 'Domains', 'Transfers', 'SSL', 'Billing', 'Settings']);
        expect(navigationItems.map(([, label]) => label)).not.toEqual(expect.arrayContaining(['Workspace', 'Notifications', 'Cart']));
    });

    it('provides typed mock domains including long names', () => {
        expect(domains.map((domain) => domain.name)).toContain('example.com');
        const longName = 'a-very-long-customer-domain-name.example';
        render(<div className="truncate">{longName}</div>);
        expect(screen.getByText(longName)).toBeInTheDocument();
    });

    it('renders an empty domain state', () => {
        render(<EmptyState title="No domains yet" description="Your purchased domains will appear here." />);
        expect(screen.getByText('No domains yet')).toBeInTheDocument();
    });

    it('renders known status badges', () => {
        render(<><StatusBadge status="Active" /><StatusBadge status="Action Required" /><StatusBadge status="Failed" /></>);
        expect(screen.getByText('Active')).toBeInTheDocument();
        expect(screen.getByText('Action Required')).toBeInTheDocument();
        expect(screen.getByText('Failed')).toBeInTheDocument();
    });

    it('renders every Domain Control Center section', () => {
        const onChange = vi.fn();
        render(<DomainTabs active="Overview" onChange={onChange} />);
        for (const tab of ['Overview', 'Nameservers', 'DNS', 'Security', 'Renewal', 'Transfers', 'SSL', 'Billing', 'Activity']) expect(screen.getByRole('tab', { name: tab })).toBeInTheDocument();
        expect(screen.queryByRole('tab', { name: 'Contacts' })).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('tab', { name: 'Security' }));
        expect(onChange).toHaveBeenCalledWith('Security');
    });

    it('keeps domain actions local and does not call an API', () => {
        const fetchSpy = vi.spyOn(globalThis, 'fetch');
        render(<DomainQuickActions />);
        fireEvent.click(screen.getByRole('button', { name: 'Renew' }));
        expect(fetchSpy).not.toHaveBeenCalled();
        fetchSpy.mockRestore();
    });
});
