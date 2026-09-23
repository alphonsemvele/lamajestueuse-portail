import { type PropsWithChildren, type ReactNode, useEffect } from 'react';
import Icon from '@/components/icon';
import { cn } from '@/lib/utils';

/** Montants en francs CFA : pas de décimales, séparateur d'espace fine. */
export function fcfa(montant: number | null | undefined): string {
    return `${Math.round(Number(montant ?? 0)).toLocaleString('fr-FR')} F`;
}

export const MOIS = [
    'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
];

/** En-tête commun : titre, sous-titre, actions à droite. */
export function Entete({ titre, sous, children }: PropsWithChildren<{ titre: string; sous?: string }>) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 className="text-2xl font-semibold text-ink-900 dark:text-white">{titre}</h1>
                {sous && <p className="mt-1 text-sm text-ink-500 dark:text-ink-400">{sous}</p>}
            </div>
            {children && <div className="flex flex-wrap items-center gap-2">{children}</div>}
        </div>
    );
}

const tonsStatut: Record<string, string> = {
    brouillon: 'bg-ink-100 text-ink-700 dark:bg-white/10 dark:text-ink-200',
    valide: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
    paye: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
    actif: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
    suspendu: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
    termine: 'bg-ink-100 text-ink-600 dark:bg-white/10 dark:text-ink-300',
};

const libellesStatut: Record<string, string> = {
    brouillon: 'Brouillon',
    valide: 'Validé',
    paye: 'Payé',
    actif: 'Actif',
    suspendu: 'Suspendu',
    termine: 'Terminé',
};

export function Statut({ valeur }: { valeur: string }) {
    return (
        <span className={cn('inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold', tonsStatut[valeur] ?? tonsStatut.brouillon)}>
            {libellesStatut[valeur] ?? valeur}
        </span>
    );
}

/** Tuile de chiffre-clé. */
export function Chiffre({ libelle, valeur, detail, icon }: { libelle: string; valeur: string; detail?: string; icon: string }) {
    return (
        <div className="card flex items-start gap-3 p-4">
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                <Icon name={icon} className="h-5 w-5" />
            </span>
            <div className="min-w-0">
                <p className="text-[11px] uppercase tracking-wide text-ink-400">{libelle}</p>
                <p className="mt-0.5 truncate text-xl font-semibold text-ink-900 tabular-nums dark:text-white">{valeur}</p>
                {detail && <p className="mt-0.5 truncate text-xs text-ink-500 dark:text-ink-400">{detail}</p>}
            </div>
        </div>
    );
}

/** Boîte de dialogue, fermée par Échap ou par le fond. */
export function Modale({
    titre,
    ouverte,
    onFermer,
    large = false,
    children,
}: PropsWithChildren<{ titre: string; ouverte: boolean; onFermer: () => void; large?: boolean }>) {
    useEffect(() => {
        if (!ouverte) return;

        const fermer = (event: KeyboardEvent) => event.key === 'Escape' && onFermer();
        document.addEventListener('keydown', fermer);

        return () => document.removeEventListener('keydown', fermer);
    }, [ouverte, onFermer]);

    if (!ouverte) return null;

    return (
        <div role="dialog" aria-modal="true" className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 py-10">
            <div className="absolute inset-0 bg-ink-900/50 backdrop-blur-[2px]" onClick={onFermer} />
            <div className={cn('relative w-full rounded-2xl bg-white p-6 shadow-2xl dark:bg-ink-800', large ? 'max-w-3xl' : 'max-w-lg')}>
                <div className="mb-4 flex items-start justify-between gap-4">
                    <h2 className="text-lg font-semibold text-ink-900 dark:text-white">{titre}</h2>
                    <button
                        type="button"
                        onClick={onFermer}
                        aria-label="Fermer"
                        className="rounded-lg p-1 text-ink-400 transition hover:bg-ink-100 hover:text-ink-700 dark:hover:bg-white/10 dark:hover:text-white"
                    >
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

/** Champ de formulaire avec son libellé et son erreur éventuelle. */
export function Champ({
    libelle,
    erreur,
    aide,
    className,
    children,
}: PropsWithChildren<{ libelle: string; erreur?: string; aide?: string; className?: string }>) {
    return (
        <label className={cn('block', className)}>
            <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">{libelle}</span>
            {children}
            {aide && !erreur && <span className="mt-1 block text-xs text-ink-400">{aide}</span>}
            {erreur && <span className="mt-1 block text-xs font-medium text-red-600 dark:text-red-400">{erreur}</span>}
        </label>
    );
}

export function Bouton({
    variante = 'principal',
    icon,
    children,
    className,
    ...props
}: PropsWithChildren<
    React.ButtonHTMLAttributes<HTMLButtonElement> & { variante?: 'principal' | 'secondaire' | 'danger'; icon?: string }
>) {
    const variantes = {
        principal: 'bg-teal-600 text-white hover:bg-teal-700 disabled:bg-teal-600/50',
        secondaire:
            'border border-ink-200 bg-white text-ink-700 hover:bg-ink-50 dark:border-white/10 dark:bg-white/5 dark:text-ink-200 dark:hover:bg-white/10',
        danger: 'border border-red-200 bg-white text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:bg-transparent dark:text-red-400 dark:hover:bg-red-500/10',
    };

    return (
        <button
            className={cn(
                'inline-flex items-center justify-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60',
                variantes[variante],
                className,
            )}
            {...props}
        >
            {icon && <Icon name={icon} className="h-4 w-4" />}
            {children}
        </button>
    );
}

/** Tableau responsive : il défile horizontalement plutôt que de déborder. */
export function Tableau({ entetes, children }: { entetes: ReactNode[]; children: ReactNode }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-left text-sm">
                <thead>
                    <tr className="border-b border-ink-200 text-[11px] uppercase tracking-wide text-ink-400 dark:border-white/10">
                        {entetes.map((entete, index) => (
                            <th key={index} className="px-3 py-2 font-medium">
                                {entete}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-ink-100 dark:divide-white/5">{children}</tbody>
            </table>
        </div>
    );
}

export function Vide({ message, icon = 'document' }: { message: string; icon?: string }) {
    return (
        <div className="flex flex-col items-center gap-2 py-10 text-center text-sm text-ink-500 dark:text-ink-400">
            <Icon name={icon} className="h-8 w-8 text-ink-300" />
            {message}
        </div>
    );
}
