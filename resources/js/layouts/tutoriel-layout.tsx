import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import Icon from '@/components/icon';
import LocaleSwitch from '@/components/locale-switch';
import Logo from '@/components/logo';
import ThemeToggle from '@/components/theme-toggle';
import { routes } from '@/lib/utils';
import type { SharedProps } from '@/types';

/**
 * L'écrin des tutoriels.
 *
 * Ils se consultent sans compte : quelqu'un qui n'est pas encore inscrit
 * doit pouvoir lire comment s'inscrire. Le bandeau s'adapte donc à qui
 * regarde — retour au portail pour un employé connecté, connexion ou
 * inscription pour un visiteur.
 */
export default function TutorielLayout({ title, children }: PropsWithChildren<{ title: string }>) {
    const { auth } = usePage<SharedProps>().props;
    const connecte = Boolean(auth?.user);

    return (
        <>
            <Head title={title} />

            <div className="min-h-dvh bg-ink-50 dark:bg-ink-950">
                <header className="sticky top-0 z-30 border-b border-ink-100 bg-white/90 backdrop-blur dark:border-white/10 dark:bg-ink-900/90">
                    <div className="mx-auto flex max-w-5xl items-center gap-1.5 px-4 py-3 sm:gap-3 sm:px-5">
                        <Link href={connecte ? routes.dashboard : routes.login} className="shrink-0">
                            <Logo size="sm" />
                        </Link>

                        <span className="flex-1" />

                        {/*
                          * Langue et thème cèdent la place sur un écran étroit :
                          * ce sont des réglages, pas ce qu'on vient faire ici.
                          */}
                        <span className="hidden items-center gap-2 sm:flex">
                            <LocaleSwitch />
                            <ThemeToggle />
                        </span>

                        {connecte ? (
                            <Link
                                href={routes.dashboard}
                                className="btn-ghost px-2.5 sm:px-3.5"
                                title="Retour au portail"
                            >
                                <Icon name="arrow-right" className="h-4 w-4 rotate-180" />
                                <span className="hidden sm:inline">Retour au portail</span>
                            </Link>
                        ) : (
                            <>
                                {/* Les deux chemins restent offerts, même à l'étroit. */}
                                <Link
                                    href={routes.login}
                                    className="rounded-xl px-2.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-white/5 sm:px-3.5"
                                >
                                    Se connecter
                                </Link>
                                <Link
                                    href={routes.register}
                                    className="rounded-xl bg-brand-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-700 sm:px-4"
                                >
                                    <span className="sm:hidden">S’inscrire</span>
                                    <span className="hidden sm:inline">Créer un compte</span>
                                </Link>
                            </>
                        )}
                    </div>
                </header>

                <main className="mx-auto max-w-5xl px-4 py-7 sm:px-5 sm:py-8">{children}</main>

                <footer className="pb-10 text-center text-xs text-ink-400">
                    © {new Date().getFullYear()} La Majestueuse · Yaoundé
                </footer>
            </div>
        </>
    );
}
