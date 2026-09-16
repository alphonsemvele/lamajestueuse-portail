import { useEffect, useRef, useState } from 'react';
import Icon from '@/components/icon';
import { cn, useT } from '@/lib/utils';

export interface MultiSelectOption {
    value: string;
    label: string;
    description?: string | null;
}

interface Props {
    options: MultiSelectOption[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    className?: string;
    id?: string;
}

/**
 * Liste déroulante à choix multiples : les choix s'affichent en pastilles,
 * le panneau propose une recherche quand la liste est longue.
 */
export default function MultiSelect({ options, value, onChange, placeholder, className, id }: Props) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [filter, setFilter] = useState('');
    const root = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        const close = (event: MouseEvent | KeyboardEvent) => {
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !root.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    const labelOf = (code: string) => options.find((option) => option.value === code)?.label ?? code;

    // On garde l'ordre du catalogue : le premier rôle est le rôle principal.
    const toggle = (code: string) => {
        const next = value.includes(code) ? value.filter((item) => item !== code) : [...value, code];
        const order = options.map((option) => option.value);
        onChange(next.sort((a, b) => (order.indexOf(a) + 1 || 999) - (order.indexOf(b) + 1 || 999)));
    };

    const visible = options.filter((option) => `${option.label} ${option.value}`.toLowerCase().includes(filter.toLowerCase()));

    return (
        <div ref={root} className={cn('relative', className)}>
            <button
                id={id}
                type="button"
                onClick={() => setOpen(!open)}
                aria-haspopup="listbox"
                aria-expanded={open}
                className="field-input flex min-h-[40px] w-full flex-wrap items-center gap-1.5 py-1.5 pr-8 text-left text-[13px]"
            >
                {value.length === 0 && <span className="text-ink-400">{placeholder ?? t('Choisir…')}</span>}
                {value.map((code) => (
                    <span
                        key={code}
                        className="inline-flex items-center gap-1 rounded-md bg-brand-50 px-2 py-0.5 text-[12px] font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"
                    >
                        {labelOf(code)}
                        <span
                            role="button"
                            tabIndex={-1}
                            aria-label={t('Retirer')}
                            onClick={(event) => {
                                event.stopPropagation();
                                toggle(code);
                            }}
                            className="-mr-0.5 rounded px-0.5 text-brand-500 hover:bg-brand-100 hover:text-brand-800 dark:hover:bg-brand-500/25"
                        >
                            ×
                        </span>
                    </span>
                ))}
                <Icon name="chevron-down" className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
            </button>

            {open && (
                <div className="absolute right-0 z-30 mt-1.5 w-full min-w-[260px] overflow-hidden rounded-xl border border-ink-200 bg-white shadow-xl dark:border-white/10 dark:bg-ink-900">
                    {options.length > 6 && (
                        <div className="border-b border-ink-100 p-2 dark:border-white/10">
                            <input
                                autoFocus
                                type="search"
                                value={filter}
                                onChange={(event) => setFilter(event.target.value)}
                                placeholder={t('Rechercher un rôle…')}
                                className="field-input py-1.5 text-[13px]"
                            />
                        </div>
                    )}
                    <ul role="listbox" aria-multiselectable="true" className="max-h-72 overflow-y-auto py-1">
                        {visible.map((option) => {
                            const checked = value.includes(option.value);

                            return (
                                <li key={option.value}>
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected={checked}
                                        onClick={() => toggle(option.value)}
                                        className="flex w-full items-start gap-3 px-3 py-2 text-left transition hover:bg-ink-50 dark:hover:bg-white/5"
                                    >
                                        <span
                                            className={cn(
                                                'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border',
                                                checked ? 'border-brand-600 bg-brand-600 text-white' : 'border-ink-300 dark:border-white/20',
                                            )}
                                        >
                                            {checked && <Icon name="check" className="h-3 w-3" />}
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block text-[13px] font-medium text-ink-900 dark:text-white">{option.label}</span>
                                            {option.description && <span className="block text-[11px] leading-snug text-ink-500 dark:text-ink-400">{option.description}</span>}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                        {visible.length === 0 && <li className="px-3 py-2 text-[13px] text-ink-400">{t('Aucun rôle ne correspond.')}</li>}
                    </ul>
                </div>
            )}
        </div>
    );
}
