import { type ChangeEvent, type PointerEvent as ReactPointerEvent, useRef, useState } from 'react';
import Icon from '@/components/icon';
import { type Cadrage, CADRAGE_NEUTRE, estNeutre, styleCadrage, ZOOM_MAX } from '@/lib/cadrage';
import { cn, useT } from '@/lib/utils';

interface Props {
    preview: string | null;
    initials?: string;
    onPick: (file: File) => void;
    onDrop?: () => void;
    error?: string;
    hint?: string;
    /**
     * Le cadrage courant et de quoi le changer. Absents, la photo se montre
     * centrée et l'outil de recadrage ne s'affiche pas.
     */
    cadrage?: Cadrage;
    onCadrage?: (cadrage: Cadrage) => void;
}

/**
 * Photo de profil : aperçu rond, choix du fichier, retrait, et recadrage.
 *
 * Recadrer n'entame jamais le fichier : on garde la photo telle qu'elle a
 * été déposée et l'on enregistre à côté la façon de la regarder. Le cadrage
 * reste donc modifiable indéfiniment, y compris sur une photo déjà en place
 * qu'on ne remplace pas.
 */
export default function PhotoField({
    preview,
    initials,
    onPick,
    onDrop,
    error,
    hint,
    cadrage,
    onCadrage,
}: Props) {
    const t = useT();
    const inputRef = useRef<HTMLInputElement>(null);
    const cadreRef = useRef<HTMLSpanElement>(null);
    const [deplace, setDeplace] = useState(false);

    const valeur = cadrage ?? CADRAGE_NEUTRE;
    const recadrable = Boolean(preview && onCadrage);

    const pick = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (file) {
            onPick(file);
            // Une nouvelle photo repart d'un cadrage neutre : celui de la
            // précédente n'a aucune raison de lui convenir.
            onCadrage?.(CADRAGE_NEUTRE);
        }
    };

    /*
     * Le point visé suit le doigt : on traduit sa position dans le cadre en
     * pourcentages de la photo. L'agrandissement resserre la course, pour
     * que le déplacement reste proportionné à ce qu'on voit.
     */
    const viser = (event: ReactPointerEvent<HTMLSpanElement>) => {
        const cadre = cadreRef.current;

        if (!cadre || !onCadrage) {
            return;
        }

        const limites = cadre.getBoundingClientRect();
        const ratio = 100 / valeur.zoom;

        onCadrage({
            ...valeur,
            x: Math.min(100, Math.max(0, ((event.clientX - limites.left) / limites.width) * ratio + (100 - ratio) / 2)),
            y: Math.min(100, Math.max(0, ((event.clientY - limites.top) / limites.height) * ratio + (100 - ratio) / 2)),
        });
    };

    return (
        <div>
            <div className="flex items-center gap-4">
                <span
                    ref={cadreRef}
                    onPointerDown={(event) => {
                        if (!recadrable) return;
                        event.currentTarget.setPointerCapture(event.pointerId);
                        setDeplace(true);
                        viser(event);
                    }}
                    onPointerMove={(event) => deplace && viser(event)}
                    onPointerUp={() => setDeplace(false)}
                    onPointerCancel={() => setDeplace(false)}
                    className={cn(
                        'relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full ring-1 select-none',
                        preview
                            ? 'ring-ink-900/10 dark:ring-white/15'
                            : 'bg-linear-to-br from-brand-600 to-brand-800 text-lg font-semibold text-white ring-white/15',
                        error && !preview && 'ring-2 ring-red-400',
                        recadrable && (deplace ? 'cursor-grabbing' : 'cursor-grab'),
                    )}
                >
                    {preview ? (
                        <img src={preview} alt="" draggable={false} className="h-full w-full" style={styleCadrage(valeur)} />
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
                                    onCadrage?.(CADRAGE_NEUTRE);
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

            {recadrable && onCadrage && (
                <div className="mt-3 rounded-xl border border-ink-200 px-3.5 py-3 dark:border-white/10">
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-[13px] font-medium text-ink-700 dark:text-ink-200">{t('Cadrage')}</span>
                        {!estNeutre(valeur) && (
                            <button
                                type="button"
                                onClick={() => onCadrage(CADRAGE_NEUTRE)}
                                className="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                            >
                                {t('Recentrer')}
                            </button>
                        )}
                    </div>

                    <div className="mt-2 flex items-center gap-3">
                        <Icon name="search" className="h-3.5 w-3.5 shrink-0 text-ink-400" />
                        <input
                            type="range"
                            min={1}
                            max={ZOOM_MAX}
                            step={0.05}
                            value={valeur.zoom}
                            onChange={(event) => onCadrage({ ...valeur, zoom: Number(event.target.value) })}
                            aria-label={t('Agrandissement')}
                            className="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-ink-200 accent-brand-600 dark:bg-white/10"
                        />
                        <span className="w-10 shrink-0 text-right text-xs tabular-nums text-ink-400">
                            ×{valeur.zoom.toFixed(1)}
                        </span>
                    </div>

                    <p className="mt-2 text-xs text-ink-400">
                        {t('Faites glisser la photo pour choisir ce qui doit être visible. Le fichier d’origine n’est pas modifié.')}
                    </p>
                </div>
            )}

            <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={pick} className="hidden" />
            {error && <p className="mt-2 text-xs text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
}
