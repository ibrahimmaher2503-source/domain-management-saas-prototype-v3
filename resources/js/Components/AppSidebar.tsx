import { Link } from '@inertiajs/react';
import { ArrowLeftRight, CreditCard, FileKey2, Globe2, LayoutDashboard, Settings } from 'lucide-react';

export const navigationItems = [
    ['overview', 'Overview', LayoutDashboard], ['domains', 'Domains', Globe2], ['transfers', 'Transfers', ArrowLeftRight], ['ssl', 'SSL', FileKey2], ['billing', 'Billing', CreditCard], ['settings', 'Settings', Settings],
] as const;

export default function AppSidebar() {
    return <aside className="fixed inset-y-0 left-0 hidden w-[236px] border-r border-[#e5e5e5] bg-white p-4 lg:block"><Link href={route('overview')} className="mb-8 block px-3 text-lg font-semibold tracking-tight text-[#171717]">Domain Desk</Link><nav className="space-y-1" aria-label="Customer navigation">{navigationItems.map(([name, label, Icon]) => <Link key={name} href={route(name)} className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ${route().current(name) ? 'bg-[#171717] text-white' : 'text-[#737373] hover:bg-[#fafafa]'}`}><Icon size={17} strokeWidth={1.8} />{label}</Link>)}</nav></aside>;
}
