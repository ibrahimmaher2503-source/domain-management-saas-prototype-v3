import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import AppLayout from '@/Layouts/AppLayout';

type Payment = { id: number; status: string; amount: string; currency: string; paid_at: string | null; created_at: string; order?: { type: string; domain: string } | null };

export default function Index({ payments }: { payments: Payment[] }) {
    return <AppLayout title="Billing"><PageHeader eyebrow="Account" title="Billing" description="Review payments for domains, renewals, transfers, and SSL certificates." />
        {payments.length ? <section className="overflow-hidden rounded-xl border bg-white"><div className="border-b px-5 py-4"><h2 className="font-semibold">Payment history</h2></div><div className="divide-y">{payments.map(payment => <div className="grid gap-3 px-5 py-4 text-sm sm:grid-cols-[1fr_auto_auto] sm:items-center" key={payment.id}><div className="min-w-0"><p className="truncate font-medium">{payment.order?.domain ?? 'Account payment'}</p><p className="text-neutral-600">{payment.order?.type?.replaceAll('_', ' ') ?? 'Payment'} · {new Date(payment.paid_at ?? payment.created_at).toLocaleDateString()}</p></div><p className="font-medium tabular-nums">{payment.amount} {payment.currency}</p><StatusBadge status={payment.status} /></div>)}</div></section> : <EmptyState title="No payments yet" description="Completed and pending payments will appear here." />}
    </AppLayout>;
}
