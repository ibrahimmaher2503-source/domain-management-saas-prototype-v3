import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export default function SecurityPanel({ domainId, transferLocked, pending = false }: { domainId: number; transferLocked: boolean | null; pending?: boolean }) {
    const [open, setOpen] = useState(false);
    const [password, setPassword] = useState('');
    const [authCode, setAuthCode] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const close = () => { setOpen(false); setPassword(''); setAuthCode(''); setError(''); };
    const reveal = async (event: FormEvent) => {
        event.preventDefault();
        setLoading(true); setError(''); setAuthCode('');
        try {
            const response = await fetch(route('domains.security.auth-code', domainId), { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '' }, body: JSON.stringify({ password }) });
            const body = await response.json();
            if (response.ok) { setAuthCode(body.auth_code); setPassword(''); } else { setError(body.errors?.password?.[0] ?? body.message ?? 'Transfer code could not be retrieved.'); }
        } catch {
            setError('Transfer code could not be retrieved.');
        } finally {
            setLoading(false);
        }
    };

    return <section className="space-y-5">
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5">
            <h2 className="font-semibold">Transfer Lock</h2>
            <p className="mt-1 text-sm text-[#737373]">Protects the domain from unauthorized registrar transfers.</p>
            <p className="mt-4 text-sm">Status: <strong>{transferLocked === null ? 'Unknown' : transferLocked ? 'Locked' : 'Unlocked'}</strong></p>
            {pending && <p className="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-900">The registrar is still confirming this security change.</p>}
            <button type="button" disabled={pending} onClick={() => router.put(route('domains.security.transfer-lock', domainId), { locked: transferLocked !== true }, { preserveScroll: true })} className="mt-4 rounded-md bg-[#171717] px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{transferLocked ? 'Unlock Domain' : 'Lock Domain'}</button>
        </div>
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5">
            <h2 className="font-semibold">Transfer Code</h2>
            <p className="mt-1 text-sm text-[#737373]">Used when transferring this domain to another registrar.</p>
            <button type="button" onClick={() => setOpen(true)} className="mt-4 rounded-md border border-[#d4d4d4] px-4 py-2 text-sm font-medium">Reveal Auth Code</button>
        </div>
        {open && <div role="dialog" aria-modal="true" aria-label="Reveal Auth Code" className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"><div className="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <h3 className="font-semibold">Confirm your password</h3>
            {!authCode ? <form onSubmit={reveal} className="mt-4 space-y-4"><label className="block text-sm"><span className="mb-1 block font-medium">Current account password</span><input type="password" autoComplete="current-password" required value={password} onChange={(event) => setPassword(event.target.value)} className="w-full rounded-md border border-[#d4d4d4] px-3 py-2" /></label>{error && <p role="alert" className="text-sm text-red-700">{error}</p>}<button disabled={loading} className="rounded-md bg-[#171717] px-4 py-2 text-sm text-white disabled:opacity-50">{loading ? 'Retrieving…' : 'Reveal code'}</button></form> : <div className="mt-4"><p className="text-sm text-[#737373]">Transfer code</p><code className="mt-2 block break-all rounded-md bg-[#fafafa] p-3">{authCode}</code><button type="button" onClick={() => navigator.clipboard.writeText(authCode)} className="mt-3 rounded-md border px-3 py-2 text-sm">Copy</button></div>}
            <button type="button" onClick={close} className="mt-4 text-sm underline">Close</button>
        </div></div>}
    </section>;
}
