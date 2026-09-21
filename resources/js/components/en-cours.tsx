import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Spinner from '@/components/spinner';
import { useT } from '@/lib/utils';

type Etat = {
    actif: boolean;
    /** Une écriture (envoi de formulaire) ou une simple navigation ? */
    ecriture: boolean;
    /** Avancement du téléversement, quand un fichier accompagne l'envoi. */
    pourcent: number | null;
};

const REPOS: Etat = { actif: false, ecriture: false, pourcent: null };

/**
 * Indicateur d'attente du portail.
 *
 * Une barre s'affiche en haut dès qu'une requête part. Si l'attente se
 * prolonge — ou dès qu'un fichier part, toujours plus lent — une fenêtre
 * annonce clairement que le traitement est en cours, avec son avancement.
 */
export default function EnCours() {
    const t = useT();
    const [etat, setEtat] = useState<Etat>(REPOS);
    const [fenetre, setFenetre] = useState(false);
    const minuteur = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const annulerMinuteur = () => {
            if (minuteur.current) {
                clearTimeout(minuteur.current);
                minuteur.current = null;
            }
        };

        const debut = router.on('start', (event) => {
            const methode = String(event.detail.visit.method ?? 'get').toLowerCase();
            const ecriture = methode !== 'get';

            setEtat({ actif: true, ecriture, pourcent: null });

            // Une écriture mérite un message rapidement ; une navigation
            // ordinaire n'affiche la fenêtre que si elle traîne vraiment.
            annulerMinuteur();
            minuteur.current = setTimeout(() => setFenetre(true), ecriture ? 400 : 1200);
        });

        const avancement = router.on('progress', (event) => {
            const pourcent = event.detail.progress?.percentage;

            if (typeof pourcent === 'number') {
                // Un envoi de fichier : on montre la fenêtre sans attendre.
                setFenetre(true);
                setEtat((actuel) => ({ ...actuel, pourcent: Math.round(pourcent) }));
            }
        });

        const fin = router.on('finish', () => {
            annulerMinuteur();
            setFenetre(false);
            setEtat(REPOS);
        });

        return () => {
            annulerMinuteur();
            debut();
            avancement();
            fin();
        };
    }, []);

    if (!etat.actif) {
        return null;
    }

    const televersement = etat.pourcent !== null && etat.pourcent < 100;

    return (
        <>
            {/* Barre de progression, toujours visible pendant la requête. */}
            <div className="fixed inset-x-0 top-0 z-[70] h-1 overflow-hidden bg-brand-100 dark:bg-brand-500/20" aria-hidden="true">
                {etat.pourcent === null ? (
                    <div className="barre-indeterminee h-full w-1/3 rounded-full bg-linear-to-r from-brand-500 to-brand-700" />
                ) : (
                    <div
                        className="h-full rounded-full bg-linear-to-r from-brand-500 to-brand-700 transition-[width] duration-200"
                        style={{ width: `${etat.pourcent}%` }}
                    />
                )}
            </div>

            {fenetre && (
                <div
                    role="status"
                    aria-live="polite"
                    className="fixed inset-0 z-[69] flex items-center justify-center bg-ink-900/35 p-4 backdrop-blur-[2px]"
                >
                    <div className="w-full max-w-xs rounded-2xl bg-white p-6 text-center shadow-2xl dark:bg-ink-800">
                        <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <Spinner className="h-6 w-6" />
                        </span>

                        <p className="mt-4 text-sm font-semibold text-ink-900 dark:text-white">
                            {televersement
                                ? t('Envoi du fichier…')
                                : etat.ecriture
                                  ? t('Traitement en cours…')
                                  : t('Chargement de la page…')}
                        </p>

                        {televersement ? (
                            <>
                                <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-white/10">
                                    <div
                                        className="h-full rounded-full bg-brand-600 transition-[width] duration-200"
                                        style={{ width: `${etat.pourcent}%` }}
                                    />
                                </div>
                                <p className="mt-2 text-xs text-ink-500 dark:text-ink-400">{etat.pourcent} %</p>
                            </>
                        ) : (
                            <p className="mt-1.5 text-xs text-ink-500 dark:text-ink-400">
                                {t('Merci de patienter, ne fermez pas cette page.')}
                            </p>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
