import AppLayout from '@/Layouts/AppLayout';
import DomainTabs, { type DomainTab } from '@/Components/Domain/DomainTabs';
import PageHeader from '@/Components/PageHeader';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

type Domain = { id: number; name: string; status: string; registered_at: string | null; expires_at: string | null; nameservers: string[]; provider_status: string | null };

export default function Show({ domain }: { domain: Domain }) {
    const [tab, setTab] = useState<DomainTab>('Overview');

    return <AppLayout title={domain.name}>
        <Head title={domain.name} />
        <PageHeader eyebrow="Domain Control Center" title={domain.name} description="Your domain and its available management areas." />
        <div className="max-w-4xl">
            <DomainTabs active={tab} onChange={setTab} />
            {tab === 'Overview' && <section className="rounded-xl border border-[#e5e5e5] bg-white p-5"><h2 className="font-semibold">Domain overview</h2><dl className="mt-5 grid gap-4 text-sm sm:grid-cols-2"><div><dt className="text-[#737373]">Lifecycle status</dt><dd className="mt-1 font-medium">{domain.status}</dd></div><div><dt className="text-[#737373]">Provider status</dt><dd className="mt-1 font-medium">{domain.provider_status ?? 'Unknown'}</dd></div><div><dt className="text-[#737373]">Registered</dt><dd className="mt-1 font-medium">{domain.registered_at ?? 'Unknown'}</dd></div><div><dt className="text-[#737373]">Expires</dt><dd className="mt-1 font-medium">{domain.expires_at ?? 'Unknown'}</dd></div></dl></section>}
            {tab === 'Nameservers' && <section className="rounded-xl border border-[#e5e5e5] bg-white p-5"><h2 className="font-semibold">Nameservers</h2><p className="mt-1 text-sm text-[#737373]">Nameservers control where your domain is delegated. They are not DNS records.</p><div className="mt-4 space-y-2 text-sm">{domain.nameservers.map((nameserver) => <p key={nameserver} className="rounded-md bg-[#fafafa] px-3 py-2 font-mono">{nameserver}</p>)}</div></section>}
            {tab === 'DNS' && <section className="rounded-xl border border-[#e5e5e5] bg-white p-5"><h2 className="font-semibold">DNS records</h2><p className="mt-2 text-sm text-[#525252]">DNS zone management is not connected yet. This area will manage records such as A, AAAA, CNAME, MX, TXT, SRV, and CAA—not nameserver delegation.</p></section>}
            {!['Overview', 'Nameservers', 'DNS'].includes(tab) && <section className="rounded-xl border border-[#e5e5e5] bg-white p-5"><h2 className="font-semibold">{tab}</h2><p className="mt-2 text-sm text-[#525252]">Management will be available in a later milestone.</p></section>}
            <Link href={route('domains')} className="mt-5 inline-block text-sm underline">Back to My Domains</Link>
        </div>
    </AppLayout>;
}
