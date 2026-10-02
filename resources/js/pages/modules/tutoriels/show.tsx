import { Link } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '@/components/icon';
import { Card } from '@/components/ui';
import TutorielLayout from '@/layouts/tutoriel-layout';
import { cn, routes } from '@/lib/utils';

interface Etape {
    titre: string;
    texte: string;
    points: string[];
    capture: string | null;
}

interface Props {
    tutoriel: { cle: string; titre: string; resume: string; duree: string | null; etapes: Etape[] };
    prealables: { titre: string; resume: string | null; etapes: Etape[] };
    module: { nom: string; url: string } | null;
}

export default function Tutoriel({ tutoriel, prealables, module }: Props) {
    /*
     * Un tutoriel se suit d'un bout à l'autre : les préalables puis le
     * module, dans une seule suite d'étapes. On garde la frontière visible
     * pour que celui qui a déjà un compte sache où commencer.
     */
    const etapes = [
        ...prealables.etapes.map((etape) => ({ ...etape, section: prealables.titre })),
        ...tutoriel.etapes.map((etape) => ({ ...etape, section: tutoriel.titre })),
    ];

    const debutDuModule = prealables.etapes.length;
    const [courante, setCourante] = useState(0);
    const etape = etapes[courante];
    const avancement = Math.round(((courante + 1) / etapes.length) * 100);

    return (
        <TutorielLayout title={tutoriel.titre}>
            <div className="space-y-5">
                <Link
                    href={routes.tutoriels.index}
                    className="inline-flex items-center gap-1.5 text-sm text-ink-500 transition hover:text-ink-800 dark:text-ink-400 dark:hover:text-white"
                >
                    <Icon name="chevron-right" className="h-4 w-4 rotate-180" />
                    Tous les tutoriels
                </Link>

                <header>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">{tutoriel.titre}</h1>
                    <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">{tutoriel.resume}</p>
                </header>

                {/* L'avancement : savoir où l'on en est et ce qu'il reste. */}
                <div>
                    <div className="flex items-baseline justify-between text-xs text-ink-500 dark:text-ink-400">
                        <span>
                            Étape {courante + 1} sur {etapes.length}
                        </span>
                        <span>{avancement} %</span>
                    </div>
                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-white/10">
                        <div
                            className="h-full rounded-full bg-teal-600 transition-all"
                            style={{ width: `${avancement}%` }}
                        />
                    </div>
                </div>

                <div className="grid gap-5 lg:grid-cols-[260px_1fr]">
                    {/* Le sommaire : on saute où l'on veut, on ne subit pas l'ordre. */}
                    <nav className="lg:sticky lg:top-24 lg:self-start">
                        <ol className="space-y-1">
                            {etapes.map((item, rang) => (
                                <li key={`${item.section}-${item.titre}`}>
                                    {rang === 0 && (
                                        <p className="mb-1 mt-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-ink-400">
                                            {prealables.titre}
                                        </p>
                                    )}
                                    {rang === debutDuModule && (
                                        <p className="mb-1 mt-3 text-[11px] font-semibold uppercase tracking-[0.1em] text-ink-400">
                                            {tutoriel.titre}
                                        </p>
                                    )}

                                    <button
                                        type="button"
                                        onClick={() => setCourante(rang)}
                                        className={cn(
                                            'flex w-full items-start gap-2.5 rounded-xl px-3 py-2 text-left text-sm transition',
                                            rang === courante
                                                ? 'bg-teal-600 text-white'
                                                : 'text-ink-600 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-white/5',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                                                rang === courante
                                                    ? 'bg-white/20 text-white'
                                                    : rang < courante
                                                      ? 'bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-300'
                                                      : 'bg-ink-100 text-ink-500 dark:bg-white/10 dark:text-ink-400',
                                            )}
                                        >
                                            {rang < courante ? '✓' : rang + 1}
                                        </span>
                                        <span className="min-w-0">{item.titre}</span>
                                    </button>
                                </li>
                            ))}
                        </ol>
                    </nav>

                    <div className="space-y-4">
                        <Card className="p-6">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.1em] text-teal-700 dark:text-teal-300">
                                {etape.section}
                            </p>
                            <h2 className="mt-1.5 text-lg font-semibold text-ink-900 dark:text-white">{etape.titre}</h2>
                            <p className="mt-3 text-[15px] leading-relaxed text-ink-700 dark:text-ink-200">{etape.texte}</p>

                            {etape.points.length > 0 && (
                                <ul className="mt-4 space-y-2">
                                    {etape.points.map((point) => (
                                        <li key={point} className="flex gap-2.5 text-sm text-ink-600 dark:text-ink-300">
                                            <Icon name="check" className="mt-0.5 h-4 w-4 shrink-0 text-teal-600 dark:text-teal-400" />
                                            {point}
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {etape.capture && (
                                <img
                                    src={etape.capture}
                                    alt=""
                                    className="mt-5 w-full rounded-xl border border-ink-200 dark:border-white/10"
                                />
                            )}
                        </Card>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <button
                                type="button"
                                onClick={() => setCourante((rang) => Math.max(0, rang - 1))}
                                disabled={courante === 0}
                                className="inline-flex items-center gap-2 rounded-xl border border-ink-200 px-4 py-2.5 text-sm font-medium text-ink-700 transition hover:bg-ink-50 disabled:opacity-40 dark:border-white/10 dark:text-ink-200 dark:hover:bg-white/5"
                            >
                                <Icon name="chevron-right" className="h-4 w-4 rotate-180" />
                                Précédent
                            </button>

                            {courante < etapes.length - 1 ? (
                                <button
                                    type="button"
                                    onClick={() => setCourante((rang) => rang + 1)}
                                    className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-teal-700"
                                >
                                    Étape suivante
                                    <Icon name="chevron-right" className="h-4 w-4" />
                                </button>
                            ) : module ? (
                                /* Fin du tutoriel : on propose de passer à l'acte. */
                                <a
                                    href={module.url}
                                    className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-teal-700"
                                >
                                    Ouvrir {module.nom}
                                    <Icon name="chevron-right" className="h-4 w-4" />
                                </a>
                            ) : (
                                <span className="text-sm text-ink-400">C’est tout : vous savez faire.</span>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </TutorielLayout>
    );
}
