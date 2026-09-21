import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Placeholder({ title }: { title: string }) {
    return <AuthenticatedLayout title={title}><Head title={title} /><div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-slate-600">{title} is reserved for a later v1 milestone.</div></AuthenticatedLayout>;
}
