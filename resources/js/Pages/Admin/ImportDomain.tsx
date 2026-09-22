import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AdminLayout from '@/Layouts/AdminLayout';
import { Link, useForm } from '@inertiajs/react';

export default function ImportDomain({ customers, selected_customer_id }: { customers: { id: number; name: string; email: string }[]; selected_customer_id: number | null }) {
    const form = useForm({ domain: '', provider: 'onlinenic', customer_id: selected_customer_id ? String(selected_customer_id) : '', new_customer_name: '', new_customer_email: '', note: '' });
    const creating = form.data.customer_id === '';

    return <AdminLayout title="Import domain">
        <div className="mx-auto max-w-2xl">
            <Link href={route('admin.domains')} className="mb-4 inline-flex min-h-11 items-center text-sm text-neutral-600 hover:text-black focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">Back to domains</Link>
            <form onSubmit={event => { event.preventDefault(); form.post(route('admin.domains.import.store')); }} className="space-y-6 rounded-xl border bg-white p-5 sm:p-6">
                <div><h2 className="text-lg font-semibold">Verify and assign</h2><p className="mt-1 text-sm leading-6 text-neutral-600">Only domains confirmed inside the configured OnlineNIC account can be imported. Verification is read-only.</p></div>
                <div><InputLabel htmlFor="domain" value="Domain" /><TextInput id="domain" className="mt-1 block w-full" placeholder="example.com" value={form.data.domain} onChange={e => form.setData('domain', e.target.value)} required autoFocus /><InputError className="mt-2" message={form.errors.domain} /></div>
                <div><InputLabel htmlFor="provider" value="Provider" /><select id="provider" className="mt-1 block min-h-11 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-black focus:ring-black" value={form.data.provider} disabled><option value="onlinenic">OnlineNIC</option></select><p className="mt-2 text-xs text-neutral-500">External registrars must be transferred to OnlineNIC before full management is enabled.</p></div>
                <div><InputLabel htmlFor="customer_id" value="Customer" /><select id="customer_id" className="mt-1 block min-h-11 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-black focus:ring-black" value={form.data.customer_id} onChange={e => form.setData('customer_id', e.target.value)}><option value="">Create a new customer</option>{customers.map(customer => <option key={customer.id} value={customer.id}>{customer.name} — {customer.email}</option>)}</select><InputError className="mt-2" message={form.errors.customer_id} /></div>
                {creating && <div className="grid gap-5 rounded-lg border bg-neutral-50 p-4 sm:grid-cols-2"><div><InputLabel htmlFor="new_customer_name" value="New customer name" /><TextInput id="new_customer_name" className="mt-1 block w-full" value={form.data.new_customer_name} onChange={e => form.setData('new_customer_name', e.target.value)} required /><InputError className="mt-2" message={form.errors.new_customer_name} /></div><div><InputLabel htmlFor="new_customer_email" value="New customer email" /><TextInput id="new_customer_email" type="email" className="mt-1 block w-full" value={form.data.new_customer_email} onChange={e => form.setData('new_customer_email', e.target.value)} required /><InputError className="mt-2" message={form.errors.new_customer_email} /></div></div>}
                <div><InputLabel htmlFor="note" value="Internal note (optional)" /><textarea id="note" className="mt-1 block min-h-24 w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-black focus:ring-black" value={form.data.note} onChange={e => form.setData('note', e.target.value)} maxLength={500} placeholder="Purchased manually for customer" /><p className="mt-2 text-xs text-neutral-500">Never include passwords, EPP codes, or provider credentials.</p><InputError className="mt-2" message={form.errors.note} /></div>
                <div className="flex justify-end"><PrimaryButton disabled={form.processing}>{form.processing ? 'Verifying…' : 'Verify and import domain'}</PrimaryButton></div>
            </form>
        </div>
    </AdminLayout>;
}
