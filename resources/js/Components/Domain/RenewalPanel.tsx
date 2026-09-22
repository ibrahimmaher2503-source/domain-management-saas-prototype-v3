import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Billing = { first_name: string; last_name: string; email: string; phone_number: string; country: string; city: string; street: string; state: string; postal_code: string };
type Quote = { domain: string; period: number; customerPrice: string; currency: string; currentExpirationDate: string | null; billingData: Partial<Billing> };
const empty = (): Billing => ({ first_name: '', last_name: '', email: '', phone_number: '', country: '', city: '', street: '', state: '', postal_code: '' });

export default function RenewalPanel({ domainId, expiresAt, quote, error }: { domainId: number; expiresAt: string | null; quote?: Quote | null; error?: string | null }) {
    const form = useForm({ period: quote?.period ?? 1, payment_billing: { ...empty(), ...(quote?.billingData ?? {}) } });
    const formError = Object.values(form.errors)[0];
    const selectPeriod = (period: number) => router.get(route('domains.show', domainId), { tab: 'renewal', period }, { preserveScroll: true });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post(route('domains.renewal.store', domainId)); };
    const field = (name: keyof Billing, label: string, type = 'text') => <label className="block text-sm"><span className="mb-1 block font-medium">{label}</span><input type={type} required value={form.data.payment_billing[name]} onChange={(event) => form.setData('payment_billing', { ...form.data.payment_billing, [name]: event.target.value })} className="w-full rounded-md border border-[#d4d4d4] px-3 py-2" /></label>;

    return <section className="rounded-xl border border-[#e5e5e5] bg-white p-5">
        <h2 className="font-semibold">Renew domain</h2>
        <p className="mt-2 text-sm text-[#525252]">Current expiration: <strong>{expiresAt ?? 'Unknown'}</strong></p>
        {error && <p role="alert" className="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-900">{error}</p>}
        {!quote && !error && <p className="mt-4 text-sm text-[#737373]">Loading the current registrar renewal price…</p>}
        {quote && <form onSubmit={submit} className="mt-5 space-y-5">
            <label className="block text-sm"><span className="mb-1 block font-medium">Renew for</span><select value={quote.period} onChange={(event) => selectPeriod(Number(event.target.value))} className="rounded-md border border-[#d4d4d4] px-3 py-2">{Array.from({ length: 10 }, (_, index) => index + 1).map((period) => <option key={period} value={period}>{period} year{period === 1 ? '' : 's'}</option>)}</select></label>
            <p className="rounded-md bg-[#fafafa] p-4 text-sm">Current renewal price: <strong>{quote.customerPrice} {quote.currency}</strong></p>
            <div><h3 className="font-medium">Payment billing details</h3><p className="mt-1 text-sm text-[#737373]">Review or update these payment details. They are not registrar contacts.</p><div className="mt-4 grid gap-3 sm:grid-cols-2">{field('first_name', 'First name')}{field('last_name', 'Last name')}{field('email', 'Email', 'email')}{field('phone_number', 'Phone', 'tel')}{field('country', 'Country code')}{field('city', 'City')}{field('street', 'Street')}{field('state', 'State / province')}{field('postal_code', 'Postal code')}</div></div>
            {formError && <p role="alert" className="text-sm text-red-700">{formError}</p>}
            <button type="submit" disabled={form.processing} className="rounded-md bg-[#171717] px-4 py-3 text-sm font-medium text-white disabled:opacity-50">{form.processing ? 'Creating renewal order…' : 'Continue to renewal'}</button>
        </form>}
    </section>;
}
