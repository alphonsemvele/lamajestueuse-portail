import { Head, Link } from '@inertiajs/react';
import Icon from '@/components/icon';
import { routes } from '@/lib/utils';
import CarteBadge, { type Institut } from './carte';

interface Demande {
    id: number;
    numero: string;
    demandeur: string | null;
    nomAffiche: string;
    posteAffiche: string | null;
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
 */
export default function PlancheImpression({ demandes, validite, mention }: Props) {
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

                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="ml-auto inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                    >
                        <Icon name="print" className="h-4 w-4" />
                        Imprimer
                    </button>
                </div>
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
                                <CarteBadge donnees={demande} echelle={1.35} validite={validite} />
                                <CarteBadge donnees={demande} echelle={1.35} validite={validite} face="verso" mention={mention} />
                            </div>
                            <p className="sans-impression text-[11px] text-ink-400">
                                {demande.demandeur} · {demande.numero}
                            </p>
                        </div>
                    ))}
                </div>
            )}

            <p className="sans-impression mx-auto max-w-[1100px] px-6 pb-12 text-xs text-ink-400">{mention}</p>
        </div>
    );
}
