import { useEffect } from 'react';
import Icon from '@/components/icon';

/**
 * Aperçu d'un PDF en plein écran, avec son bouton de téléchargement.
 *
 * Le document est servi en ligne par le serveur et affiché tel quel : ce
 * qu'on voit ici est exactement le fichier qui sera enregistré, sans
 * reconstitution à l'écran qui pourrait en diverger.
 */
export default function ApercuPdf({
    titre,
    sousTitre,
    source,
    telechargement,
    onFermer,
}: {
    titre: string;
    sousTitre?: string | null;
    /** L'adresse qui sert le PDF en ligne. */
    source: string;
    /** Celle qui déclenche l'enregistrement. */
    telechargement: string;
    onFermer: () => void;
}) {
    useEffect(() => {
        const fermer = (event: KeyboardEvent) => event.key === 'Escape' && onFermer();
        document.addEventListener('keydown', fermer);

        const avant = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', fermer);
            document.body.style.overflow = avant;
        };
    }, [onFermer]);

    return (
        <div role="dialog" aria-modal="true" aria-label={titre} className="fixed inset-0 z-50 flex flex-col">
            <div className="absolute inset-0 bg-ink-900/70 backdrop-blur-[2px]" onClick={onFermer} />

            <div className="relative z-10 flex h-full flex-col p-3 sm:p-6">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-white">{titre}</p>
                        {sousTitre && <p className="truncate text-xs text-white/60">{sousTitre}</p>}
                    </div>

                    <a
                        href={telechargement}
                        className="inline-flex items-center gap-2 rounded-xl bg-white px-3.5 py-2 text-sm font-medium text-ink-800 transition hover:bg-ink-100"
                    >
                        <Icon name="download" className="h-4 w-4" />
                        Télécharger
                    </a>

                    <button
                        type="button"
                        onClick={onFermer}
                        aria-label="Fermer l’aperçu"
                        className="rounded-xl bg-white/10 px-3 py-2 text-sm text-white transition hover:bg-white/20"
                    >
                        ✕
                    </button>
                </div>

                {/*
                  * Une page A4 fait 1/1,414 de rapport : le cadre le respecte
                  * pour que le document ne soit ni étiré ni rogné.
                  */}
                <iframe
                    src={source}
                    title={titre}
                    className="min-h-0 w-full flex-1 rounded-xl border-0 bg-white shadow-2xl"
                />

                <p className="mt-2 text-center text-[11px] text-white/50">
                    Aperçu du document tel qu’il sera enregistré.
                </p>
            </div>
        </div>
    );
}
