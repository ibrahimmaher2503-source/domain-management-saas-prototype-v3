import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AdminLayout from '@/Layouts/AdminLayout';
import { Link, useForm } from '@inertiajs/react';

export default function CreateCustomer() {
    const form = useForm({ name: '', email: '' });

    return <AdminLayout title="Create customer">
        <div className="mx-auto max-w-2xl">
            <Link href={route('admin.customers')} className="mb-4 inline-flex min-h-11 items-center text-sm text-neutral-600 hover:text-black focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">Back to customers</Link>
            <form onSubmit={event => { event.preventDefault(); form.post(route('admin.customers.store')); }} className="rounded-xl border bg-white p-5 sm:p-6">
                <h2 className="text-lg font-semibold">Customer details</h2>
                <p className="mt-1 max-w-xl text-sm leading-6 text-neutral-600">The customer will choose their own password through a secure, one-time activation link.</p>
                <div className="mt-6 space-y-5">
                    <div><InputLabel htmlFor="name" value="Name" /><TextInput id="name" className="mt-1 block w-full" value={form.data.name} onChange={e => form.setData('name', e.target.value)} required autoFocus autoComplete="name" /><InputError className="mt-2" message={form.errors.name} /></div>
                    <div><InputLabel htmlFor="email" value="Email" /><TextInput id="email" type="email" className="mt-1 block w-full" value={form.data.email} onChange={e => form.setData('email', e.target.value)} required autoComplete="email" /><InputError className="mt-2" message={form.errors.email} /></div>
                </div>
                <div className="mt-6 flex justify-end"><PrimaryButton disabled={form.processing}>{form.processing ? 'Creating…' : 'Create customer'}</PrimaryButton></div>
            </form>
        </div>
    </AdminLayout>;
}
