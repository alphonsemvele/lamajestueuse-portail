import { cn, useT } from '@/lib/utils';

interface Props {
    active: boolean;
    onToggle?: () => void;
    /** Rend le bouton non cliquable (affichage seul). */
    readOnly?: boolean;
    size?: 'sm' | 'md';
    labels?: { active: string; inactive: string };
    className?: string;
}

/**
 * Bouton à deux états : Actif / Inactif.
 * Cliquable dans les listes pour basculer sans ouvrir la fiche.
 */
export default function StatusToggle({ active, onToggle, readOnly = false, size = 'sm', labels, className }: Props) {
    const t = useT();
    const texte = active ? (labels?.active ?? t('Actif')) : (labels?.inactive ?? t('Inactif'));

    const classes = cn(
        'inline-flex items-center gap-1.5 rounded-full border font-medium transition',
        size === 'sm' ? 'px-2.5 py-1 text-[11px]' : 'px-3.5 py-2 text-[13px]',
        active
            ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/12 dark:text-emerald-300'
            : 'border-ink-200 bg-ink-50 text-ink-500 dark:border-white/10 dark:bg-white/5 dark:text-ink-400',
        !readOnly && 'cursor-pointer hover:brightness-95 dark:hover:brightness-125',
        className,
    );

    const contenu = (
        <>
            <span
                className={cn(
                    'rounded-full',
                    size === 'sm' ? 'h-1.5 w-1.5' : 'h-2 w-2',
                    active ? 'bg-emerald-500' : 'bg-ink-300 dark:bg-ink-500',
                )}
            />
            {texte}
        </>
    );

    if (readOnly) {
        return <span className={classes}>{contenu}</span>;
    }

    return (
        <button type="button" onClick={onToggle} title={active ? t('Masquer du portail') : t('Afficher sur le portail')} className={classes}>
            {contenu}
        </button>
    );
}
