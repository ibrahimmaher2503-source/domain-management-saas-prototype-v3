import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState } from 'react';
import AppSidebar, { navigationItems } from './AppSidebar';

export default function AppTopbar({ title }: { title: string }) {
    const [open, setOpen] = useState(false);
    const user = usePage().props.auth.user;
    return <><header className="flex h-16 items-center justify-between border-b border-[#e5e5e5] bg-white px-4 sm:px-6"><button className="rounded-md p-2 lg:hidden" aria-label="Toggle navigation" onClick={() => setOpen(!open)}>{open ? <X size={20} /> : <Menu size={20} />}</button><h1 className="text-lg font-semibold text-[#171717]">{title}</h1><span className="text-sm text-[#737373]">{user.name}</span></header>{open && <nav className="space-y-1 border-b border-[#e5e5e5] bg-white p-4 lg:hidden" aria-label="Mobile navigation">{navigationItems.map(([name, label]) => <Link key={name} href={route(name)} className="block rounded-md px-3 py-2 text-sm text-[#525252]">{label}</Link>)}{user.is_admin && <Link href={route('admin.overview')} className="block rounded-md px-3 py-2 text-sm font-medium">Admin</Link>}</nav>}</>;
}
