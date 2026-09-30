import { useEffect, useRef, useState } from 'react';

/**
 * Recherche au fil de la frappe.
 *
 * Le terme saisi est rendu tout de suite ; la requête ne part qu'après un
 * court silence, sinon chaque lettre en déclencherait une. Inertia annule
 * d'elle-même la visite précédente quand une autre part.
 *
 * `valeurAppliquee` est le terme que le serveur a effectivement traité : on
 * ne relance rien tant que la saisie lui correspond, ce qui évite une
 * requête en boucle au retour de la réponse.
 */
export function useRechercheInstantanee(
    valeurAppliquee: string,
    lancer: (terme: string) => void,
    delai = 250,
): [string, (terme: string) => void] {
    const [terme, setTerme] = useState(valeurAppliquee);
    const premierRendu = useRef(true);

    useEffect(() => {
        if (premierRendu.current) {
            premierRendu.current = false;

            return;
        }

        if (terme === valeurAppliquee) {
            return;
        }

        const minuteur = setTimeout(() => lancer(terme), delai);

        return () => clearTimeout(minuteur);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [terme]);

    return [terme, setTerme];
}
