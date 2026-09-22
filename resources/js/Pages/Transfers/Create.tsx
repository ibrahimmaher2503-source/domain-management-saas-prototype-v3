import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Quote = { domain: string; customerPrice: string; currency: string; billingData: Record<string, string> };
const fields = ['first_name','last_name','email','phone_number','country','city','street','state','postal_code'];
export default function Create({ quote, error }: { quote: Quote | null; error?: string | null }) {
 const search = useForm({ domain: quote?.domain ?? '' });
 const checkout = useForm<{domain:string;payment_billing:Record<string,string>;transfer?:string}>({ domain: quote?.domain ?? '', payment_billing: Object.fromEntries(fields.map(k => [k, quote?.billingData[k] ?? ''])) });
 const preflight = (e: FormEvent) => { e.preventDefault(); router.get(route('transfers.create'), { domain: search.data.domain }); };
 const submit = (e: FormEvent) => { e.preventDefault(); checkout.post(route('transfers.store')); };
 return <AppLayout title="Transfer Domain"><PageHeader eyebrow="Transfers" title="Transfer Domain" description="Move a .com domain from another registrar to this platform." /><section className="max-w-2xl rounded-xl border border-[#e5e5e5] bg-white p-6"><form onSubmit={preflight} className="flex gap-3"><input value={search.data.domain} onChange={e=>search.setData('domain',e.target.value)} placeholder="example.com" required className="flex-1 rounded-md border border-[#d4d4d4] px-3 py-2"/><button className="rounded-md bg-[#171717] px-4 py-2 text-white">Check transfer</button></form>{error && <p role="alert" className="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-900">{error}</p>}{quote && <form onSubmit={submit} className="mt-6 space-y-4"><div className="rounded-md bg-[#fafafa] p-4"><p className="font-medium">{quote.domain}</p><p className="mt-1 text-sm">Transfer price: {quote.customerPrice} {quote.currency}</p><p className="mt-1 text-xs text-[#737373]">A successful incoming transfer includes a one-year renewal.</p></div><div className="grid gap-3 sm:grid-cols-2">{fields.map(k=><label className="text-sm" key={k}><span className="mb-1 block capitalize">{k.replaceAll('_',' ')}</span><input type={k==='email'?'email':'text'} value={checkout.data.payment_billing[k]} onChange={e=>checkout.setData('payment_billing',{...checkout.data.payment_billing,[k]:e.target.value})} required className="w-full rounded-md border border-[#d4d4d4] px-3 py-2"/></label>)}</div>{checkout.errors.transfer && <p className="text-sm text-red-700">{checkout.errors.transfer}</p>}<button disabled={checkout.processing} className="rounded-md bg-[#171717] px-4 py-3 text-white">Continue to payment</button></form>}</section></AppLayout>;
}
