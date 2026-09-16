import { Link, usePage } from '@inertiajs/react';
import { cn, routes } from '@/lib/utils';
import type { SharedProps } from '@/types';

export default function LocaleSwitch({ tone = 'light' }: { tone?: 'light' | 'dark' }) {
    const { locale } = usePage<SharedProps>().props;

    return (
        <div
            className={cn(
                'flex items-center gap-1 rounded-full border p-1',
                tone === 'dark'
                    ? 'border-white/15 bg-white/10'
                    : 'border-ink-200 bg-white dark:border-white/10 dark:bg-white/5',
            )}
        >
            {(
                [
                    ['fr', '🇫🇷'],
                    ['en', '🇬🇧'],
                ] as const
            ).map(([code, flag]) => (
                <Link
                    key={code}
                    href={routes.locale(code)}
                    preserveScroll
                    className={cn(
                        'flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase transition',
                        locale === code
                            ? tone === 'dark'
                                ? 'bg-white/20 text-white'
                                : 'bg-brand-50 text-brand-700 dark:bg-white/10 dark:text-white'
                            : tone === 'dark'
                              ? 'text-white/70 hover:text-white'
                              : 'text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white',
                    )}
                >
                    <span aria-hidden="true">{flag}</span>
                    {code}
                </Link>
            ))}
        </div>
    );
}
