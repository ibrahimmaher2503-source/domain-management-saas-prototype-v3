import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';
import Search from '@/Pages/Domains/Search';

vi.stubGlobal('route', (name: string) => `/${name}`);
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/react')>('@inertiajs/react');
    return { ...actual, router: { get: vi.fn() }, Head: ({ children }: { children?: ReactNode }) => <>{children}</> };
});

describe('domain search', () => {
    it('renders the search input and available result', () => {
        render(<Search result={{ availability: { domain: 'example.com', available: true, premium: false, trademarkClaimRequired: false }, customerPrice: '10.31', period: 1, registrationReady: true }} />);
        expect(screen.getByPlaceholderText('example.com')).toBeInTheDocument();
        expect(screen.getByText('Available')).toBeInTheDocument();
        expect(screen.getByText('10.31 / year')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Continue' })).toBeInTheDocument();
    });

    it('renders premium and provider error states', () => {
        const { rerender } = render(<Search result={{ availability: { domain: 'premium.com', available: true, premium: true, trademarkClaimRequired: true }, customerPrice: '20.00', period: 1 }} />);
        expect(screen.getByText('Premium domain')).toBeInTheDocument();
        expect(screen.getByText(/trademark acknowledgement/i)).toBeInTheDocument();
        rerender(<Search error={{ type: 'provider', message: 'Domain search is temporarily unavailable. Please try again.' }} />);
        expect(screen.getByRole('alert')).toHaveTextContent('temporarily unavailable');
    });

    it('does not render cart or checkout actions', () => {
        render(<Search />);
        expect(screen.queryByText(/cart|checkout/i)).not.toBeInTheDocument();
        fireEvent.change(screen.getAllByPlaceholderText('example.com')[0], { target: { value: 'example.com' } });
        expect(screen.getByDisplayValue('example.com')).toBeInTheDocument();
    });
});
