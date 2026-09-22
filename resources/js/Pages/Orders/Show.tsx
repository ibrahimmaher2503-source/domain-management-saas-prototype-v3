import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, Link } from '@inertiajs/react';
import { FormEvent } from 'react';

type Order = { id: number; type: 'domain_registration' | 'domain_renewal'; domain: string; status: string; registration_period: number; customer_price: string; currency: string; nameservers: string[]; payment_error?: string; provisioning_failure_reason?: string; domain_id?: number; requires_reconciliation?: boolean };

export default function Show({ order }: { order: Order }) {
    const pay = (event: FormEvent) => { event.preventDefault(); (document.getElementById('pay-order') as HTMLFormElement)?.submit(); };
    const renewal = order.type === 'domain_renewal';
    const copy = order.requires_reconciliation ? [renewal ? 'Confirming renewal' : 'Confirming registration', renewal ? "We're confirming the renewal status with the registrar." : "We're confirming the registration status with the registrar."] : renewal ? {
        awaiting_payment: ['Awaiting Payment', `Complete payment to renew ${order.domain}.`],
        paid: ['Paid', 'Payment confirmed. Your domain renewal is being processed.'],
        provisioning: ['Renewing', 'Payment confirmed. Your domain renewal is being processed.'],
        completed: ['Completed', 'Domain renewed successfully.'],
        failed: ['Renewal failed', 'Payment was successful, but the renewal could not be completed. This order requires review.'],
    }[order.status] ?? ['Processing', "We're confirming the renewal status with the registrar."] : {
        awaiting_payment: ['Awaiting Payment', 'Payment is required before registration.'],
        paid: ['Paid', 'Payment confirmed. Your domain is being registered.'],
        provisioning: ['Registering', 'Registering your domain…'],
        completed: ['Completed', 'Domain registered successfully.'],
        failed: ['Registration failed', `${order.provisioning_failure_reason ?? 'Payment was successful, but the domain could not be registered.'} Your payment is safe. This order requires review.`],
    }[order.status] ?? ['Processing', "We're confirming the registration status with the registrar."];

    return <AppLayout title="Order"><Head title={`Order ${order.domain}`} /><PageHeader eyebrow="Order" title={order.domain} description={renewal ? 'Domain renewal order' : 'Domain registration order'} /><section className="max-w-3xl rounded-xl border border-[#e5e5e5] bg-white p-5 sm:p-7"><div className="flex flex-wrap items-center justify-between gap-3"><div><p className="text-sm text-[#737373]">Status</p><p className="mt-1 font-semibold">{copy[0]}</p></div><span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">{copy[0]}</span></div><dl className="mt-6 grid gap-4 text-sm sm:grid-cols-2"><div><dt className="text-[#737373]">{renewal ? 'Renewal period' : 'Registration period'}</dt><dd className="mt-1 font-medium">{order.registration_period} year{order.registration_period === 1 ? '' : 's'}</dd></div><div><dt className="text-[#737373]">Total</dt><dd className="mt-1 font-medium">{order.customer_price} {order.currency}</dd></div>{!renewal && <div><dt className="text-[#737373]">Nameservers</dt><dd className="mt-1 font-medium">{order.nameservers.join(', ')}</dd></div>}</dl>{order.payment_error && <p role="alert" className="mt-6 rounded-md bg-red-50 p-4 text-sm text-red-800">{order.payment_error}</p>}<p className={`mt-6 rounded-md p-4 text-sm ${order.status === 'failed' ? 'bg-red-50 text-red-800' : order.status === 'completed' ? 'bg-emerald-50 text-emerald-800' : 'bg-[#fafafa] text-[#525252]'}`}>{copy[1]}</p>{order.status === 'awaiting_payment' && <form id="pay-order" method="post" action={route('orders.pay', order.id)} onSubmit={pay}><input type="hidden" name="_token" value={(document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? ''} /><button type="submit" className="mt-6 rounded-md bg-[#171717] px-4 py-3 text-sm font-medium text-white">Pay Now</button></form>}{order.status === 'completed' && order.domain_id && <Link href={route('domains.show', order.domain_id)} className="mt-6 inline-block rounded-md bg-[#171717] px-4 py-3 text-sm font-medium text-white">Manage Domain</Link>}<Link href={route('domains')} className="mt-6 ml-3 inline-block text-sm text-[#737373]">Back to domains</Link></section></AppLayout>;
}
