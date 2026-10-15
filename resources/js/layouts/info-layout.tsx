import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import Icon from '@/components/icon';
import Logo from '@/components/logo';
import ThemeToggle from '@/components/theme-toggle';
import { cn, routes } from '@/lib/utils';
import type { Rubrique, SharedProps } from '@/types';

/**
 * L'écrin de LA MAJESTUEUSE Info.
 *
 * La salle de presse se lit sans compte : quelqu'un qui ouvre un lien reçu
 * sur WhatsApp doit tomber sur l'article, pas sur une page de connexion. Le
 * bandeau s'adapte donc à qui regarde.
 */
export default function InfoLayout({
    titre,
    rubriques = [],
    active = null,
    children,
}: PropsWithChildren<{ titre: string; rubriques?: Rubrique[]; active?: string | null }>) {
    const { auth } = usePage<SharedProps>().props;
    const connecte = Boolean(auth?.user);

    return (
        <>
            <Head title={titre} />

            <div className="flex min-h-dvh flex-col bg-ink-50 dark:bg-ink-950">
                <header className="sticky top-0 z-30 border-b border-ink-100 bg-white/90 backdrop-blur dark:border-white/10 dark:bg-ink-900/90">
                    <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6">
                        <Link href={routes.info.index} className="flex shrink-0 items-center gap-2.5">
                            <Logo size="sm" />
                            <span className="hidden text-sm font-semibold uppercase tracking-[0.18em] text-brand-700 dark:text-brand-300 sm:inline">
                                Info
                            </span>
                        </Link>

                        <span className="flex-1" />

                        <span className="hidden sm:inline">
                            <ThemeToggle />
                        </span>

                        {connecte ? (
                            <Link href={routes.dashboard} className="btn-ghost px-2.5 sm:px-3.5" title="Retour au portail">
                                <Icon name="arrow-right" className="h-4 w-4 rotate-180" />
                                <span className="hidden sm:inline">Le portail</span>
                            </Link>
                        ) : (
                            <Link
                                href={routes.login}
                                className="rounded-xl bg-brand-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-700 sm:px-4"
                            >
                                Espace personnel
                            </Link>
                        )}
                    </div>

                    {/*
                      * Les rubriques tiennent le rôle d'un sommaire de journal.
                      * Elles défilent horizontalement sur un téléphone plutôt
                      * que de passer à la ligne et de pousser le contenu.
                      */}
                    {rubriques.length > 0 && (
                        <nav className="mx-auto max-w-6xl overflow-x-auto px-4 pb-2 sm:px-6">
                            <ul className="flex items-center gap-1.5 whitespace-nowrap text-sm">
                                <li>
                                    <Link
                                        href={routes.info.index}
                                        className={cn(
                                            'inline-block rounded-lg px-3 py-1.5 font-medium transition',
                                            active === null
                                                ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-900'
                                                : 'text-ink-600 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-white/5',
                                        )}
                                    >
                                        À la une
                                    </Link>
                                </li>
                                {rubriques.map((rubrique) => (
                                    <li key={rubrique.slug}>
                                        <Link
                                            href={`${routes.info.index}?rubrique=${rubrique.slug}`}
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium transition',
                                                active === rubrique.slug
                                                    ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-900'
                                                    : 'text-ink-600 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-white/5',
                                            )}
                                        >
                                            <span
                                                className="h-2 w-2 rounded-full"
                                                style={{ backgroundColor: rubrique.couleur }}
                                                aria-hidden
                                            />
                                            {rubrique.nom}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    )}
                </header>

                {/*
                  * « flex-1 » tient le pied de page en bas : une page courte
                  * — une rubrique vide, un article bref — le laissait
                  * flotter au milieu de l'ecran.
                  */}
                <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-10">{children}</main>

                <footer className="mt-auto border-t border-ink-100 bg-white py-8 dark:border-white/10 dark:bg-ink-900">
                    <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 text-sm text-ink-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:text-ink-400">
                        <p className="font-medium text-ink-700 dark:text-ink-200">Groupe La Majestueuse — Yaoundé</p>
                        <p>IUM · IFPM · GSBM — © {new Date().getFullYear()}</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
