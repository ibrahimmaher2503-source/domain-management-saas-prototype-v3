import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import DomainHeader from '@/Components/Domain/DomainHeader';
import DomainSummary from '@/Components/Domain/DomainSummary';
import DomainQuickActions from '@/Components/Domain/DomainQuickActions';
import DomainTabs, { type DomainTab } from '@/Components/Domain/DomainTabs';
import ContactCard from '@/Components/Domain/ContactCard';
import TransferTimeline from '@/Components/Domain/TransferTimeline';
import StatusBadge from '@/Components/StatusBadge';
import { domainDetails } from '@/mocks/domains';

const panel = 'rounded-xl border border-[#e5e5e5] bg-white p-5';
export default function Show({ domain: domainId }: { domain: number }) {
    const domain = domainDetails[domainId] ?? domainDetails[1];
    const [tab, setTab] = useState<DomainTab>('Overview');
    return <AppLayout title={domain.name}><DomainHeader domain={domain} /><DomainTabs active={tab} onChange={setTab} />
        {tab === 'Overview' && <div className="space-y-5"><DomainSummary domain={domain} /><DomainQuickActions /><section className={panel}><h2 className="font-semibold">Nameservers</h2><div className="mt-3 space-y-2 text-sm">{domain.nameservers.map((ns) => <div className="rounded-md bg-[#fafafa] px-3 py-2 font-mono" key={ns}>{ns}</div>)}</div></section></div>}
        {tab === 'Renewal' && <section className={panel}><h2 className="font-semibold">Renewal</h2><div className="mt-4 grid gap-4 sm:grid-cols-3"><Info label="Expires" value={domain.expiresAt} /><Info label="Time remaining" value="365 days" /><Info label="Auto renew" value={domain.autoRenew ? 'On' : 'Off'} /></div><label className="mt-5 block text-sm font-medium">Renewal period<select className="mt-2 block w-full max-w-xs rounded-md border-[#d4d4d4]"><option>1 year — {domain.renewalPrice}</option><option>2 years — price placeholder</option></select></label><button data-mock-action className="mt-4 rounded-md bg-[#171717] px-4 py-2 text-sm font-medium text-white">Renew domain</button></section>}
        {tab === 'Contacts' && <div className="grid gap-4 md:grid-cols-2"><ContactCard role="Registrant" name={domain.registrant} /><ContactCard role="Administrative" name={domain.administrative} /><ContactCard role="Technical" name={domain.technical} /><ContactCard role="Billing" name={domain.billing} /><button data-mock-action className="rounded-md border border-[#d4d4d4] bg-white p-3 text-sm font-medium md:col-span-2">Change Registrant</button></div>}
        {tab === 'Nameservers' && <div className="space-y-5"><section className={panel}><h2 className="font-semibold">Current nameservers</h2>{domain.nameservers.map((ns) => <p className="mt-3 rounded-md bg-[#fafafa] px-3 py-2 font-mono text-sm" key={ns}>{ns}</p>)}<button data-mock-action className="mt-4 rounded-md border border-[#d4d4d4] px-3 py-2 text-sm font-medium">Change Nameservers</button></section><details className={panel}><summary className="cursor-pointer font-semibold">Registered Hosts / Glue Records</summary><p className="mt-3 text-sm text-[#737373]">No registered hosts in this mock account.</p></details></div>}
        {tab === 'Privacy' && <section className={panel}><h2 className="font-semibold">Domain privacy</h2><div className="mt-3"><StatusBadge status={domain.privacyStatus} /></div><p className="mt-3 text-sm text-[#737373]">Privacy lifecycle dates and supported actions will appear here.</p><button data-mock-action className="mt-4 rounded-md border border-[#d4d4d4] px-3 py-2 text-sm font-medium">Manage Privacy</button></section>}
        {tab === 'Security' && <section className={panel}><h2 className="font-semibold">Security</h2><div className="mt-4 grid gap-4 sm:grid-cols-2"><Info label="Transfer lock" value={domain.transferLocked ? 'Locked' : 'Unlocked'} /><Info label="Authorization code" value="Protected" /></div><div className="mt-4 flex gap-2"><button data-mock-action className="rounded-md border border-[#d4d4d4] px-3 py-2 text-sm font-medium">{domain.transferLocked ? 'Unlock' : 'Lock'}</button><button data-mock-action className="rounded-md border border-[#d4d4d4] px-3 py-2 text-sm font-medium">Get Auth Code</button></div></section>}
        {tab === 'Transfers' && <section className={panel}><h2 className="font-semibold">Transfer state</h2><p className="mt-2 text-sm text-[#737373]">{domain.transferStatus}</p><div className="mt-5"><TransferTimeline /></div></section>}
        {tab === 'SSL' && <section className={panel}><h2 className="font-semibold">SSL certificate product</h2><div className="mt-3"><StatusBadge status={domain.sslStatus} /></div><p className="mt-3 text-sm text-[#737373]">DV Certificate · expires Dec 14, 2026</p><button data-mock-action className="mt-4 rounded-md border border-[#d4d4d4] px-3 py-2 text-sm font-medium">Manage certificate</button></section>}
        {tab === 'Billing' && <section className={panel}><h2 className="font-semibold">Domain billing</h2><div className="mt-4 grid gap-4 sm:grid-cols-2"><Info label="Renewal price" value={domain.renewalPrice} /><Info label="Last charge" value="$14.99 · Paid" /></div></section>}
        {tab === 'Activity' && <section className={panel}><h2 className="font-semibold">Activity</h2><div className="mt-4 space-y-4"><Activity title="Nameservers reviewed" time="12 minutes ago" /><Activity title="Auto-renew confirmed" time="Yesterday" /><Activity title="Domain registered" time={domain.registeredAt} /></div></section>}
    </AppLayout>;
}
function Info({ label, value }: { label: string; value: string }) { return <div><p className="text-xs text-[#737373]">{label}</p><p className="mt-1 font-medium">{value}</p></div>; }
function Activity({ title, time }: { title: string; time: string }) { return <div className="flex items-center justify-between border-b border-[#e5e5e5] pb-4 last:border-0"><p className="font-medium">{title}</p><span className="text-sm text-[#a3a3a3]">{time}</span></div>; }
