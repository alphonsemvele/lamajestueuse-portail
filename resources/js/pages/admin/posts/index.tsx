import { Link, router } from '@inertiajs/react';
import Icon from '@/components/icon';
import StatusToggle from '@/components/status-toggle';
import Pagination from '@/components/pagination';
import { Card } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { routes, useT } from '@/lib/utils';
import type { Paginated, Post } from '@/types';

export default function PostsIndex({ posts }: { posts: Paginated<Post> }) {
    const t = useT();
    const labels: Record<Post['type'], string> = { news: t('Actualité'), announcement: t('Annonce'), billboard: t('Affichage') };

    return (
        <AdminLayout title={t('Publications')} subheading={t('Actualités, annonces et affichage du centre d’information.')}>
            <div className="flex justify-end">
                <Link href={routes.admin.postCreate} className="btn-primary">
                    <Icon name="plus" className="h-4 w-4" />
                    {t('Nouvelle publication')}
                </Link>
            </div>

            <Card className="overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="border-b border-ink-100 bg-ink-50/70 text-[11px] uppercase tracking-[0.07em] text-ink-500 dark:border-white/10 dark:bg-white/5">
                            <tr>
                                <th className="px-5 py-3 font-semibold">{t('Titre')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Type')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Affichage')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Publication')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Vues')}</th>
                                <th className="px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-ink-100 dark:divide-white/10">
                            {posts.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-14 text-center text-sm text-ink-400">
                                        {t('Aucune publication.')}
                                    </td>
                                </tr>
                            )}
                            {posts.data.map((post) => (
                                <tr key={post.id} className="transition hover:bg-ink-50/60 dark:hover:bg-white/5">
                                    <td className="px-5 py-3.5">
                                        <p className="font-medium text-ink-900 dark:text-white">{post.title}</p>
                                        {post.isFeatured && <span className="badge mt-1 bg-gold-500/12 text-gold-600">{t('À la une')}</span>}
                                    </td>
                                    <td className="px-5 py-3.5 text-ink-600 dark:text-ink-300">{labels[post.type]}</td>
                                    <td className="px-5 py-3.5">
                                        <StatusToggle
                                            active={post.isVisible}
                                            onToggle={() => router.post(routes.admin.postVisibility(post.slug), {}, { preserveScroll: true })}
                                        />
                                    </td>
                                    <td className="px-5 py-3.5 text-ink-600 dark:text-ink-300">
                                        {post.visibility === 'draft' ? (
                                            <span className="badge bg-amber-50 text-amber-700 dark:bg-amber-500/12 dark:text-amber-300">{t('Brouillon')}</span>
                                        ) : post.visibility === 'scheduled' ? (
                                            <span className="badge bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300">
                                                {t('Programmée')} · {post.publishedAtLabel}
                                            </span>
                                        ) : (
                                            post.publishedAtLabel
                                        )}
                                    </td>
                                    <td className="px-5 py-3.5 text-ink-600 dark:text-ink-300">{post.views}</td>
                                    <td className="px-5 py-3.5">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={routes.admin.postEdit(post.slug)}
                                                className="rounded-lg p-2 text-ink-400 transition hover:bg-ink-100 hover:text-ink-800 dark:hover:bg-white/10"
                                            >
                                                <Icon name="pencil" className="h-4 w-4" />
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (confirm(t('Supprimer cette publication ?'))) router.delete(routes.admin.post(post.slug));
                                                }}
                                                className="rounded-lg p-2 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                            >
                                                <Icon name="trash" className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <Pagination page={posts} />
        </AdminLayout>
    );
}
