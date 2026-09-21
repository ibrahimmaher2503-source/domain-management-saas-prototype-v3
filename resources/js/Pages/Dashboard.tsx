import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            title="Overview"
        >
            <Head title="Overview" />

            <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p className="text-sm font-medium text-slate-500">Customer account</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Your domain portfolio starts here.</h1>
                <p className="mt-2 max-w-2xl text-slate-600">The production shell is connected through Laravel, Inertia, React, and TypeScript. Domain operations arrive in later milestones.</p>
            </div>
        </AuthenticatedLayout>
    );
}
