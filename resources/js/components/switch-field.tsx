import { cn, useT } from '@/lib/utils';

interface Props {
    checked: boolean;
    onChange: (value: boolean) => void;
    label: string;
    description?: string;
}

/** Interrupteur Actif / Inactif, avec libellé et explication. */
export default function SwitchField({ checked, onChange, label, description }: Props) {
    const t = useT();

    return (
        <div className="flex items-start gap-3 rounded-xl border border-ink-200 p-3.5 dark:border-white/10">
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                onClick={() => onChange(!checked)}
                className={cn(
                    'relative mt-0.5 h-6 w-11 shrink-0 rounded-full transition',
                    checked ? 'bg-emerald-500' : 'bg-ink-300 dark:bg-white/20',
                )}
            >
                <span
                    className={cn(
                        'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all',
                        checked ? 'left-[22px]' : 'left-0.5',
                    )}
                />
            </button>

            <div className="min-w-0">
                <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-ink-800 dark:text-ink-100">
                    {label}
                    <span
                        className={cn(
                            'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                            checked
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300'
                                : 'bg-ink-100 text-ink-500 dark:bg-white/8 dark:text-ink-400',
                        )}
                    >
                        {checked ? t('Actif') : t('Inactif')}
                    </span>
                </p>
                {description && <p className="mt-0.5 text-xs leading-snug text-ink-400">{description}</p>}
            </div>
        </div>
    );
}
