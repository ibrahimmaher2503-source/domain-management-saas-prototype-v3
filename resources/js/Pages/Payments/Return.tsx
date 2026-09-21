import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

export default function ReturnPage({ state }: { state: 'paid' | 'failed' | 'processing' }) {
    const message = state === 'paid' ? 'Payment confirmed.' : state === 'failed' ? 'Payment was not completed.' : "We're confirming your payment.";
    return <AppLayout title="Payment"><Head title="Payment" /><section className="max-w-xl rounded-xl border border-[#e5e5e5] bg-white p-6"><h1 className="text-xl font-semibold">{message}</h1><p className="mt-3 text-sm text-[#525252]">Your order status is updated from Paymob's verified server callback.</p><Link href={route('domains')} className="mt-6 inline-block text-sm underline">Back to domains</Link></section></AppLayout>;
}
