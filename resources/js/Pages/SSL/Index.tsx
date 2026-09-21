import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import { certificates } from '@/mocks/certificates';

export default function Index() { return <AppLayout title="SSL"><PageHeader eyebrow="Certificate products" title="SSL" description="Manage certificate product status separately from website HTTPS health." /><div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{certificates.map((item) => <section className="rounded-xl border border-[#e5e5e5] bg-white p-5" key={item.id}><div className="flex items-start justify-between gap-3"><div><h2 className="font-semibold">{item.domain}</h2><p className="mt-1 text-sm text-[#737373]">{item.product}</p></div><StatusBadge status={item.status} /></div><p className="mt-6 text-xs text-[#a3a3a3]">Expiration</p><p className="mt-1 text-sm font-medium">{item.expiresAt}</p><button data-mock-action className="mt-5 rounded-md border border-[#d4d4d4] px-3 py-2 text-sm font-medium">View certificate</button></section>)}</div></AppLayout>; }
