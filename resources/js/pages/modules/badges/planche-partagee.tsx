import { Head } from '@inertiajs/react';
import { useRef, useState } from 'react';
import Icon from '@/components/icon';
import { type Cadrage } from '@/lib/cadrage';
import { nomDeFichier, telechargerFaces } from '@/lib/badge-pdf';
import CarteBadge, { type Institut } from './carte';

interface Badge {
    id: number;
    numero: string;
    nomAffiche: string;
    matricule: string | null;
    photoUrl: string | null;
    photoCadrage: Cadrage | null;
    modele: string;
    institut: Institut | null;
}

interface Props {
    demandes: Badge[];
    validite: number;
    mention: string | null;
}

/**
 * La planche telle que la reçoit la reprographie : un lien, pas de compte.
 *
 * Chaque badge se télécharge en PDF recto-verso — deux pages au format de la
 * carte, 54 × 86 mm — et la planche entière en un seul fichier.
 */
export default function PlanchePartagee({ demandes, validite, mention }: Props) {
    const faces = useRef(new Map<string, HTMLDivElement>());
    const [enCours, setEnCours] = useState<string | null>(null);

    const retenir = (cle: string) => (element: HTMLDivElement | null) => {
        if (element) {
            faces.current.set(cle, element);
        } else {
            faces.current.delete(cle);
        }
    };

    const elementsDe = (badge: Badge) =>
        [faces.current.get(`${badge.id}-recto`), faces.current.get(`${badge.id}-verso`)].filter(
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

    return (
        <div className="min-h-dvh bg-ink-50">
            <Head title="Badges à imprimer" />

            <style>{`
                @media print {
                    .sans-impression { display: none !important; }
                    @page { size: A4; margin: 12mm; }
                    body { background: #fff; }
                }
            `}</style>

            <header className="sans-impression border-b border-ink-200 bg-white">
                <div className="mx-auto flex max-w-[1100px] flex-wrap items-center gap-4 px-6 py-5">
                    <img src="/images/marque-la-majestueuse.png" alt="" className="h-10 w-auto" />

                    <div>
                        <h1 className="text-lg font-semibold text-ink-900">Badges à imprimer</h1>
                        <p className="text-sm text-ink-500">
                            {demandes.length} badge(s) · format carte 54 × 86 mm · recto-verso
                        </p>
                    </div>

                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        {demandes.length > 0 && (
                            <button
                                type="button"
                                onClick={() =>
                                    telecharger(
                                        'planche',
                                        demandes.flatMap(elementsDe),
                                        nomDeFichier('badges', new Date().toISOString().slice(0, 10)),
                                    )
                                }
                                disabled={enCours !== null}
                                className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                <Icon
                                    name={enCours === 'planche' ? 'loader' : 'download'}
                                    className={enCours === 'planche' ? 'h-4 w-4 animate-spin' : 'h-4 w-4'}
                                />
                                {enCours === 'planche' ? 'Préparation…' : 'Tout télécharger (PDF)'}
                            </button>
                        )}

                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="inline-flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50"
                        >
                            <Icon name="print" className="h-4 w-4" />
                            Imprimer
                        </button>
                    </div>
                </div>
            </header>

            {demandes.length === 0 ? (
                <p className="px-6 py-24 text-center text-sm text-ink-500">
                    Ce lien ne contient aucun badge à imprimer. Demandez-en un nouveau au service qui vous l'a
                    envoyé.
                </p>
            ) : (
                <div className="mx-auto flex max-w-[1100px] flex-wrap gap-8 px-6 py-10">
                    {demandes.map((badge) => (
                        <div
                            key={badge.id}
                            className="flex flex-col items-center gap-3 rounded-2xl bg-white p-5 shadow-sm"
                        >
                            <div className="flex gap-4">
                                <div ref={retenir(`${badge.id}-recto`)}>
                                    <CarteBadge donnees={badge} echelle={1.2} validite={validite} sansRelief />
                                </div>
                                <div ref={retenir(`${badge.id}-verso`)}>
                                    <CarteBadge
                                        donnees={badge}
                                        echelle={1.2}
                                        validite={validite}
                                        face="verso"
                                        mention={mention}
                                        sansRelief
                                    />
                                </div>
                            </div>

                            <div className="sans-impression flex w-full items-center justify-between gap-3">
                                <span className="truncate text-xs text-ink-500">
                                    {badge.numero} · {badge.nomAffiche}
                                </span>

                                <button
                                    type="button"
                                    onClick={() =>
                                        telecharger(
                                            String(badge.id),
                                            elementsDe(badge),
                                            nomDeFichier('badge', badge.numero, badge.nomAffiche),
                                        )
                                    }
                                    disabled={enCours !== null}
                                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-medium text-ink-700 transition hover:bg-ink-50 disabled:opacity-60"
                                >
                                    <Icon
                                        name={enCours === String(badge.id) ? 'loader' : 'download'}
                                        className={
                                            enCours === String(badge.id) ? 'h-3.5 w-3.5 animate-spin' : 'h-3.5 w-3.5'
                                        }
                                    />
                                    PDF recto-verso
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <p className="sans-impression mx-auto max-w-[1100px] px-6 pb-12 text-xs text-ink-400">
                Lien privé, à durée limitée. {mention}
            </p>
        </div>
    );
}
