import type { PropsWithChildren } from 'react';
import { Head } from '@inertiajs/react';
import AppSidebar from '@/Components/AppSidebar';
import AppTopbar from '@/Components/AppTopbar';

export default function AppLayout({ title, children }: PropsWithChildren<{ title: string }>) { return <><Head title={title} /><div className="min-h-screen bg-[#fafafa] text-[#171717]"><AppSidebar /><div className="lg:pl-[236px]"><AppTopbar title={title} /><main className="mx-auto max-w-[1240px] p-4 sm:p-6">{children}</main></div></div></>; }
