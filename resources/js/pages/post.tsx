import { Link } from '@inertiajs/react';
import Icon from '@/components/icon';
import { Card } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { routes, useT } from '@/lib/utils';
import type { Post } from '@/types';

export default function PostPage({ post }: { post: Post }) {
    const t = useT();

    const labels: Record<Post['type'], string> = {
        news: t('Actualité'),
        announcement: t('Annonce'),
        billboard: t('Affichage'),
    };

    return (
        <PortalLayout title={post.title}>
            <article className="mx-auto max-w-3xl">
                <Link href={routes.dashboard} className="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition hover:text-brand-600 dark:text-ink-400">
                    <Icon name="chevron-right" className="h-4 w-4 rotate-180" />
                    {t('Retour au portail')}
                </Link>

                <Card className="overflow-hidden">
                    {post.imageUrl && <img src={post.imageUrl} alt="" className="aspect-[21/9] w-full object-cover" />}

                    <div className="p-6 sm:p-9">
                        <span className="badge bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300">{labels[post.type]}</span>

                        <h1 className="mt-4 text-3xl font-semibold leading-tight tracking-tight text-ink-900 dark:text-white">{post.title}</h1>

                        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-400">
                            <span>{post.publishedAtLabel}</span>
                            {post.author && (
                                <span>
                                    {t('Par')} {post.author}
                                </span>
                            )}
                            <span className="flex items-center gap-1">
                                <Icon name="eye" className="h-3.5 w-3.5" />
                                {post.views}
                            </span>
                        </div>

                        {post.excerpt && (
                            <p className="mt-7 border-l-2 border-brand-500 pl-4 text-[15px] leading-relaxed text-ink-600 dark:text-ink-300">{post.excerpt}</p>
                        )}

                        <div className="mt-6 space-y-4 text-[15px] leading-relaxed text-ink-700 dark:text-ink-300">
                            {(post.body ?? '')
                                .split(/\n{2,}/)
                                .filter((paragraph) => paragraph.trim() !== '')
                                .map((paragraph, index) => (
                                    <p key={index}>{paragraph}</p>
                                ))}
                        </div>
                    </div>
                </Card>
            </article>
        </PortalLayout>
    );
}
