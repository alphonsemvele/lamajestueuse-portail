import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, type ReactNode, useState } from 'react';
import Icon from '@/components/icon';
import PortalLayout from '@/layouts/portal-layout';
import { cn, routes } from '@/lib/utils';

interface Lien {
    libelle: string;
    href: string;
    icon: string;
}

interface Groupe {
    titre: string | null;
    liens: Lien[];
}

interface Props {
    title: string;
    peutGerer: boolean;
    /** Bandeau de titre, au-dessus du contenu. */
    entete?: ReactNode;
}

/**
 * Coquille des écrans Personnel & paie : menu latéral à gauche, contenu à
 * droite. Le menu reprend l'organisation d'IUM — la paie d'un côté, les
 * référentiels (employeurs, grille, profils, indemnités, retenues) de
 * l'autre, chacun avec sa propre entrée.
 */
export default function PersonnelLayout({ title, peutGerer, entete, children }: PropsWithChildren<Props>) {
    const { url } = usePage();
    const [ouvert, setOuvert] = useState(false);

    const groupes: Groupe[] = [
        {
            titre: null,
            liens: [
                { libelle: 'Tableau de bord', href: routes.personnel.index, icon: 'grid' },
                { libelle: 'Personnel', href: routes.personnel.agents, icon: 'users' },
            ],
        },
        {
            titre: 'Paie',
            liens: [
                { libelle: 'Payer les salaires', href: routes.personnel.paie, icon: 'wallet' },
                { libelle: 'Bulletins de paie', href: routes.personnel.bulletins, icon: 'document' },
            ],
        },
        ...(peutGerer
            ? [
                  {
                      titre: 'Référentiels',
                      liens: [
                          { libelle: 'Employeurs', href: routes.personnel.employeurs, icon: 'building' },
                          { libelle: 'Catégories & échelons', href: routes.personnel.categories, icon: 'layers' },
                          { libelle: 'Profils salaires', href: routes.personnel.profils, icon: 'clipboard' },
                          { libelle: 'Indemnités', href: routes.personnel.indemnites, icon: 'plus' },
                          { libelle: 'Retenues', href: routes.personnel.retenues, icon: 'moins' },
                      ],
                  },
              ]
            : []),
    ];

    const chemin = url.split('?')[0];

    /**
     * Le lien actif est le plus précis : /personnel/agents/12 allume
     * « Personnel », pas « Tableau de bord ».
     */
    const candidats = groupes.flatMap((groupe) => groupe.liens.map((lien) => lien.href));
    const actif = candidats
        .filter((href) => chemin === href || chemin.startsWith(`${href}/`))
        .sort((a, b) => b.length - a.length)[0];

    const menu = (
        <div className="space-y-4">
            {groupes.map((groupe, index) => (
                <div key={groupe.titre ?? index}>
                    {groupe.titre && (
                        <p className="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-ink-400">{groupe.titre}</p>
                    )}
                    <div className="space-y-0.5">
                        {groupe.liens.map((lien) => (
                            <Link
                                key={lien.href}
                                href={lien.href}
                                onClick={() => setOuvert(false)}
                                aria-current={actif === lien.href ? 'page' : undefined}
                                className={cn(
                                    'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm transition',
                                    actif === lien.href
                                        ? 'bg-teal-600 font-medium text-white shadow-sm shadow-teal-900/20'
                                        : 'text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-white/5',
                                )}
                            >
                                <Icon name={lien.icon} className="h-4 w-4 shrink-0" />
                                <span className="truncate">{lien.libelle}</span>
                            </Link>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );

    return (
        <PortalLayout title={title}>
            <div className="lg:grid lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-8">
                <div className="lg:sticky lg:top-24 lg:self-start">
                    <button
                        type="button"
                        onClick={() => setOuvert((etat) => !etat)}
                        aria-expanded={ouvert}
                        className="mb-4 flex w-full items-center justify-between gap-2 rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm font-medium text-ink-800 dark:border-white/10 dark:bg-ink-900 dark:text-white lg:hidden"
                    >
                        <span className="flex items-center gap-2">
                            <Icon name="briefcase" className="h-4 w-4 text-teal-600" />
                            Personnel &amp; paie
                        </span>
                        <Icon name={ouvert ? 'chevron-up' : 'chevron-down'} className="h-4 w-4 text-ink-400" />
                    </button>

                    <aside className={cn('mb-6 lg:mb-0 lg:block', ouvert ? 'block' : 'hidden')}>
                        <div className="rounded-2xl border border-ink-200 bg-white p-3 dark:border-white/10 dark:bg-ink-900">
                            <p className="hidden px-3 pb-2 pt-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-ink-400 lg:block">
                                Personnel &amp; paie
                            </p>
                            {menu}

                            {/* On sort du module par où l'on y est entré. */}
                            <div className="mt-4 border-t border-ink-100 pt-3 dark:border-white/10">
                                <Link
                                    href={routes.dashboard}
                                    onClick={() => setOuvert(false)}
                                    className="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-ink-600 transition hover:bg-ink-100 hover:text-ink-900 dark:text-ink-300 dark:hover:bg-white/5 dark:hover:text-white"
                                >
                                    <Icon name="arrow-right" className="h-4 w-4 shrink-0 rotate-180" />
                                    <span className="truncate">Retour au portail</span>
                                </Link>
                            </div>
                        </div>
                    </aside>
                </div>

                <div className="min-w-0 space-y-6">
                    {entete}
                    {children}
                </div>
            </div>
        </PortalLayout>
    );
}
