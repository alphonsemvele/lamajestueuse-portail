import { Link } from '@inertiajs/react';
import Icon from '@/components/icon';
import { Card } from '@/components/ui';
import TutorielLayout from '@/layouts/tutoriel-layout';
import { routes } from '@/lib/utils';

interface Etape {
    titre: string;
    texte: string;
    points: string[];
    capture: string | null;
}

interface Props {
    prealables: { titre: string; resume: string | null; etapes: Etape[] };
    tutoriels: { cle: string; titre: string; resume: string; icone: string; duree: string | null; etapes: number }[];
}

export default function Tutoriels({ prealables, tutoriels }: Props) {
    return (
        <TutorielLayout title="Tutoriels">
            <div className="space-y-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">Tutoriels</h1>
                    <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">
                        Comment se servir du portail, module par module. Chaque tutoriel se suit pas à pas, à votre rythme.
                    </p>
                </header>

                {/* Les prérequis valent pour tous : on les annonce avant la liste. */}
                <Card className="border-amber-200 bg-amber-50/50 p-5 dark:border-amber-500/20 dark:bg-amber-500/5">
                    <div className="flex items-start gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                            <Icon name="alert" className="h-4 w-4" />
                        </span>
                        <div className="min-w-0">
                            <h2 className="text-sm font-semibold text-ink-900 dark:text-white">{prealables.titre}</h2>
                            {prealables.resume && (
                                <p className="mt-1 text-sm text-ink-600 dark:text-ink-300">{prealables.resume}</p>
                            )}
                            <ol className="mt-3 space-y-1.5">
                                {prealables.etapes.map((etape, rang) => (
                                    <li key={etape.titre} className="flex gap-2.5 text-sm text-ink-700 dark:text-ink-200">
                                        <span className="font-semibold text-amber-700 dark:text-amber-300">{rang + 1}.</span>
                                        {etape.titre}
                                    </li>
                                ))}
                            </ol>
                            <p className="mt-2.5 text-xs text-ink-500 dark:text-ink-400">
                                Chaque tutoriel les reprend en détail avant d’entrer dans le vif du sujet.
                            </p>
                        </div>
                    </div>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2">
                    {tutoriels.map((tutoriel) => (
                        <Link
                            key={tutoriel.cle}
                            href={routes.tutoriels.show(tutoriel.cle)}
                            className="group rounded-2xl border border-ink-200 bg-white p-5 transition hover:border-teal-300 hover:shadow-md dark:border-white/10 dark:bg-ink-900 dark:hover:border-teal-500/40"
                        >
                            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300">
                                <Icon name={tutoriel.icone} className="h-5 w-5" />
                            </span>

                            <h2 className="mt-4 text-[15px] font-semibold text-ink-900 group-hover:text-teal-700 dark:text-white dark:group-hover:text-teal-300">
                                {tutoriel.titre}
                            </h2>
                            <p className="mt-1.5 text-sm leading-relaxed text-ink-500 dark:text-ink-400">{tutoriel.resume}</p>

                            <p className="mt-4 flex items-center gap-3 text-xs text-ink-400">
                                <span className="inline-flex items-center gap-1.5">
                                    <Icon name="layers" className="h-3.5 w-3.5" />
                                    {tutoriel.etapes} étapes
                                </span>
                                {tutoriel.duree && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Icon name="clock" className="h-3.5 w-3.5" />
                                        {tutoriel.duree}
                                    </span>
                                )}
                            </p>
                        </Link>
                    ))}

                    {/* Les suivants viendront : on le dit plutôt que de laisser croire à un oubli. */}
                    <div className="flex items-center justify-center rounded-2xl border border-dashed border-ink-200 p-5 text-center text-sm text-ink-400 dark:border-white/10">
                        Les tutoriels des autres modules arrivent.
                    </div>
                </div>
            </div>
        </TutorielLayout>
    );
}
