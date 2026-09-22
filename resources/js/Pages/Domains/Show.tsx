import AppLayout from '@/Layouts/AppLayout';
import DomainTabs, { type DomainTab } from '@/Components/Domain/DomainTabs';
import PageHeader from '@/Components/PageHeader';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

type Domain = { id: number; name: string; status: string; registered_at: string | null; expires_at: string | null; nameservers: string[]; provider_status: string | null; provider_synced_at?: string | null };
type Activity = { id: number; label: string; status: string; at: string | null };

export default function Show({ domain, activity = [], notice, error }: { domain: Domain; activity?: Activity[]; notice?: string | null; error?: string | null }) {
    const [tab, setTab] = useState<DomainTab>('Overview');
    const [editing, setEditing] = useState(false);
    const [nameservers, setNameservers] = useState(domain.nameservers.length >= 2 ? domain.nameservers : ['', '']);
    const [confirmed, setConfirmed] = useState(false);
    const [formError, setFormError] = useState('');

    useEffect(() => { setNameservers(domain.nameservers.length >= 2 ? domain.nameservers : ['', '']); }, [domain.nameservers]);

    const cancel = () => { setNameservers(domain.nameservers.length >= 2 ? domain.nameservers : ['', '']); setEditing(false); setConfirmed(false); setFormError(''); };
    const save = (event: FormEvent) => {
        event.preventDefault();
        setFormError('');
        router.put(route('domains.nameservers.update', domain.id), { nameservers }, { preserveScroll: true, onError: (errors) => setFormError(errors.nameservers ?? Object.values(errors)[0] ?? 'Please check the nameservers.'), onSuccess: () => { setEditing(false); setConfirmed(false); } });
    };

    return <AppLayout title={domain.name}>
        <Head title={domain.name} />
        <PageHeader eyebrow="Domain Control Center" title={domain.name} description="Current registrar-backed domain information." />
        <div className="max-w-4xl">
            {notice && <p role="status" className="mb-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800">{notice}</p>}
            {error && <p role="alert" className="mb-4 rounded-md bg-amber-50 p-3 text-sm text-amber-900">{error}</p>}
            <DomainTabs active={tab} onChange={setTab} />
            {tab === 'Overview' && <section className="rounded-xl border border-[#e5e5e5] bg-white p-5">
                <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-semibold">Domain overview</h2><button type="button" onClick={() => router.post(route('domains.sync', domain.id), {}, { preserveScroll: true })} className="rounded-md border border-[#d4d4d4] px-3 py-2 text-sm font-medium">Refresh domain</button></div>
                <dl className="mt-5 grid gap-4 text-sm sm:grid-cols-2"><div><dt className="text-[#737373]">Domain</dt><dd className="mt-1 font-medium">{domain.name}</dd></div><div><dt className="text-[#737373]">Lifecycle status</dt><dd className="mt-1 font-medium">{domain.status}</dd></div><div><dt className="text-[#737373]">Registered</dt><dd className="mt-1 font-medium">{domain.registered_at ?? 'Unknown'}</dd></div><div><dt className="text-[#737373]">Expires</dt><dd className="mt-1 font-medium">{domain.expires_at ?? 'Unknown'}</dd></div><div><dt className="text-[#737373]">Provider status</dt><dd className="mt-1 font-medium">{domain.provider_status ?? 'Unknown'}</dd></div><div><dt className="text-[#737373]">Last synced</dt><dd className="mt-1 font-medium">{domain.provider_synced_at ? new Date(domain.provider_synced_at).toLocaleString() : 'Not synced yet'}</dd></div><div className="sm:col-span-2"><dt className="text-[#737373]">Current nameservers</dt><dd className="mt-1 font-medium">{domain.nameservers.length ? domain.nameservers.join(', ') : 'Unknown'}</dd></div></dl>
            </section>}
            {tab === 'Nameservers' && <section className="rounded-xl border border-[#e5e5e5] bg-white p-5">
                <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-semibold">Current Nameservers</h2>{!editing && <button type="button" onClick={() => setEditing(true)} className="rounded-md border border-[#d4d4d4] px-3 py-2 text-sm font-medium">Edit Nameservers</button>}</div>
                <p className="mt-1 text-sm text-[#737373]">Nameservers control where your domain is delegated. They are not DNS records.</p>
                {!editing ? <div className="mt-4 space-y-2 text-sm">{domain.nameservers.map((name) => <p key={name} className="rounded-md bg-[#fafafa] px-3 py-2 font-mono">{name}</p>)}</div> : <form onSubmit={save} className="mt-5 space-y-4">
                    {nameservers.map((name, index) => <div key={index} className="flex items-end gap-2"><label className="flex-1 text-sm"><span className="mb-1 block font-medium">Nameserver {index + 1}</span><input value={name} onChange={(event) => setNameservers(nameservers.map((entry, i) => i === index ? event.target.value : entry))} required className="w-full rounded-md border border-[#d4d4d4] px-3 py-2" /></label>{nameservers.length > 2 && <button type="button" onClick={() => setNameservers(nameservers.filter((_, i) => i !== index))} className="rounded-md border border-[#d4d4d4] px-3 py-2 text-sm">Remove</button>}</div>)}
                    {nameservers.length < 6 && <button type="button" onClick={() => setNameservers([...nameservers, ''])} className="text-sm underline">+ Add Nameserver</button>}
                    <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-900">Changing nameservers changes where this domain resolves. DNS services at the previous provider may stop working.</p>
                    <label className="flex items-start gap-2 text-sm"><input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} className="mt-1" /><span>I understand this change may affect website and email availability.</span></label>
                    {formError && <p role="alert" className="text-sm text-red-700">{formError}</p>}
                    <div className="flex gap-3"><button type="submit" disabled={!confirmed} className="rounded-md bg-[#171717] px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Save Changes</button><button type="button" onClick={cancel} className="rounded-md border border-[#d4d4d4] px-4 py-2 text-sm">Cancel</button></div>
                </form>}
            </section>}
            {tab === 'DNS' && <section className="rounded-xl border border-[#e5e5e5] bg-white p-5"><h2 className="font-semibold">DNS Records</h2><p className="mt-2 text-sm font-medium">Status: Not connected</p><p className="mt-2 text-sm text-[#525252]">DNS zone management is not connected yet. Nameservers control where your domain's DNS is hosted. DNS records such as A, AAAA, CNAME, MX and TXT will be managed here once a DNS provider is connected.</p></section>}
            {tab === 'Activity' && <section className="rounded-xl border border-[#e5e5e5] bg-white p-5"><h2 className="font-semibold">Activity</h2>{activity.length ? <ol className="mt-4 divide-y divide-[#e5e5e5]">{activity.map((item) => <li key={item.id} className="flex justify-between gap-3 py-3 text-sm"><span>{item.label}</span><time className="text-[#737373]">{item.at ? new Date(item.at).toLocaleString() : 'Pending'}</time></li>)}</ol> : <p className="mt-2 text-sm text-[#737373]">No domain activity yet.</p>}</section>}
            {!['Overview', 'Nameservers', 'DNS', 'Activity'].includes(tab) && <section className="rounded-xl border border-[#e5e5e5] bg-white p-5"><h2 className="font-semibold">{tab}</h2><p className="mt-2 text-sm text-[#525252]">Management will be available in a later milestone.</p></section>}
            <Link href={route('domains')} className="mt-5 inline-block text-sm underline">Back to My Domains</Link>
        </div>
    </AppLayout>;
}
