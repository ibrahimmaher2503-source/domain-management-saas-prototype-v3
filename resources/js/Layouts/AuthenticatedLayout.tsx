import Dropdown from '@/Components/Dropdown';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { CreditCard, FileKey2, Globe2, LayoutDashboard, Settings, ArrowLeftRight, Menu, X } from 'lucide-react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function Authenticated({
    title,
    header,
    children,
}: PropsWithChildren<{ title?: string; header?: ReactNode }>) {
    const user = usePage().props.auth.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    const nav = [
        ['dashboard', 'Overview', LayoutDashboard], ['domains', 'Domains', Globe2], ['transfers', 'Transfers', ArrowLeftRight],
        ['ssl', 'SSL', FileKey2], ['billing', 'Billing', CreditCard], ['settings', 'Settings', Settings],
    ] as const;
    return (
        <div className="min-h-screen bg-slate-50 text-slate-950">
            <aside className="fixed inset-y-0 left-0 hidden w-64 border-r border-slate-200 bg-white p-4 lg:block">
                <Link href={route('dashboard')} className="mb-8 block px-3 text-lg font-semibold tracking-tight">Domain Desk</Link>
                <nav className="space-y-1" aria-label="Customer navigation">
                    {nav.map(([name, label, Icon]) => <Link key={name} href={route(name)} className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ${route().current(name) ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}`}><Icon size={17} />{label}</Link>)}
                </nav>
                <div className="absolute bottom-4 left-4 right-4 border-t border-slate-200 pt-4 text-sm text-slate-500">{user.name}</div>
            </aside>
            <div className="lg:pl-64">
                <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                    <button className="rounded-md p-2 lg:hidden" aria-label="Toggle navigation" onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}>{showingNavigationDropdown ? <X size={20} /> : <Menu size={20} />}</button>
                    <h1 className="text-lg font-semibold">{title ?? 'Account'}</h1>
                    <Dropdown><Dropdown.Trigger><button className="rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">{user.name}</button></Dropdown.Trigger><Dropdown.Content><Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link><Dropdown.Link href={route('logout')} method="post" as="button">Log out</Dropdown.Link></Dropdown.Content></Dropdown>
                </header>
                {showingNavigationDropdown && <nav className="space-y-1 border-b border-slate-200 bg-white p-4 lg:hidden">{nav.map(([name, label]) => <ResponsiveNavLink key={name} href={route(name)} active={route().current(name)}>{label}</ResponsiveNavLink>)}</nav>}
                <main className="mx-auto max-w-7xl p-4 sm:p-6">{header && <div className="mb-6">{header}</div>}{children}</main>
            </div>
        </div>
    );
}
