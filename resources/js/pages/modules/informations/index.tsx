import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import StatusToggle from '@/components/status-toggle';
import { Card } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { cn, routes, useT } from '@/lib/utils';
import type { Paginated, Post, PostType } from '@/types';

interface Props {
    posts: Paginated<Post>;
    filters: { q: string | null; type: string | null };
    canManage: boolean;
    counts: Record<PostType, number>;
}

export default function Informations({ posts, filters, canManage, counts }: Props) {
    const t = useT();
    const [q, setQ] = useState(filters.q ?? '');

    const rubriques: [PostType | '', string, string][] = [
        ['', 'layers', t('Tout')],
        ['news', 'newspaper', t('Actualités')],
        ['announcement', 'megaphone', t('Annonces')],
        ['billboard', 'clipboard', t('Affichage')],
    ];

    const labels: Record<PostType, string> = {
        news: t('Actualité'),
        announcement: t('Annonce'),
        billboard: t('Affichage'),
    };

    const go = (params: Record<string, string>) =>
        router.get(routes.informations.index, { q, type: filters.type ?? '', ...params }, { preserveState: true, replace: true });

    const search = (event: FormEvent) => {
        event.preventDefault();
        go({});
    };

    return (
        <PortalLayout title={t("Centre d'information")}>
            <Link
                href={routes.dashboard}
                className="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition hover:text-brand-600 dark:text-ink-400"
            >
                <Icon name="chevron-right" className="h-4 w-4 rotate-180" />
                {t('Retour au portail')}
            </Link>

            <div className="mb-8 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-600 dark:text-brand-400">
                        {t('Module du portail')}
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight text-ink-900 dark:text-white sm:text-4xl">
                        {t("Centre d'information")}
                    </h1>
                    <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">
                        {t('Actualités, annonces et affichage du groupe.')}
                    </p>
                </div>

                {canManage && (
                    <Link href={routes.informations.create} className="btn-primary shrink-0">
                        <Icon name="plus" className="h-4 w-4" />
                        {t('Nouvelle publication')}
                    </Link>
                )}
            </div>

            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="flex flex-wrap gap-1.5">
                    {rubriques.map(([value, icon, label]) => (
                        <button
                            key={label}
                            type="button"
                            onClick={() => go({ type: value })}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-2 text-[13px] font-medium transition',
                                (filters.type ?? '') === value
                                    ? 'border-brand-600 bg-brand-600 text-white'
                                    : 'border-ink-200 text-ink-600 hover:border-ink-400 dark:border-white/10 dark:text-ink-300',
                            )}
                        >
                            <Icon name={icon} className="h-4 w-4" />
                            {label}
                            {value !== '' && counts[value as PostType] > 0 && (
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 text-[10px] font-semibold',
                                        (filters.type ?? '') === value ? 'bg-white/20' : 'bg-ink-100 text-ink-500 dark:bg-white/10',
                                    )}
                                >
                                    {counts[value as PostType]}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                <form onSubmit={search} className="relative sm:ml-auto sm:w-72">
                    <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                    <input
                        type="search"
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder={t('Rechercher une publication…')}
                        className="field-input pl-10"
                    />
                </form>
            </div>

            {posts.data.length === 0 ? (
                <Card className="flex flex-col items-center gap-3 px-6 py-16 text-center">
                    <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-ink-100 text-ink-400 dark:bg-white/5">
                        <Icon name="newspaper" className="h-6 w-6" />
                    </span>
                    <p className="text-sm font-medium text-ink-700 dark:text-ink-200">{t('Aucune publication à afficher')}</p>
                    <p className="max-w-sm text-xs text-ink-400">{t('Aucune publication ne correspond à votre recherche.')}</p>
                </Card>
            ) : (
                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    {posts.data.map((post) => (
                        <Card key={post.id} className="group flex flex-col overflow-hidden transition hover:border-brand-300 hover:shadow-lg dark:hover:border-brand-500/40">
                            <Link href={routes.post(post.slug)} className="block">
                                {post.imageUrl ? (
                                    <div className="relative aspect-[16/9] overflow-hidden bg-ink-100 dark:bg-white/5">
                                        <img src={post.imageUrl} alt="" className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]" />
                                        {post.isFeatured && <span className="badge absolute left-2.5 top-2.5 bg-white/95 text-brand-700 shadow-sm">{t('À la une')}</span>}
                                    </div>
                                ) : (
                                    <div className="flex aspect-[16/9] items-center justify-center bg-linear-to-br from-brand-50 to-ink-100 text-brand-300 dark:from-brand-500/10 dark:to-white/5">
                                        <Icon name="newspaper" className="h-10 w-10" />
                                    </div>
                                )}
                            </Link>

                            <div className="flex flex-1 flex-col p-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="badge bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300">{labels[post.type]}</span>

                                    {canManage && (
                                        <StatusToggle
                                            active={post.isVisible}
                                            onToggle={() => router.post(routes.informations.visibility(post.slug), {}, { preserveScroll: true })}
                                        />
                                    )}

                                    {post.visibility === 'draft' && (
                                        <span className="badge bg-amber-50 text-amber-700 dark:bg-amber-500/12 dark:text-amber-300">{t('Brouillon')}</span>
                                    )}
                                    {post.visibility === 'scheduled' && (
                                        <span className="badge bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300">{t('Programmée')}</span>
                                    )}
                                </div>

                                <Link href={routes.post(post.slug)} className="mt-2.5 block">
                                    <p className="text-[15px] font-semibold leading-snug text-ink-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">
                                        {post.title}
                                    </p>
                                </Link>
                                <p className="mt-1.5 line-clamp-2 flex-1 text-[13px] leading-relaxed text-ink-500 dark:text-ink-400">{post.excerpt}</p>

                                <div className="mt-4 flex items-center justify-between border-t border-ink-100 pt-3 text-[11px] text-ink-400 dark:border-white/10">
                                    <span>{post.publishedAtLabel ?? t('Non publiée')}</span>

                                    {canManage ? (
                                        <span className="flex items-center gap-1">
                                            <Link
                                                href={routes.informations.edit(post.slug)}
                                                title={t('Modifier')}
                                                className="rounded-lg p-1.5 text-ink-400 transition hover:bg-ink-100 hover:text-ink-800 dark:hover:bg-white/10"
                                            >
                                                <Icon name="pencil" className="h-4 w-4" />
                                            </Link>
                                            <button
                                                type="button"
                                                title={t('Supprimer')}
                                                onClick={() => {
                                                    if (confirm(t('Supprimer cette publication ?'))) {
                                                        router.delete(routes.informations.destroy(post.slug));
                                                    }
                                                }}
                                                className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                            >
                                                <Icon name="trash" className="h-4 w-4" />
                                            </button>
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1">
                                            <Icon name="eye" className="h-3.5 w-3.5" />
                                            {post.views}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            <Pagination page={posts} />
        </PortalLayout>
    );
}
