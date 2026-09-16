import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppCard from '@/components/app-card';
import Icon from '@/components/icon';
import { Card } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { cn, routes, useT } from '@/lib/utils';
import type { Application, Category, Post, PostType, SharedProps } from '@/types';

interface Props {
    apps: Application[];
    quickLinks: Application[];
    categories: Category[];
    checkIn: { checkedInAt: string | null; checkedOutAt: string | null } | null;
    today: string;
    news: Post[];
    announcements: Post[];
    billboard: Post[];
    filters: { q: string | null; category: string | null };
}

function Section({
    title,
    count,
    description,
    children,
}: React.PropsWithChildren<{ title: string; count: number; description?: string }>) {
    const [open, setOpen] = useState(true);

    return (
        <section>
            <div className="mb-1 flex items-center justify-between">
                <h2 className="flex items-center gap-2.5 text-lg font-semibold text-ink-900 dark:text-white">
                    {title}
                    <span className="badge bg-ink-100 text-ink-600 dark:bg-white/8 dark:text-ink-300">{count}</span>
                </h2>
                <button type="button" onClick={() => setOpen((value) => !value)} className="rounded-lg p-1.5 text-ink-400 transition hover:text-ink-700 dark:hover:text-white">
                    <Icon name={open ? 'chevron-up' : 'chevron-down'} className="h-5 w-5" />
                </button>
            </div>
            {description && <p className="mb-5 text-sm text-ink-500 dark:text-ink-400">{description}</p>}
            {!description && <div className="mb-4" />}
            {open && children}
        </section>
    );
}

export default function Dashboard({ apps, quickLinks, categories, checkIn, today, news, announcements, billboard, filters }: Props) {
    const t = useT();
    const { auth } = usePage<SharedProps>().props;
    const [tab, setTab] = useState<PostType>('news');

    const collections: Record<PostType, Post[]> = { news, announcement: announcements, billboard };

    const checkInLabel = !checkIn?.checkedInAt
        ? t('Non pointé')
        : !checkIn.checkedOutAt
          ? `${t('Arrivé à')} ${checkIn.checkedInAt}`
          : t('Journée clôturée');

    return (
        <PortalLayout title={t('Mes applications')} categories={categories} filters={filters} alerts={announcements.length} showSearch>
            <div className="grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div className="min-w-0 space-y-10">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h1 className="text-3xl font-semibold tracking-tight text-ink-900 dark:text-white sm:text-4xl">
                                {t('Bon retour,')} <span className="text-brand-600 dark:text-brand-400">{auth.user?.lastname || auth.user?.name}</span>
                            </h1>
                            <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">{t('Accédez à vos applications en un clic.')}</p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <Card className="flex items-center gap-3 px-4 py-3">
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-ink-100 text-ink-500 dark:bg-white/5 dark:text-ink-300">
                                    <Icon name="clock" className="h-5 w-5" />
                                </span>
                                <div className="leading-tight">
                                    <p className="text-sm font-semibold text-ink-800 dark:text-white">{checkInLabel}</p>
                                    <p className="text-[11px] text-ink-400">
                                        {t('Présence')} · {today}
                                    </p>
                                </div>
                                {!checkIn?.checkedOutAt && (
                                    <button
                                        type="button"
                                        onClick={() => router.post(routes.checkIn, {}, { preserveScroll: true })}
                                        className="ml-1 inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                    >
                                        <Icon name="login" className="h-3.5 w-3.5" />
                                        {!checkIn?.checkedInAt ? t('Pointer') : t('Sortir')}
                                    </button>
                                )}
                            </Card>

                            <Card className="flex items-center gap-3 px-4 py-3">
                                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/12 dark:text-brand-300">
                                    <Icon name="layers" className="h-5 w-5" />
                                </span>
                                <div className="leading-tight">
                                    <p className="text-xl font-semibold text-ink-900 dark:text-white">{apps.length}</p>
                                    <p className="text-[11px] text-ink-400">{t('Applications')}</p>
                                </div>
                            </Card>
                        </div>
                    </div>

                    <Section title={t('Mes applications')} count={apps.length}>
                        {apps.length === 0 ? (
                            <Card className="flex flex-col items-center gap-3 px-6 py-14 text-center">
                                <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-ink-100 text-ink-400 dark:bg-white/5">
                                    <Icon name="grid" className="h-6 w-6" />
                                </span>
                                <p className="text-sm font-medium text-ink-700 dark:text-ink-200">{t('Aucune application à afficher')}</p>
                                <p className="max-w-sm text-xs text-ink-400">
                                    {t("Aucune application ne correspond à votre recherche, ou aucun accès ne vous a encore été attribué.")}
                                </p>
                            </Card>
                        ) : (
                            <div className="grid gap-5 sm:grid-cols-2 2xl:grid-cols-3">
                                {apps.map((application) => (
                                    <AppCard key={application.id} application={application} />
                                ))}
                            </div>
                        )}
                    </Section>

                    {quickLinks.length > 0 && (
                        <Section title={t('Liens rapides')} count={quickLinks.length} description={t('Raccourcis vers les services externes du groupe.')}>
                            <div className="grid gap-5 sm:grid-cols-2 2xl:grid-cols-3">
                                {quickLinks.map((link) => (
                                    <AppCard key={link.id} application={link} variant="quick" />
                                ))}
                            </div>
                        </Section>
                    )}
                </div>

                <aside id="centre-information" className="min-w-0">
                    <Card className="sticky top-24 overflow-hidden">
                        <div className="border-b border-ink-100 px-5 pt-5 dark:border-white/10">
                            <p className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-ink-400">
                                {t("Centre d'information")}
                                <span className="h-1.5 w-1.5 rounded-full bg-red-500" />
                            </p>

                            <div className="mt-3 flex gap-1 overflow-x-auto">
                                {(
                                    [
                                        ['news', 'newspaper', t('Actualités')],
                                        ['announcement', 'megaphone', t('Annonces')],
                                        ['billboard', 'clipboard', t('Affichage')],
                                    ] as [PostType, string, string][]
                                ).map(([key, icon, label]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => setTab(key)}
                                        className={cn(
                                            'flex shrink-0 items-center gap-1.5 border-b-2 px-2 pb-2.5 text-[12.5px] font-medium transition',
                                            tab === key
                                                ? 'border-brand-600 text-brand-700 dark:text-brand-300'
                                                : 'border-transparent text-ink-500 hover:text-ink-800 dark:text-ink-400',
                                        )}
                                    >
                                        <Icon name={icon} className="h-4 w-4 shrink-0" />
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="max-h-[calc(100vh-14rem)] space-y-4 overflow-y-auto p-4">
                            {collections[tab].length === 0 && (
                                <p className="px-1 py-10 text-center text-xs text-ink-400">{t('Rien à signaler pour le moment.')}</p>
                            )}

                            {collections[tab].map((post) => (
                                <Link
                                    key={post.id}
                                    href={routes.post(post.slug)}
                                    className="group block overflow-hidden rounded-xl border border-ink-100 transition hover:border-brand-300 hover:shadow-md dark:border-white/10 dark:hover:border-brand-500/40"
                                >
                                    {post.imageUrl && (
                                        <div className="relative aspect-[16/9] overflow-hidden bg-ink-100 dark:bg-white/5">
                                            <img src={post.imageUrl} alt="" className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]" />
                                            {post.isFeatured && <span className="badge absolute left-2.5 top-2.5 bg-white/95 text-brand-700 shadow-sm">{t('À la une')}</span>}
                                        </div>
                                    )}
                                    <div className="p-3.5">
                                        <p className="text-sm font-semibold leading-snug text-ink-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">
                                            {post.title}
                                        </p>
                                        <p className="mt-1.5 line-clamp-2 text-xs leading-relaxed text-ink-500 dark:text-ink-400">{post.excerpt}</p>
                                        <div className="mt-3 flex items-center justify-between text-[11px] text-ink-400">
                                            <span>{post.publishedAtLabel}</span>
                                            <span className="flex items-center gap-1">
                                                <Icon name="eye" className="h-3.5 w-3.5" />
                                                {post.views}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </Card>
                </aside>
            </div>
        </PortalLayout>
    );
}
