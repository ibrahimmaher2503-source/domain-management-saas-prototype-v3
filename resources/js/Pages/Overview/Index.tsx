import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

type Props = { metrics: { total: number; active: number; expiring: number; transfers: number }; domains: { id: number; name: string; status: string; expires_at: string | null }[] };

export default function Index({ metrics, domains }: Props) {
    const cards = [['Total domains', metrics.total, 'Across your account'], ['Active', metrics.active, 'Ready to manage'], ['Expiring soon', metrics.expiring, 'Within 30 days'], ['Transfers in progress', metrics.transfers, 'Pending or needs action']] as const;
    return <AppLayout title="Overview"><PageHeader eyebrow="Portfolio" title="Overview" description="Your domains, renewals, transfers, and services at a glance." actions={<Link href={route('domains.search')} className="inline-flex min-h-11 items-center rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2">Search domains</Link>} />
        <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{cards.map(([label, value, note]) => <section className="rounded-xl border bg-white p-4" key={label}><p className="text-sm text-neutral-600">{label}</p><p className="mt-2 text-2xl font-semibold tabular-nums">{value}</p><p className="mt-1 text-xs text-neutral-500">{note}</p></section>)}</div>
        {domains.length ? <section className="overflow-hidden rounded-xl border bg-white"><div className="flex items-center justify-between border-b px-5 py-4"><h2 className="font-semibold">Recent domains</h2><Link href={route('domains')} className="min-h-11 py-3 text-sm font-medium text-neutral-600 hover:text-black">View all</Link></div><div className="divide-y">{domains.map(domain => <Link href={route('domains.show', domain.id)} className="flex min-h-16 items-center justify-between gap-4 px-5 py-4 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-black" key={domain.id}><div className="min-w-0"><p className="truncate font-medium">{domain.name}</p><p className="mt-1 text-xs text-neutral-500">{domain.expires_at ? `Expires ${domain.expires_at}` : 'Expiration date unavailable'}</p></div><StatusBadge status={domain.status} /></Link>)}</div></section> : <EmptyState title="No domains yet" description="Search for a domain to start building your portfolio." />}
    </AppLayout>;
}
