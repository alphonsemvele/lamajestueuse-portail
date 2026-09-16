import { type ChangeEvent, useRef } from 'react';
import Icon from '@/components/icon';
import { cn, useT } from '@/lib/utils';

interface Props {
    preview: string | null;
    initials?: string;
    onPick: (file: File) => void;
    onDrop?: () => void;
    error?: string;
    hint?: string;
}

/** Photo de profil : aperçu rond, choix du fichier, retrait. */
export default function PhotoField({ preview, initials, onPick, onDrop, error, hint }: Props) {
    const t = useT();
    const inputRef = useRef<HTMLInputElement>(null);

    const pick = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (file) onPick(file);
    };

    return (
        <div>
            <div className="flex items-center gap-4">
                <span
                    className={cn(
                        'flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full ring-1',
                        preview
                            ? 'ring-ink-900/10 dark:ring-white/15'
                            : 'bg-linear-to-br from-brand-600 to-brand-800 text-lg font-semibold text-white ring-white/15',
                        error && !preview && 'ring-2 ring-red-400',
                    )}
                >
                    {preview ? (
                        <img src={preview} alt="" className="h-full w-full object-cover" />
                    ) : (
                        (initials || <Icon name="user" className="h-8 w-8" />)
                    )}
                </span>

                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <button type="button" onClick={() => inputRef.current?.click()} className="btn-ghost py-2 text-[13px]">
                            <Icon name="upload" className="h-4 w-4" />
                            {preview ? t('Changer la photo') : t('Choisir une photo')}
                        </button>

                        {preview && onDrop && (
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

                    <p className="mt-1.5 text-xs text-ink-400">{hint ?? t('JPG, PNG ou WebP — 4 Mo maximum.')}</p>
                </div>
            </div>

            <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={pick} className="hidden" />
            {error && <p className="mt-2 text-xs text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
}
