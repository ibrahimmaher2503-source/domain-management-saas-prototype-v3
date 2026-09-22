import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SecurityPanel from '@/Components/Domain/SecurityPanel';
import { domainTabs } from '@/Components/Domain/DomainTabs';

const { put } = vi.hoisted(() => ({ put: vi.fn() }));
vi.stubGlobal('route', (name: string) => `/${name}`);
vi.mock('@inertiajs/react', () => ({ router: { put } }));

describe('domain security', () => {
    afterEach(cleanup);
    beforeEach(() => {
        put.mockReset();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ auth_code: 'EPP-SECRET' }) }));
        Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: vi.fn() } });
    });

    it('shows tri-state transfer lock controls and keeps Contacts absent', () => {
        const { rerender } = render(<SecurityPanel domainId={1} transferLocked={null} />);
        expect(screen.getByText('Unknown')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Lock Domain' }));
        expect(put).toHaveBeenCalledWith('/domains.security.transfer-lock', { locked: true }, { preserveScroll: true });
        rerender(<SecurityPanel domainId={1} transferLocked={true} />);
        expect(screen.getByText('Locked')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Unlock Domain' })).toBeInTheDocument();
        expect(domainTabs).not.toContain('Contacts');
    });

    it('requires a password, hides the code by default, and clears it on close', async () => {
        render(<SecurityPanel domainId={1} transferLocked={false} />);
        expect(screen.queryByText('EPP-SECRET')).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Reveal Auth Code' }));
        const password = screen.getByLabelText('Current account password');
        expect(password).toBeRequired();
        fireEvent.change(password, { target: { value: 'correct-password' } });
        fireEvent.click(screen.getByRole('button', { name: 'Reveal code' }));
        expect(await screen.findByText('EPP-SECRET')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Close' }));
        await waitFor(() => expect(screen.queryByText('EPP-SECRET')).not.toBeInTheDocument());
        fireEvent.click(screen.getByRole('button', { name: 'Reveal Auth Code' }));
        expect(screen.queryByText('EPP-SECRET')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Current account password')).toHaveValue('');
    });
});
