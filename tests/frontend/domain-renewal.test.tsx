import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import RenewalPanel from '@/Components/Domain/RenewalPanel';
import { domainTabs } from '@/Components/Domain/DomainTabs';

const { get, post } = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.stubGlobal('route', (name: string) => `/${name}`);
vi.mock('@inertiajs/react', () => ({
    router: { get },
    useForm: (data: unknown) => ({ data, setData: vi.fn(), post, processing: false, errors: {} }),
}));

const quote = { domain: 'example.com', period: 1, customerPrice: '10.31', currency: 'EGP', currentExpirationDate: '2027-09-22', billingData: { first_name: 'Billing', last_name: 'Customer', email: 'payer@example.com', phone_number: '+201000000000', country: 'EG', city: 'Cairo', street: '1 Main', state: 'Cairo', postal_code: '11511' } };

describe('domain renewal', () => {
    afterEach(cleanup);
    it('shows real expiration, quoted price, billing data, and no Contacts tab', () => {
        render(<RenewalPanel domainId={1} expiresAt="2027-09-22" quote={quote} />);
        expect(screen.getByText('2027-09-22')).toBeInTheDocument();
        expect(screen.getByText(/10.31 EGP/)).toBeInTheDocument();
        expect(screen.getByDisplayValue('payer@example.com')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Continue to renewal' })).toBeInTheDocument();
        expect(domainTabs).toContain('DNS');
        expect(domainTabs).toContain('Security');
        expect(domainTabs).not.toContain('Contacts');
    });

    it('requests a fresh provider quote when the period changes', () => {
        render(<RenewalPanel domainId={1} expiresAt="2027-09-22" quote={quote} />);
        fireEvent.change(screen.getByRole('combobox'), { target: { value: '2' } });
        expect(get).toHaveBeenCalledWith('/domains.show', { tab: 'renewal', period: 2 }, { preserveScroll: true });
    });
});
