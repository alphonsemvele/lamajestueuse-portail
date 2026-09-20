import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Icon from '@/components/icon';
import LocaleSwitch from '@/components/locale-switch';
import Logo from '@/components/logo';
import ThemeToggle from '@/components/theme-toggle';
import { cn, routes, useT } from '@/lib/utils';

interface Etape {
    titre: string;
    texte: string;
    capture: string | null;
}

interface Rubrique {
    cle: string;
    question: string;
    reponse: string | null;
    points: string[];
    etapes: Etape[];
}

interface Props {
    rubriques: Rubrique[];
    contact: { email: string; telephone: string; horaires: string; adresse: string };
    retour: 'connexion' | 'inscription';
}

export default function Support({ rubriques, contact, retour }: Props) {
    const t = useT();
    const [recherche, setRecherche] = useState('');
    const [ouverte, setOuverte] = useState<string | null>(rubriques[0]?.cle ?? null);
    const [agrandie, setAgrandie] = useState<{ url: string; titre: string } | null>(null);

    // Un lien peut viser une question précise : /support#creer-compte
    useEffect(() => {
        const cle = window.location.hash.replace('#', '');
        if (cle && rubriques.some((r) => r.cle === cle)) {
            setOuverte(cle);
            document.getElementById(cle)?.scrollIntoView({ block: 'center' });
        }
    }, [rubriques]);

    useEffect(() => {
        if (!agrandie) return;
        const fermer = (e: KeyboardEvent) => e.key === 'Escape' && setAgrandie(null);
        document.addEventListener('keydown', fermer);

        return () => document.removeEventListener('keydown', fermer);
    }, [agrandie]);

    const terme = recherche.trim().toLowerCase();
    const visibles = terme
        ? rubriques.filter((r) =>
              `${r.question} ${r.reponse ?? ''} ${r.points.join(' ')} ${r.etapes.map((e) => `${e.titre} ${e.texte}`).join(' ')}`
                  .toLowerCase()
                  .includes(terme),
          )
        : rubriques;

    return (
        <>
            <Head title={t('Aide et support')} />

            <div className="min-h-dvh bg-ink-50 dark:bg-ink-950">
                <header className="sticky top-0 z-30 border-b border-ink-100 bg-white/90 backdrop-blur dark:border-white/10 dark:bg-ink-900/90">
                    <div className="mx-auto flex max-w-4xl items-center gap-3 px-5 py-3.5">
                        <Link href={routes.login} className="shrink-0">
                            <Logo size="sm" />
                        </Link>
                        <span className="flex-1" />
                        <LocaleSwitch />
                        <ThemeToggle />
                        <Link
                            href={retour === 'inscription' ? routes.register : routes.login}
                            className="btn-ghost hidden sm:inline-flex"
                        >
                            <Icon name="arrow-right" className="h-4 w-4 rotate-180" />
                            {retour === 'inscription' ? t("Retour à l'inscription") : t('Retour à la connexion')}
                        </Link>
                    </div>
                </header>

                <main className="mx-auto max-w-4xl px-5 py-10">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">
                        {t('Centre d’aide')}
                    </p>
                    <h1 className="mt-2 text-3xl font-bold tracking-tight text-ink-900 dark:text-white">
                        {t('Comment pouvons-nous vous aider ?')}
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm leading-relaxed text-ink-500 dark:text-ink-400">
                        {t('Les réponses aux questions les plus fréquentes, avec les captures d’écran correspondantes. Si vous ne trouvez pas votre réponse, le support répond du lundi au vendredi.')}
                    </p>

                    <div className="relative mt-6">
                        <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                        <input
                            type="search"
                            value={recherche}
                            onChange={(event) => setRecherche(event.target.value)}
                            placeholder={t('Rechercher une question…')}
                            className="field-input pl-10"
                        />
                    </div>

                    <div className="mt-6 space-y-3">
                        {visibles.map((rubrique) => {
                            const ouvert = ouverte === rubrique.cle;

                            return (
                                <section
                                    key={rubrique.cle}
                                    id={rubrique.cle}
                                    className="overflow-hidden rounded-2xl border border-ink-200 bg-white dark:border-white/10 dark:bg-ink-900"
                                >
                                    <h2>
                                        <button
                                            type="button"
                                            onClick={() => setOuverte(ouvert ? null : rubrique.cle)}
                                            aria-expanded={ouvert}
                                            className="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-ink-50/70 dark:hover:bg-white/5"
                                        >
                                            <span className="flex-1 text-[15px] font-semibold text-ink-900 dark:text-white">{rubrique.question}</span>
                                            <Icon
                                                name="chevron-down"
                                                className={cn('h-4 w-4 shrink-0 text-ink-400 transition', ouvert && 'rotate-180')}
                                            />
                                        </button>
                                    </h2>

                                    {ouvert && (
                                        <div className="border-t border-ink-100 px-5 py-5 dark:border-white/10">
                                            {rubrique.reponse && (
                                                <p className="text-sm leading-relaxed text-ink-600 dark:text-ink-300">{rubrique.reponse}</p>
                                            )}

                                            {rubrique.points.length > 0 && (
                                                <ul className="mt-4 space-y-2">
                                                    {rubrique.points.map((point) => (
                                                        <li key={point} className="flex gap-2.5 text-sm text-ink-600 dark:text-ink-300">
                                                            <Icon name="check" className="mt-0.5 h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                                            <span>{point}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}

                                            {rubrique.etapes.length > 0 && (
                                                <ol className="mt-5 space-y-6">
                                                    {rubrique.etapes.map((etape, index) => (
                                                        <li key={etape.titre} className="flex gap-4">
                                                            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[13px] font-semibold text-white">
                                                                {index + 1}
                                                            </span>
                                                            <div className="min-w-0 flex-1">
                                                                <p className="text-sm font-semibold text-ink-900 dark:text-white">{etape.titre}</p>
                                                                <p className="mt-1 text-sm leading-relaxed text-ink-600 dark:text-ink-300">{etape.texte}</p>

                                                                {etape.capture && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setAgrandie({ url: etape.capture as string, titre: etape.titre })}
                                                                        className="group mt-3 block w-full overflow-hidden rounded-xl border border-ink-200 bg-ink-50 dark:border-white/10 dark:bg-ink-800"
                                                                    >
                                                                        <img
                                                                            src={etape.capture}
                                                                            alt={etape.titre}
                                                                            loading="lazy"
                                                                            className="w-full object-cover transition duration-300 group-hover:scale-[1.01]"
                                                                        />
                                                                        <span className="flex items-center justify-center gap-1.5 border-t border-ink-200 px-3 py-2 text-[12px] text-ink-500 dark:border-white/10 dark:text-ink-400">
                                                                            <Icon name="search" className="h-3.5 w-3.5" />
                                                                            {t('Agrandir la capture')}
                                                                        </span>
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ol>
                                            )}
                                        </div>
                                    )}
                                </section>
                            );
                        })}

                        {visibles.length === 0 && (
                            <p className="rounded-2xl border border-dashed border-ink-200 px-5 py-10 text-center text-sm text-ink-500 dark:border-white/10 dark:text-ink-400">
                                {t('Aucune question ne correspond à votre recherche. Écrivez-nous, nous vous répondrons.')}
                            </p>
                        )}
                    </div>

                    <section className="mt-8 rounded-2xl border border-brand-200 bg-brand-50/60 p-6 dark:border-brand-500/25 dark:bg-brand-500/10">
                        <h2 className="text-base font-semibold text-ink-900 dark:text-white">{t('Toujours bloqué ?')}</h2>
                        <p className="mt-1.5 text-sm text-ink-600 dark:text-ink-300">
                            {t('Indiquez votre nom complet et votre matricule : la réponse sera plus rapide.')}
                        </p>

                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <a
                                href={`mailto:${contact.email}`}
                                className="flex items-center gap-3 rounded-xl border border-ink-200 bg-white px-4 py-3 transition hover:border-brand-300 dark:border-white/10 dark:bg-ink-900"
                            >
                                <Icon name="mail" className="h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                <span className="min-w-0">
                                    <span className="block text-[11px] uppercase tracking-wide text-ink-400">{t('E-mail')}</span>
                                    <span className="block truncate text-sm font-medium text-ink-900 dark:text-white">{contact.email}</span>
                                </span>
                            </a>
                            <a
                                href={`tel:${contact.telephone.replace(/\s/g, '')}`}
                                className="flex items-center gap-3 rounded-xl border border-ink-200 bg-white px-4 py-3 transition hover:border-brand-300 dark:border-white/10 dark:bg-ink-900"
                            >
                                <Icon name="phone" className="h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                <span className="min-w-0">
                                    <span className="block text-[11px] uppercase tracking-wide text-ink-400">{t('Téléphone')}</span>
                                    <span className="block truncate text-sm font-medium text-ink-900 dark:text-white">{contact.telephone}</span>
                                </span>
                            </a>
                        </div>

                        <p className="mt-3 text-xs text-ink-500 dark:text-ink-400">
                            {contact.horaires} · {contact.adresse}
                        </p>
                    </section>

                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link href={routes.login} className="btn-primary">
                            <Icon name="login" className="h-4 w-4" />
                            {t('Se connecter')}
                        </Link>
                        <Link href={routes.register} className="btn-ghost">
                            {t('Créer mon compte')}
                        </Link>
                    </div>
                </main>

                <footer className="border-t border-ink-100 py-6 text-center text-xs text-ink-400 dark:border-white/10">
                    © {new Date().getFullYear()} La Majestueuse · {t('Portail entreprise')}
                </footer>
            </div>

            {agrandie && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label={agrandie.titre}
                    onClick={() => setAgrandie(null)}
                    className="fixed inset-0 z-50 flex items-center justify-center bg-ink-900/70 p-4 backdrop-blur-sm"
                >
                    <figure className="max-h-full w-full max-w-3xl overflow-auto rounded-2xl bg-white p-2 shadow-2xl dark:bg-ink-900">
                        <img src={agrandie.url} alt={agrandie.titre} className="w-full rounded-xl" />
                        <figcaption className="flex items-center justify-between gap-3 px-2 py-2 text-sm text-ink-600 dark:text-ink-300">
                            {agrandie.titre}
                            <button type="button" onClick={() => setAgrandie(null)} className="btn-ghost py-1.5">
                                {t('Fermer')}
                            </button>
                        </figcaption>
                    </figure>
                </div>
            )}
        </>
    );
}
