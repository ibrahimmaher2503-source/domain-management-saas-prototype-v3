import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const columns: Record<string, [string, string][]> = {
    customers: [['name', 'Customer'], ['email', 'Email'], ['created_at', 'Joined'], ['domains_count', 'Domains'], ['orders_count', 'Orders'], ['paid_payments_count', 'Paid payments']],
    domains: [['name', 'Domain'], ['user.name', 'Customer'], ['status', 'Status'], ['expires_at', 'Expires'], ['provider', 'Provider'], ['provider_synced_at', 'Last synced'], ['dns_zone.status', 'DNS'], ['transfer_locked', 'Transfer lock']],
    orders: [['id', 'Order ID'], ['user.name', 'Customer'], ['type', 'Type'], ['domain', 'Domain'], ['status', 'Status'], ['customer_price', 'Amount'], ['currency', 'Currency'], ['created_at', 'Created'], ['updated_at', 'Updated']],
    payments: [['provider', 'Provider'], ['status', 'Status'], ['user.name', 'Customer'], ['order_id', 'Order'], ['amount', 'Amount'], ['currency', 'Currency'], ['paid_at', 'Paid'], ['failed_at', 'Failed']],
    transfers: [['domain', 'Domain'], ['user.name', 'Customer'], ['direction', 'Direction'], ['status', 'Status'], ['provider_status', 'Provider status'], ['requested_at', 'Requested'], ['provider_synced_at', 'Last synced'], ['completed_at', 'Completed']],
    ssl: [['domain.name', 'Domain'], ['user.name', 'Customer'], ['provider_product', 'Product'], ['status', 'Status'], ['provider_status', 'Provider status'], ['approver_email', 'Approver'], ['issued_at', 'Issued'], ['expires_at', 'Expires'], ['provider_synced_at', 'Last synced']],
};
const routes: Record<string, string> = { customers: 'admin.customers.show', domains: 'admin.domains.show', orders: 'admin.orders.show', transfers: 'admin.transfers.show', ssl: 'admin.ssl.show' };
const dataKeys: Record<string, string> = { customers: 'customers', domains: 'domains', orders: 'orders', payments: 'payments', transfers: 'transfers', ssl: 'certificates' };
const extraFilters: Record<string, string[]> = { domains: ['provider'], orders: ['type', 'from', 'to'], payments: ['provider'], transfers: ['direction'], ssl: ['provider'] };

function value(row: any, path: string) { const found = path.split('.').reduce((v, key) => v?.[key], row); return found === true ? 'Locked' : found === false ? 'Unlocked' : found ?? '—'; }

export default function Table(props: any) {
    const page = props[dataKeys[props.resource]];
    const [filters, setFilters] = useState<Record<string, string>>(props.filters ?? {});
    const customerKey = props.resource === 'customers' || props.resource === 'domains' ? 'search' : 'customer';
    const submit = (event: FormEvent) => { event.preventDefault(); router.get(route(`admin.${props.resource}`), filters, { preserveState: true }); };

    return <AdminLayout title={props.title}>
        <form onSubmit={submit} className="mb-4 flex flex-wrap gap-2 rounded-xl border bg-white p-3">
            <input aria-label="Search or customer" className="min-w-48 rounded-lg border px-3 py-2 text-sm" placeholder="Search / customer" value={filters[customerKey] ?? ''} onChange={e => setFilters({ ...filters, [customerKey]: e.target.value })} />
            {props.resource !== 'customers' && <input aria-label="Status" className="rounded-lg border px-3 py-2 text-sm" placeholder="Status" value={filters.status ?? ''} onChange={e => setFilters({ ...filters, status: e.target.value })} />}
            {(extraFilters[props.resource] ?? []).map(field => <input key={field} aria-label={field} type={['from', 'to'].includes(field) ? 'date' : 'text'} className="rounded-lg border px-3 py-2 text-sm" placeholder={field} value={filters[field] ?? ''} onChange={e => setFilters({ ...filters, [field]: e.target.value })} />)}
            {props.resource === 'domains' && <select aria-label="Expiration" className="rounded-lg border px-3 py-2 text-sm" value={filters.expiration ?? ''} onChange={e => setFilters({ ...filters, expiration: e.target.value })}><option value="">All expiration dates</option><option value="expired">Expired</option><option value="7">Within 7 days</option><option value="30">Within 30 days</option><option value="60">Within 60 days</option><option value="unknown">Unknown</option></select>}
            <button className="rounded-lg bg-black px-4 py-2 text-sm text-white">Apply</button>
        </form>
        <div className="overflow-x-auto rounded-xl border bg-white"><table className="min-w-full text-left text-sm"><thead className="border-b bg-neutral-50 text-xs uppercase text-neutral-500"><tr>{columns[props.resource].map(([, label]) => <th key={label} className="px-4 py-3 font-medium">{label}</th>)}{routes[props.resource] && <th className="px-4 py-3" />}</tr></thead><tbody className="divide-y">{page.data.map((row: any) => <tr key={row.id} className="hover:bg-neutral-50">{columns[props.resource].map(([key]) => <td key={key} className="whitespace-nowrap px-4 py-3">{String(value(row, key))}</td>)}{routes[props.resource] && <td className="px-4 py-3 text-right"><Link className="font-medium underline" href={route(routes[props.resource], row.id)}>View</Link></td>}</tr>)}</tbody></table>{!page.data.length && <p className="p-8 text-center text-sm text-neutral-500">No records match these filters.</p>}</div>
        <div className="mt-4 flex flex-wrap gap-2">{page.links.map((link: any, index: number) => <Link key={index} href={link.url ?? '#'} preserveScroll className={`rounded-lg border px-3 py-2 text-sm ${link.active ? 'bg-black text-white' : 'bg-white'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>
    </AdminLayout>;
}
