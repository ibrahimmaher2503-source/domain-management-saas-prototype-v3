import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Link } from '@inertiajs/react';

export default function Index() { return <AppLayout title="Settings"><PageHeader eyebrow="Customer account" title="Settings" description="Manage your profile and account password." /><div className="grid gap-4 md:grid-cols-2">{[['Profile', 'Update your name and email address.'], ['Password', 'Choose a strong account password.']].map(([title, copy]) => <section className="rounded-xl border bg-white p-5" key={title}><h2 className="font-semibold">{title}</h2><p className="mt-2 min-h-10 text-sm leading-6 text-neutral-600">{copy}</p><Link href={route('profile.edit')} className="mt-5 inline-flex min-h-11 items-center rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2">Manage {title.toLowerCase()}</Link></section>)}</div></AppLayout>; }
