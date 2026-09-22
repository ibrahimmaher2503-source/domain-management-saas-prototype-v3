import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, BadgeDollarSign, CreditCard, FileKey2, Globe2, LayoutDashboard, Menu, Package, Server, Users, X, ArrowLeftRight } from 'lucide-react';
import { PropsWithChildren, useState } from 'react';
import Dropdown from '@/Components/Dropdown';

const items = [
    ['admin.overview', 'Overview', LayoutDashboard], ['admin.customers', 'Customers', Users], ['admin.domains', 'Domains', Globe2],
    ['admin.orders', 'Orders', Package], ['admin.payments', 'Payments', CreditCard], ['admin.transfers', 'Transfers', ArrowLeftRight],
    ['admin.ssl', 'SSL', FileKey2], ['admin.operations', 'Operations', Activity], ['admin.providers', 'Providers', Server], ['admin.pricing', 'Pricing', BadgeDollarSign],
] as const;

function Navigation() {
    return <nav className="space-y-1" aria-label="Admin navigation">{items.map(([name, label, Icon]) => <Link key={name} href={route(name)} className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ${route().current(`${name}*`) ? 'bg-white text-black' : 'text-neutral-400 hover:bg-neutral-900 hover:text-white'}`}><Icon size={17} />{label}</Link>)}</nav>;
}

export default function AdminLayout({ title, children }: PropsWithChildren<{ title: string }>) {
    const [open, setOpen] = useState(false);
    const user = usePage().props.auth.user;
    return <><Head title={`${title} · Admin`} /><div className="min-h-screen bg-[#f7f7f7] text-[#171717]"><aside className="fixed inset-y-0 left-0 hidden w-60 bg-[#111] p-4 lg:block"><Link href={route('admin.overview')} className="mb-8 block px-3 text-lg font-semibold text-white">Domain Desk <span className="text-xs text-neutral-500">Admin</span></Link><Navigation /><Link href={route('overview')} className="absolute bottom-5 left-7 min-h-11 py-3 text-sm text-neutral-400 hover:text-white">Back to customer app</Link></aside><div className="lg:pl-60"><header className="flex h-16 items-center justify-between gap-3 border-b bg-white px-4 sm:px-6"><button className="min-h-11 min-w-11 rounded-md lg:hidden" aria-label="Toggle admin navigation" onClick={() => setOpen(!open)}>{open ? <X /> : <Menu />}</button><div className="min-w-0 flex-1"><h1 className="truncate font-semibold">{title}</h1><p className="text-xs text-neutral-500">Operations console</p></div><Dropdown><Dropdown.Trigger><button className="min-h-11 rounded-md px-3 text-sm text-neutral-600 hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-black">{user.name}</button></Dropdown.Trigger><Dropdown.Content><Dropdown.Link href={route('overview')}>Customer app</Dropdown.Link><Dropdown.Link href={route('logout')} method="post" as="button">Log out</Dropdown.Link></Dropdown.Content></Dropdown></header>{open && <div className="bg-[#111] p-4 lg:hidden"><Navigation /><div className="mt-4 border-t border-neutral-800 pt-3"><Link href={route('overview')} className="block min-h-11 px-3 py-3 text-sm text-neutral-300">Customer app</Link><Link href={route('logout')} method="post" as="button" className="block min-h-11 w-full px-3 py-3 text-left text-sm text-neutral-300">Log out</Link></div></div>}<main className="mx-auto max-w-[1440px] p-4 sm:p-6">{children}</main></div></div></>;
}
