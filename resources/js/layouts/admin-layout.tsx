import { Head, Link, router, usePage } from '@inertiajs/react';
import { type PropsWithChildren, useEffect, useRef, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import Logo from '@/components/logo';
import ThemeToggle from '@/components/theme-toggle';
import { Alert, ErrorSummary } from '@/components/ui';
import { cn, routes, useT } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Props {
    title: string;
    heading?: string;
    subheading?: string;
}

export default function AdminLayout({ title, heading, subheading, children }: PropsWithChildren<Props>) {
    const t = useT();
    const page = usePage<SharedProps>();
    const { auth, flash, errors } = page.props;
    const url = page.url;
    const [mobileOpen, setMobileOpen] = useState(false);

    const nav: [string, string, string, (path: string) => boolean][] = [
        [routes.admin.dashboard, 'grid', t('Tableau de bord'), (p) => p === '/admin'],
        [routes.admin.applications, 'layers', t('Applications'), (p) => p.startsWith('/admin/applications')],
        [routes.admin.users, 'users', t('Utilisateurs'), (p) => p.startsWith('/admin/users')],
        [routes.admin.modules, 'sliders', t('Modules'), (p) => p.startsWith('/admin/modules')],
        [routes.admin.categories, 'building', t('Catégories'), (p) => p.startsWith('/admin/categories')],
        [routes.admin.posts, 'newspaper', t('Publications'), (p) => p.startsWith('/admin/posts')],
        [routes.admin.email, 'mail', t('Réglages e-mail'), (p) => p.startsWith('/admin/email')],
        [routes.admin.logs, 'shield', t("Journal d'accès"), (p) => p.startsWith('/admin/journal')],
    ];

    const path = url.split('?')[0];

    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const fermer = (evenement: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(evenement.target as Node)) setMenuOpen(false);
        };

        document.addEventListener('mousedown', fermer);

        return () => document.removeEventListener('mousedown', fermer);
    }, []);

    return (
        <div className="flex min-h-dvh bg-ink-50 dark:bg-ink-950">
            <Head title={title} />

            {/*
              * La barre tient toute la hauteur de l'écran et n'accompagne pas
              * le défilement de la page : seule la liste des rubriques défile,
              * de sorte que « Retour au portail » et « Se déconnecter »
              * restent sous les yeux. En absolute dans une barre sans hauteur
              * propre, ils se retrouvaient hors de l'écran.
              */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 flex w-72 shrink-0 flex-col border-r border-ink-200 bg-white transition-transform dark:border-white/10 dark:bg-ink-900 lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                )}
            >
                <div className="flex h-16 shrink-0 items-center gap-2.5 border-b border-ink-100 px-5 dark:border-white/10">
                    <Logo size="sm" />
                </div>

                <nav className="flex-1 space-y-1 overflow-y-auto p-4">
                    {nav.map(([href, icon, label, isActive]) => (
                        <Link
                            key={href}
                            href={href}
                            onClick={() => setMobileOpen(false)}
                            className={cn(
                                'flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition',
                                isActive(path)
                                    ? 'bg-brand-600 text-white shadow-sm shadow-brand-600/25'
                                    : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900 dark:text-ink-300 dark:hover:bg-white/5 dark:hover:text-white',
                            )}
                        >
                            <Icon name={icon} className="h-[18px] w-[18px]" />
                            {label}
                        </Link>
                    ))}
                </nav>

                <div className="shrink-0 space-y-2 border-t border-ink-100 p-4 dark:border-white/10">
                    <Link href={routes.dashboard} className="btn-ghost w-full">
                        <Icon name="arrow-right" className="h-4 w-4 rotate-180" />
                        {t('Retour au portail')}
                    </Link>
                    <button
                        type="button"
                        onClick={() => router.post(routes.logout)}
                        className="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                    >
                        <Icon name="logout" className="h-4 w-4" />
                        {t('Se déconnecter')}
                    </button>
                </div>
            </aside>

            {mobileOpen && <div onClick={() => setMobileOpen(false)} className="fixed inset-0 z-40 bg-ink-950/50 lg:hidden" />}

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-ink-200 bg-white/90 px-4 backdrop-blur-md dark:border-white/10 dark:bg-ink-950/90 sm:px-6">
                    <button type="button" onClick={() => setMobileOpen((open) => !open)} className="rounded-lg p-2 text-ink-500 lg:hidden">
                        <Icon name="grid" className="h-5 w-5" />
                    </button>

                    <div className="min-w-0 flex-1">
                        <h1 className="truncate text-lg font-semibold text-ink-900 dark:text-white">{heading ?? title}</h1>
                        {subheading && <p className="truncate text-xs text-ink-500 dark:text-ink-400">{subheading}</p>}
                    </div>

                    <ThemeToggle />

                    {/*
                      * L'avatar ouvre le menu du compte : retour au portail,
                      * profil et deconnexion. Les deux derniers n'etaient
                      * accessibles qu'en bas de la barre laterale, qu'on ne
                      * voit pas depuis le haut d'une longue page.
                      */}
                    <div ref={menuRef} className="relative">
                        <button
                            type="button"
                            onClick={() => setMenuOpen((ouvert) => !ouvert)}
                            aria-haspopup="menu"
                            aria-expanded={menuOpen}
                            className="flex items-center gap-2.5 rounded-full border border-ink-200 py-1.5 pl-1.5 pr-2.5 transition hover:bg-ink-50 dark:border-white/10 dark:hover:bg-white/5"
                        >
                            <Avatar url={auth.user?.avatarUrl} initials={auth.user?.initials ?? ''} className="h-7 w-7 text-[11px]" />
                            <span className="hidden text-[13px] font-medium text-ink-700 dark:text-ink-200 sm:inline">
                                {auth.user?.fullName}
                            </span>
                            <Icon name="chevron-down" className="h-4 w-4 text-ink-400" />
                        </button>

                        {menuOpen && (
                            <div
                                role="menu"
                                className="absolute right-0 mt-2 w-60 overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-xl dark:border-white/10 dark:bg-ink-900"
                            >
                                <div className="border-b border-ink-100 px-4 py-3 dark:border-white/10">
                                    <p className="truncate text-sm font-semibold text-ink-900 dark:text-white">{auth.user?.fullName}</p>
                                    <p className="truncate text-xs text-ink-500 dark:text-ink-400">
                                        {auth.user?.email ?? auth.user?.poste}
                                    </p>
                                </div>

                                <Link
                                    href={routes.profil.index}
                                    onClick={() => setMenuOpen(false)}
                                    className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-700 transition hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-white/5"
                                >
                                    <Icon name="user" className="h-4 w-4 text-ink-400" />
                                    {t('Mon profil')}
                                </Link>

                                <Link
                                    href={routes.dashboard}
                                    onClick={() => setMenuOpen(false)}
                                    className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-700 transition hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-white/5"
                                >
                                    <Icon name="arrow-right" className="h-4 w-4 rotate-180 text-ink-400" />
                                    {t('Retour au portail')}
                                </Link>

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
                </header>

                <main className="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
                    {flash.status && (
                        <Alert tone="success" icon="check">
                            {flash.status}
                        </Alert>
                    )}
                    <ErrorSummary errors={errors as Record<string, string>} title={t('Veuillez corriger les points suivants :')} />
                    {children}
                </main>
            </div>
        </div>
    );
}
