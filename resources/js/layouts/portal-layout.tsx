import { Head, Link, router, usePage } from '@inertiajs/react';
import { type FormEvent, type PropsWithChildren, useEffect, useRef, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import LocaleSwitch from '@/components/locale-switch';
import Logo from '@/components/logo';
import ThemeToggle from '@/components/theme-toggle';
import { cn, routes, useT } from '@/lib/utils';
import type { Category, SharedProps } from '@/types';

interface Props {
    title: string;
    categories?: Category[];
    filters?: { q?: string | null; category?: string | null };
    alerts?: number;
    /** Recherche d'applications dans l'en-tête : réservée au tableau de bord. */
    showSearch?: boolean;
}

export default function PortalLayout({ title, categories = [], filters, alerts = 0, showSearch = false, children }: PropsWithChildren<Props>) {
    const t = useT();
    const { auth, flash } = usePage<SharedProps>().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const [dismissed, setDismissed] = useState(false);
    const [search, setSearch] = useState(filters?.q ?? '');
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) setMenuOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(routes.dashboard, { q: search, category: filters?.category ?? '' }, { preserveState: true, replace: true });
    };

    return (
        <div className="min-h-dvh bg-constellation bg-ink-50 dark:bg-ink-950">
            <Head title={title} />

            <header className="sticky top-0 z-40 border-b border-ink-200/70 bg-white/85 backdrop-blur-md dark:border-white/10 dark:bg-ink-950/85">
                <div className="mx-auto flex h-16 max-w-[1600px] items-center gap-3 px-4 sm:gap-5 sm:px-6 lg:px-8">
                    <Link href={routes.dashboard} className="flex shrink-0 items-center gap-2.5">
                        <Logo size="sm" />
                        <span className="hidden leading-tight sm:block">
                            <span className="block text-[15px] font-semibold text-ink-900 dark:text-white">La Majestueuse</span>
                            <span className="block text-[10px] uppercase tracking-[0.14em] text-ink-400">{t('Portail entreprise')}</span>
                        </span>
                    </Link>

                    {showSearch ? (
                    <form onSubmit={submitSearch} className="ml-auto flex flex-1 items-center gap-2 sm:ml-4 sm:max-w-2xl">
                        <div className="relative flex-1">
                            <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder={t('Rechercher une application…')}
                                className="w-full rounded-full border border-ink-200 bg-ink-50/70 py-2.5 pl-10 pr-4 text-sm text-ink-800 placeholder:text-ink-400 transition focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                        </div>
                        {categories.length > 0 && (
                            <label className="relative hidden sm:block">
                                <select
                                    value={filters?.category ?? ''}
                                    onChange={(event) =>
                                        router.get(routes.dashboard, { q: search, category: event.target.value }, { preserveState: true, replace: true })
                                    }
                                    className="appearance-none rounded-full border border-ink-200 bg-white py-2.5 pl-4 pr-9 text-sm text-ink-700 transition focus:border-brand-500 focus:outline-none dark:border-white/10 dark:bg-white/5 dark:text-ink-200"
                                >
                                    <option value="">{t('Toutes les catégories')}</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.slug}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                <Icon name="chevron-down" className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                            </label>
                        )}
                    </form>
                    ) : (
                        <span className="flex-1" />
                    )}

                    <div className="flex shrink-0 items-center gap-2">
                        <a
                            href="#centre-information"
                            className="relative hidden rounded-full border border-ink-200 bg-white p-2.5 text-ink-500 transition hover:text-brand-600 dark:border-white/10 dark:bg-white/5 dark:text-ink-300 sm:block"
                        >
                            <Icon name="bell" className="h-4 w-4" />
                            {alerts > 0 && (
                                <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                                    {alerts}
                                </span>
                            )}
                        </a>

                        <div className="hidden lg:block">
                            <LocaleSwitch />
                        </div>
                        <div className="hidden xl:block">
                            <ThemeToggle />
                        </div>

                        <div ref={menuRef} className="relative">
                            <button
                                type="button"
                                onClick={() => setMenuOpen((open) => !open)}
                                className="flex items-center gap-2.5 rounded-full border border-ink-200 bg-white py-1.5 pl-1.5 pr-2.5 transition hover:border-ink-300 dark:border-white/10 dark:bg-white/5"
                            >
                                <Avatar url={auth.user?.avatarUrl} initials={auth.user?.initials ?? ''} className="h-8 w-8 text-xs" />
                                <span className="hidden text-left leading-tight md:block">
                                    <span className="block max-w-[9rem] truncate text-[13px] font-semibold text-ink-800 dark:text-white">
                                        {auth.user?.fullName}
                                    </span>
                                    <span className="block text-[10px] uppercase tracking-wide text-ink-400">
                                        {auth.user?.isAdmin ? t('Administrateur') : t('Employé')}
                                    </span>
                                </span>
                                <Icon name="chevron-down" className="h-4 w-4 text-ink-400" />
                            </button>

                            {menuOpen && (
                                <div className="absolute right-0 mt-2 w-64 overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-xl dark:border-white/10 dark:bg-ink-900">
                                    <div className="border-b border-ink-100 px-4 py-3 dark:border-white/10">
                                        <p className="text-sm font-semibold text-ink-900 dark:text-white">{auth.user?.fullName}</p>
                                        <p className="truncate text-xs text-ink-500 dark:text-ink-400">{auth.user?.email ?? auth.user?.poste}</p>
                                        {auth.user?.poste && <p className="mt-1 text-[11px] text-ink-400">{auth.user.poste}</p>}
                                    </div>

                                    {auth.user?.isAdmin && (
                                        <Link
                                            href={routes.admin.dashboard}
                                            className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-700 transition hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-white/5"
                                        >
                                            <Icon name="shield" className="h-4 w-4 text-ink-400" />
                                            {t('Administration du portail')}
                                        </Link>
                                    )}

                                    <div className="flex items-center justify-between gap-2 border-t border-ink-100 px-4 py-3 dark:border-white/10 lg:hidden">
                                        <LocaleSwitch />
                                        <ThemeToggle />
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => router.post(routes.logout)}
                                        className="flex w-full items-center gap-2.5 border-t border-ink-100 px-4 py-2.5 text-sm text-red-600 transition hover:bg-red-50 dark:border-white/10 dark:text-red-400 dark:hover:bg-red-500/10"
                                    >
                                        <Icon name="logout" className="h-4 w-4" />
                                        {t('Se déconnecter')}
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </header>

            {flash.status && !dismissed && (
                <div className="mx-auto mt-4 flex max-w-[1600px] items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-200 sm:mx-6 lg:mx-8">
                    <Icon name="check" className="h-4 w-4 shrink-0" />
                    <span className="flex-1">{flash.status}</span>
                    <button type="button" onClick={() => setDismissed(true)} className="text-emerald-600/70 hover:text-emerald-800">
                        ✕
                    </button>
                </div>
            )}

            <main className={cn('mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8')}>{children}</main>

            <footer className="mx-auto max-w-[1600px] px-4 pb-10 text-center text-xs text-ink-400 sm:px-6 lg:px-8">
                © {new Date().getFullYear()} La Majestueuse · {t('Portail entreprise')} · Yaoundé
            </footer>
        </div>
    );
}
