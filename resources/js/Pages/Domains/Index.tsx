import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import { Link } from '@inertiajs/react';

type Domain = { id: number; name: string; status: string; expires_at: string | null; nameservers: string[] };

export default function Index({ domains }: { domains: Domain[] }) {
    return <AppLayout title="Domains"><PageHeader eyebrow="Portfolio" title="Domains" description="Your purchased domains." actions={<Link href={route('domains.search')} className="min-h-11 rounded-md bg-[#171717] px-4 py-3 text-sm font-medium text-white">Search domain</Link>} />{domains.length ? <div className="overflow-hidden rounded-xl border border-[#e5e5e5] bg-white"><div className="border-b border-[#e5e5e5] px-5 py-4"><h2 className="font-semibold">Your domains</h2><span className="text-sm text-[#737373]">{domains.length} domain{domains.length === 1 ? '' : 's'}</span></div><div className="overflow-x-auto"><table className="w-full min-w-[680px] text-left text-sm"><thead className="bg-[#fafafa] text-xs uppercase tracking-wide text-[#737373]"><tr>{['Domain', 'Status', 'Expiration', 'Nameservers', ''].map((heading) => <th className="px-5 py-3 font-medium" key={heading}>{heading}</th>)}</tr></thead><tbody className="divide-y divide-[#e5e5e5]">{domains.map((domain) => <tr key={domain.id}><td className="px-5 py-4"><Link href={route('domains.show', domain.id)} className="font-medium hover:underline">{domain.name}</Link></td><td className="px-5 py-4"><StatusBadge status={domain.status}/></td><td className="px-5 py-4 text-[#525252]">{domain.expires_at ?? 'Unknown'}</td><td className="px-5 py-4 text-xs text-[#525252]">{domain.nameservers.join(', ')}</td><td className="px-5 py-4"><Link href={route('domains.show', domain.id)} className="underline">Manage</Link></td></tr>)}</tbody></table></div></div> : <EmptyState title="No domains yet" description="Your purchased domains will appear here." />}</AppLayout>;
}
