import { Head, Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
import Icon from '@/components/icon';
import { nomDeFichier, telechargerFaces } from '@/lib/badge-pdf';
import { routes } from '@/lib/utils';
import CarteBadge, { type Institut } from './carte';

interface Demande {
    id: number;
    numero: string;
    demandeur: string | null;
    nomAffiche: string;
    matricule: string | null;
    photoUrl: string | null;
    modele: string;
    institut: Institut | null;
}

interface Props {
    demandes: Demande[];
    validite: number;
    mention: string;
}

/**
 * Planche d'impression : les badges approuvés, à l'échelle, sur fond blanc.
 * Tout ce qui est écran disparaît à l'impression.
 *
 * Le PDF est fabriqué à partir de ces cartes-là, celles qui sont affichées :
 * une page par face, au format 54 × 86 mm. C'est le fichier qu'on envoie à la
 * reprographie.
 */
export default function PlancheImpression({ demandes, validite, mention }: Props) {
    // Chaque face est repérée pour la capture : « 12-recto », « 12-verso ».
    const faces = useRef(new Map<string, HTMLDivElement>());
    const [enCours, setEnCours] = useState<string | null>(null);

    const retenir = (cle: string) => (element: HTMLDivElement | null) => {
        if (element) {
            faces.current.set(cle, element);
        } else {
            faces.current.delete(cle);
        }
    };

    const elementsDe = (demande: Demande) =>
        [faces.current.get(`${demande.id}-recto`), faces.current.get(`${demande.id}-verso`)].filter(
            (element): element is HTMLDivElement => Boolean(element),
        );

    const telecharger = async (cle: string, elements: HTMLDivElement[], nom: string) => {
        if (elements.length === 0) return;

        setEnCours(cle);

        try {
            await telechargerFaces(elements, nom);
        } finally {
            setEnCours(null);
        }
    };

    const toutTelecharger = () =>
        telecharger(
            'planche',
            demandes.flatMap(elementsDe),
            nomDeFichier('planche-badges', new Date().toISOString().slice(0, 10)),
        );

    return (
        <div className="min-h-dvh bg-white">
            <Head title="Planche d'impression" />

            <style>{`
                @media print {
                    .sans-impression { display: none !important; }
                    .planche { gap: 10mm; }
                    @page { size: A4; margin: 12mm; }
                }
            `}</style>

            <div className="sans-impression border-b border-ink-200 bg-ink-50">
                <div className="mx-auto flex max-w-[1100px] flex-wrap items-center gap-4 px-6 py-4">
                    <Link
                        href={routes.badges.gestion}
                        className="inline-flex items-center gap-1.5 text-sm text-ink-600 transition hover:text-ink-900"
                    >
                        <Icon name="chevron-right" className="h-4 w-4 rotate-180" />
                        Retour aux demandes
                    </Link>

                    <p className="text-sm text-ink-500">
                        {demandes.length} badge(s) · format carte 54 × 86 mm
                    </p>

                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        {demandes.length > 0 && (
                            <button
                                type="button"
                                onClick={toutTelecharger}
                                disabled={enCours !== null}
                                className="inline-flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50 disabled:opacity-60"
                            >
                                <Icon
                                    name={enCours === 'planche' ? 'loader' : 'download'}
                                    className={enCours === 'planche' ? 'h-4 w-4 animate-spin' : 'h-4 w-4'}
                                />
                                {enCours === 'planche' ? 'Préparation…' : 'Télécharger le PDF'}
                            </button>
                        )}

                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                        >
                            <Icon name="print" className="h-4 w-4" />
                            Imprimer
                        </button>
                    </div>
                </div>

                <p className="mx-auto max-w-[1100px] px-6 pb-4 text-xs text-ink-400">
                    Le PDF compte une page par face, à la taille exacte de la carte : recto, verso, badge après
                    badge.
                </p>
            </div>

            {demandes.length === 0 ? (
                <p className="px-6 py-20 text-center text-sm text-ink-500">
                    Aucun badge approuvé en attente d'impression.
                </p>
            ) : (
                <div className="planche mx-auto flex max-w-[1100px] flex-wrap gap-8 px-6 py-10">
                    {demandes.map((demande) => (
                        <div key={demande.id} className="flex flex-col items-center gap-2">
                            {/* Recto et verso côte à côte : la planche se tire en un passage. */}
                            <div className="flex gap-4">
                                <div ref={retenir(`${demande.id}-recto`)}>
                                    <CarteBadge donnees={demande} echelle={1.35} validite={validite} sansRelief />
                                </div>
                                <div ref={retenir(`${demande.id}-verso`)}>
                                    <CarteBadge
                                        donnees={demande}
                                        echelle={1.35}
                                        validite={validite}
                                        face="verso"
                                        mention={mention}
                                        sansRelief
                                    />
                                </div>
                            </div>

                            <div className="sans-impression flex items-center gap-3 text-[11px] text-ink-400">
                                <span>
                                    {demande.demandeur} · {demande.numero}
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        telecharger(
                                            String(demande.id),
                                            elementsDe(demande),
                                            nomDeFichier('badge', demande.numero, demande.nomAffiche),
                                        )
                                    }
                                    disabled={enCours !== null}
                                    className="inline-flex items-center gap-1 rounded-lg border border-ink-200 px-2 py-1 font-medium text-ink-600 transition hover:bg-ink-50 disabled:opacity-60"
                                >
                                    <Icon
                                        name={enCours === String(demande.id) ? 'loader' : 'download'}
                                        className={
                                            enCours === String(demande.id) ? 'h-3 w-3 animate-spin' : 'h-3 w-3'
                                        }
                                    />
                                    PDF
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <p className="sans-impression mx-auto max-w-[1100px] px-6 pb-12 text-xs text-ink-400">{mention}</p>
        </div>
    );
}
