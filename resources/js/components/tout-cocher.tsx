import { useEffect, useRef } from 'react';

/**
 * Cocher ou décocher d'un coup toute une liste.
 *
 * Elle ne porte que sur les éléments qu'on a sous les yeux : avec un filtre
 * actif, « tout cocher » n'ajoute que les visibles et « tout décocher » ne
 * retire qu'eux, sans toucher à ce qui est coché ailleurs.
 *
 * La case passe en état indéterminé quand la sélection est partielle — on
 * voit d'un coup d'œil qu'il reste quelque chose à cocher.
 */
export default function ToutCocher({
    valeurs,
    selection,
    onChange,
    libelle = 'Tout cocher',
    className,
}: {
    /** Les identifiants actuellement visibles. */
    valeurs: number[];
    /** Les identifiants cochés, visibles ou non. */
    selection: number[];
    onChange: (selection: number[]) => void;
    libelle?: string;
    className?: string;
}) {
    const caseRef = useRef<HTMLInputElement>(null);

    const coches = valeurs.filter((valeur) => selection.includes(valeur));
    const toutCoche = valeurs.length > 0 && coches.length === valeurs.length;
    const partiel = coches.length > 0 && !toutCoche;

    useEffect(() => {
        if (caseRef.current) caseRef.current.indeterminate = partiel;
    }, [partiel]);

    if (valeurs.length === 0) return null;

    const basculer = () => {
        onChange(
            toutCoche
                ? selection.filter((valeur) => !valeurs.includes(valeur))
                : [...new Set([...selection, ...valeurs])],
        );
    };

    return (
        <label
            className={`inline-flex w-fit cursor-pointer items-center gap-2 text-sm text-ink-600 dark:text-ink-300 ${className ?? ''}`}
        >
            <input
                ref={caseRef}
                type="checkbox"
                checked={toutCoche}
                onChange={basculer}
                className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500/30 dark:border-white/20 dark:bg-white/5"
            />
            <span>
                {toutCoche ? 'Tout décocher' : libelle}
                <span className="ml-1 text-ink-400">
                    ({coches.length}/{valeurs.length})
                </span>
            </span>
        </label>
    );
}
