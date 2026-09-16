import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export default function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) return null;

    return (
        <nav className="mt-5 flex flex-wrap items-center gap-1">
            {page.links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={cn(
                            'rounded-lg border px-3 py-1.5 text-sm transition',
                            link.active
                                ? 'border-brand-600 bg-brand-600 text-white'
                                : 'border-ink-200 text-ink-600 hover:border-ink-400 dark:border-white/10 dark:text-ink-300',
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={index}
                        className="rounded-lg border border-ink-200/60 px-3 py-1.5 text-sm text-ink-300 dark:border-white/5"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}
