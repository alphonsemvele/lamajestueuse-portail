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
                    <div className="mx-auto flex max-w-5xl items-center gap-3 px-5 py-3">
                        <Link href={connecte ? routes.dashboard : routes.login} className="shrink-0">
                            <Logo size="sm" />
                        </Link>

                        <span className="flex-1" />

                        <LocaleSwitch />
                        <ThemeToggle />

                        {connecte ? (
                            <Link href={routes.dashboard} className="btn-ghost hidden sm:inline-flex">
                                <Icon name="arrow-right" className="h-4 w-4 rotate-180" />
                                Retour au portail
                            </Link>
                        ) : (
                            <>
                                <Link href={routes.login} className="btn-ghost hidden sm:inline-flex">
                                    Se connecter
                                </Link>
                                <Link href={routes.register} className="btn-primary">
                                    Créer un compte
                                </Link>
                            </>
                        )}
                    </div>
                </header>

                <main className="mx-auto max-w-5xl px-5 py-8">{children}</main>

                <footer className="pb-10 text-center text-xs text-ink-400">
                    © {new Date().getFullYear()} La Majestueuse · Yaoundé
                </footer>
            </div>
        </>
    );
}
