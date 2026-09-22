import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState } from 'react';
import { navigationItems } from './AppSidebar';
import Dropdown from './Dropdown';

export default function AppTopbar({ title }: { title: string }) {
    const [open, setOpen] = useState(false);
    const user = usePage().props.auth.user;
    return <><header className="flex h-16 items-center justify-between gap-3 border-b border-[#e5e5e5] bg-white px-4 sm:px-6"><button className="min-h-11 min-w-11 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-black lg:hidden" aria-label="Toggle navigation" aria-expanded={open} onClick={() => setOpen(!open)}>{open ? <X size={20} /> : <Menu size={20} />}</button><h1 className="min-w-0 flex-1 truncate text-lg font-semibold text-[#171717]">{title}</h1><Dropdown><Dropdown.Trigger><button className="min-h-11 max-w-40 truncate rounded-md px-3 text-sm text-neutral-600 hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-black">{user.name}</button></Dropdown.Trigger><Dropdown.Content><Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>{user.is_admin && <Dropdown.Link href={route('admin.overview')}>Admin</Dropdown.Link>}<Dropdown.Link href={route('logout')} method="post" as="button">Log out</Dropdown.Link></Dropdown.Content></Dropdown></header>{open && <nav className="space-y-1 border-b border-[#e5e5e5] bg-white p-4 lg:hidden" aria-label="Mobile navigation">{navigationItems.map(([name, label]) => <Link key={name} href={route(name)} className={`block min-h-11 rounded-md px-3 py-3 text-sm ${route().current(name) ? 'bg-neutral-900 font-medium text-white' : 'text-neutral-700 hover:bg-neutral-100'}`}>{label}</Link>)}{user.is_admin && <Link href={route('admin.overview')} className="block min-h-11 rounded-md px-3 py-3 text-sm font-medium">Admin</Link>}</nav>}</>;
}
