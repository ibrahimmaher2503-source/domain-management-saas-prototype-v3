import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type SearchResult = {
    availability: { domain: string; available: boolean; premium: boolean; trademarkClaimRequired: boolean; lookupKey?: string | null };
    providerPrice?: { amount: number; period: number; currency?: string | null } | null;
    customerPrice?: string | null;
    period: number;
    registrationReady?: boolean;
};

type Props = { result?: SearchResult | null; error?: { type: string; message: string } | null; searchedDomain?: string | null };

export default function Search({ result = null, error = null, searchedDomain = null }: Props) {
    const [domain, setDomain] = useState(searchedDomain ?? '');
    const [searching, setSearching] = useState(false);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSearching(true);
        router.get(route('domains.search'), { domain }, { preserveState: true, onFinish: () => setSearching(false) });
    };

    return <AppLayout title="Search domain">
        <Head title="Search domain" />
        <PageHeader eyebrow="Domains" title="Find your next domain" description="Search availability and see the current registrar-backed registration price." />
        <section className="max-w-3xl rounded-xl border border-[#e5e5e5] bg-white p-5 sm:p-7">
            <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                <label htmlFor="domain" className="sr-only">Domain name</label>
                <input id="domain" value={domain} onChange={(event) => setDomain(event.target.value)} placeholder="example.com" className="min-h-11 flex-1 rounded-md border border-[#d4d4d4] px-3 text-sm outline-none focus:border-[#171717] focus:ring-1 focus:ring-[#171717]" />
                <button type="submit" disabled={searching || !domain.trim()} className="min-h-11 rounded-md bg-[#171717] px-5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">{searching ? 'Searching…' : 'Search'}</button>
            </form>
            {error && <div role="alert" className={`mt-5 rounded-lg border p-4 text-sm ${error.type === 'provider' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-800'}`}>{error.message}</div>}
            {result && <div className="mt-6 rounded-lg border border-[#e5e5e5] p-5">
                <div className="flex flex-wrap items-center justify-between gap-3"><div><p className="text-lg font-semibold">{result.availability.domain}</p><p className="mt-1 text-sm text-[#737373]">{result.availability.available ? 'Available' : 'Unavailable'}</p></div>{result.availability.premium && <span className="rounded-full bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700">Premium domain</span>}</div>
                {result.availability.trademarkClaimRequired && <p className="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-900">Additional trademark acknowledgement may be required.</p>}
                {result.availability.available && result.customerPrice !== null && result.customerPrice !== undefined && <div className="mt-5 flex flex-wrap items-end justify-between gap-4"><div><p className="text-2xl font-semibold">{result.customerPrice} / year</p><p className="mt-1 text-xs text-[#737373]">Customer price for {result.period} year{result.period === 1 ? '' : 's'}; purchase flow is not available yet.</p></div><Link href={route('checkout.domain', { domain: result.availability.domain, period: result.period })} aria-disabled={!result.registrationReady} className={`rounded-md border px-4 py-2 text-sm font-medium ${result.registrationReady ? 'border-[#171717] text-[#171717]' : 'pointer-events-none border-[#d4d4d4] text-[#737373]'}`}>Continue</Link></div>}
                {result.availability.available && !result.registrationReady && <p className="mt-4 text-sm text-amber-800">Registration is not enabled for this TLD yet.</p>}
            </div>}
            <Link href={route('domains')} className="mt-6 inline-block text-sm text-[#737373] hover:text-[#171717]">Back to domains</Link>
        </section>
    </AppLayout>;
}
