import { useEffect, useState } from 'react';
import Icon from '@/components/icon';
import { cn, useT } from '@/lib/utils';

type Mode = 'system' | 'light' | 'dark';

export default function ThemeToggle({ tone = 'light' }: { tone?: 'light' | 'dark' }) {
    const t = useT();
    const [mode, setMode] = useState<Mode>('system');

    useEffect(() => {
        setMode((localStorage.getItem('theme') as Mode) ?? 'system');
    }, []);

    const apply = (next: Mode) => {
        setMode(next);
        localStorage.setItem('theme', next);
        const dark = next === 'dark' || (next === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    };

    const options: [Mode, string, string][] = [
        ['system', 'monitor', t('Système')],
        ['light', 'sun', t('Clair')],
        ['dark', 'moon', t('Sombre')],
    ];

    return (
        <div
            role="group"
            aria-label={t('Thème')}
            className={cn(
                'flex items-center gap-1 rounded-full border p-1',
                tone === 'dark'
                    ? 'border-white/15 bg-white/10 text-white/70'
                    : 'border-ink-200 bg-white text-ink-500 dark:border-white/10 dark:bg-white/5 dark:text-ink-400',
            )}
        >
            {options.map(([value, icon, label]) => (
                <button
                    key={value}
                    type="button"
                    title={label}
                    aria-label={label}
                    onClick={() => apply(value)}
                    className={cn(
                        'rounded-full p-1.5 transition',
                        mode === value &&
                            (tone === 'dark'
                                ? 'bg-white/20 text-white'
                                : 'bg-brand-50 text-brand-700 dark:bg-white/10 dark:text-white'),
                    )}
                >
                    <Icon name={icon} className="h-4 w-4" />
                </button>
            ))}
        </div>
    );
}
