import { type ChangeEvent, useRef } from 'react';
import Icon from '@/components/icon';
import Label from '@/components/label';
import { Input } from '@/components/ui';
import { cn, useT } from '@/lib/utils';

/** Zone de televersement avec apercu immediat et retrait. */
export default function MediaField({
    label,
    hint,
    preview,
    round,
    emptyLabel,
    onPick,
    onDrop,
    externalValue,
    onExternalChange,
    externalPlaceholder,
}: {
    label: string;
    hint: string;
    preview: string | null;
    round?: boolean;
    emptyLabel: string;
    onPick: (file: File) => void;
    onDrop: () => void;
    externalValue: string;
    onExternalChange: (value: string) => void;
    externalPlaceholder: string;
}) {
    const t = useT();
    const inputRef = useRef<HTMLInputElement>(null);

    const pick = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (file) onPick(file);
    };

    return (
        <div>
            <Label>{label}</Label>
            <div className="mt-2 overflow-hidden rounded-xl border border-ink-200 dark:border-white/10">
                <div className="relative flex h-32 items-center justify-center bg-ink-100 dark:bg-white/5">
                    {preview ? (
                        <img
                            src={preview}
                            alt=""
                            className={cn(round ? 'h-20 w-20 rounded-xl bg-white object-contain p-1.5 shadow-sm ring-1 ring-ink-900/5' : 'h-full w-full object-cover')}
                        />
                    ) : (
                        <span className="flex flex-col items-center gap-1.5 text-ink-400">
                            <Icon name="image" className="h-6 w-6" />
                            <span className="text-xs">{emptyLabel}</span>
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-2 border-t border-ink-200 p-2.5 dark:border-white/10">
                    <button type="button" onClick={() => inputRef.current?.click()} className="btn-ghost py-2 text-[13px]">
                        <Icon name="upload" className="h-4 w-4" />
                        {t('Choisir un fichier')}
                    </button>
                    <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={pick} className="hidden" />
                    {preview && (
                        <button
                            type="button"
                            title={t('Retirer')}
                            onClick={() => {
                                onDrop();
                                if (inputRef.current) inputRef.current.value = '';
                            }}
                            className="rounded-lg p-2 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                        >
                            <Icon name="trash" className="h-4 w-4" />
                        </button>
                    )}
                </div>
            </div>
            <Input
                className="mt-2 font-mono text-[12px]"
                placeholder={externalPlaceholder}
                value={externalValue}
                onChange={(event) => onExternalChange(event.target.value)}
                maxLength={255}
            />
            <p className="mt-1.5 text-xs text-ink-400">{hint}</p>
        </div>
    );
}
