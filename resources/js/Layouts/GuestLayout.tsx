import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-neutral-50 px-4 pt-8 sm:justify-center sm:pt-0">
            <Link href="/" className="text-lg font-semibold tracking-tight text-neutral-900 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-4">Domain Desk</Link>
            <div className="mt-6 w-full overflow-hidden rounded-xl border bg-white px-6 py-6 sm:max-w-md">
                {children}
            </div>
        </div>
    );
}
