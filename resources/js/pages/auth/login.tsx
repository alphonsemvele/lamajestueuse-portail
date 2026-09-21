import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import HeroCarousel from '@/components/hero-carousel';
import Icon from '@/components/icon';
import Spinner from '@/components/spinner';
import Label from '@/components/label';
import LocaleSwitch from '@/components/locale-switch';
import Logo from '@/components/logo';
import ThemeToggle from '@/components/theme-toggle';
import { cn, routes, useT } from '@/lib/utils';
import type { SharedProps } from '@/types';

const slides = [
    { image: '/images/hero/ecole-cour.jpg', label: 'Groupe scolaire' },
    { image: '/images/hero/ecole-aire-de-jeux.jpg', label: 'Maternelle' },
    { image: '/images/hero/fondation-facade.jpg', label: 'Fondation Médicale' },
    { image: '/images/hero/fondation-accueil.jpg', label: 'Accueil des patients' },
];

export default function Login() {
    const t = useT();
    const { flash } = usePage<SharedProps>().props;
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        username: '',
        password: '',
        remember: false as boolean,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(routes.login);
    };

    const hour = new Date().getHours();
    const greeting = hour < 12 ? t('Bonjour') : hour < 18 ? t('Bon après-midi') : t('Bonsoir');

    const stats: [string, string][] = [
        ['6', t('Applications')],
        ['4', t('Entités')],
        ['24/7', t('Disponibilité')],
        ['SSO', t('Mot de passe')],
    ];

    return (
        <div className="flex min-h-dvh flex-col lg:flex-row">
            <Head title={t('Connexion')} />

            {/* Volet gauche : identité visuelle du groupe, en défilement automatique. */}
            <section className="hidden lg:block lg:w-[56%]">
                <HeroCarousel slides={slides} interval={6500}>
                    <div className="relative flex h-full flex-col justify-between p-12 xl:p-16">
                        <div className="flex items-center gap-3">
                            <Logo size="md" />
                            <div className="leading-tight">
                                <p className="text-lg font-semibold text-white">La Majestueuse</p>
                                <p className="text-xs uppercase tracking-[0.16em] text-white/55">{t('Portail entreprise')}</p>
                            </div>
                        </div>

                        <div className="max-w-xl">
                            <h1 className="text-4xl font-semibold leading-[1.08] tracking-tight text-white xl:text-5xl">
                                {t('Un seul portail.')}
                                <br />
                                <span className="bg-linear-to-r from-brand-300 to-gold-300 bg-clip-text text-transparent">
                                    {t('Toutes vos applications.')}
                                </span>
                            </h1>
                            <p className="mt-6 max-w-md text-[15px] leading-relaxed text-white/70">
                                {t(
                                    "Connectez-vous une seule fois et accédez à l'ensemble des applications du groupe — instituts, établissements scolaires et Fondation médicale.",
                                )}
                            </p>

                            <dl className="mt-10 grid max-w-lg grid-cols-2 gap-3 sm:grid-cols-4">
                                {stats.map(([value, label]) => (
                                    <div key={label} className="rounded-xl border border-white/12 bg-white/8 px-3 py-3 backdrop-blur-sm">
                                        <dt className="text-xl font-semibold text-white">{value}</dt>
                                        <dd className="mt-0.5 text-[10px] font-medium uppercase tracking-[0.12em] text-white/55">{label}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <p className="text-xs text-white/45">La Majestueuse — Yaoundé, Cameroun</p>
                    </div>
                </HeroCarousel>
            </section>

            {/* Volet droit : l'unique point d'authentification de l'écosystème. */}
            <section className="relative flex flex-1 items-center justify-center bg-white px-5 py-10 dark:bg-ink-950 sm:px-10">
                <div className="absolute right-5 top-5 flex items-center gap-2 sm:right-8 sm:top-8">
                    <LocaleSwitch />
                    <ThemeToggle />
                </div>

                <div className="w-full max-w-md">
                    <div className="mb-8 flex items-center gap-3 lg:hidden">
                        <Logo size="sm" />
                        <div className="leading-tight">
                            <p className="font-semibold text-ink-900 dark:text-white">La Majestueuse</p>
                            <p className="text-[11px] uppercase tracking-[0.14em] text-ink-400">{t('Portail entreprise')}</p>
                        </div>
                    </div>

                    <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-600 dark:text-brand-400">{greeting}</p>
                    <h2 className="mt-2 text-3xl font-semibold tracking-tight text-ink-900 dark:text-white">{t('Connexion')}</h2>
                    <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">{t('Saisissez vos identifiants professionnels.')}</p>

                    {flash.status && (
                        <div className="mt-6 flex items-start gap-2.5 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-200">
                            <Icon name="check" className="mt-0.5 h-4 w-4 shrink-0" />
                            <span>{flash.status}</span>
                        </div>
                    )}

                    <div className="mt-7 rounded-2xl border border-ink-200/80 bg-white p-6 shadow-xl shadow-ink-900/5 dark:border-white/10 dark:bg-ink-900 sm:p-7">
                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <Label htmlFor="username" required>
                                    {t('Identifiant')}
                                </Label>
                                <div className="relative mt-2">
                                    <Icon name="user" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                                    <input
                                        id="username"
                                        autoFocus
                                        autoComplete="username"
                                        value={data.username}
                                        onChange={(event) => setData('username', event.target.value)}
                                        placeholder={t('adresse professionnelle ou matricule')}
                                        className={cn('field-input pl-10', errors.username && 'border-red-400 focus:border-red-500 focus:ring-red-500/15')}
                                    />
                                </div>
                                <p className="mt-1.5 text-xs text-ink-400">
                                    {t('Votre matricule ou votre adresse professionnelle, au choix.')}
                                </p>
                            </div>

                            <div>
                                <div className="flex items-baseline justify-between">
                                    <Label htmlFor="password" required>
                                        {t('Mot de passe')}
                                    </Label>
                                    <span className="text-xs font-medium text-brand-600 dark:text-brand-400">{t('Mot de passe oublié ?')}</span>
                                </div>
                                <div className="relative mt-2">
                                    <Icon name="lock" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                                    <input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        autoComplete="current-password"
                                        value={data.password}
                                        onChange={(event) => setData('password', event.target.value)}
                                        placeholder={t('Votre mot de passe')}
                                        className="field-input px-10"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((shown) => !shown)}
                                        aria-label={showPassword ? t('Masquer') : t('Afficher')}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-ink-400 transition hover:text-ink-700 dark:hover:text-white"
                                    >
                                        <Icon name={showPassword ? 'eye-off' : 'eye'} className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            {errors.username && (
                                <p className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300">
                                    <Icon name="alert" className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{errors.username}</span>
                                </p>
                            )}

                            <label className="flex items-center gap-2.5 text-sm text-ink-600 dark:text-ink-300">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(event) => setData('remember', event.target.checked)}
                                    className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                />
                                {t('Rester connecté sur cet appareil')}
                            </label>

                            <button type="submit" disabled={processing} className="btn-primary w-full py-3.5">
                                {processing ? <Spinner /> : <Icon name="login" className="h-4 w-4" />}
                                {processing ? t('Connexion en cours…') : t('Se connecter')}
                            </button>
                        </form>

                        <Link
                            href={routes.register}
                            className="mt-5 flex items-center gap-3 rounded-xl border border-dashed border-brand-300 bg-brand-50/60 px-4 py-3.5 transition hover:border-brand-500 hover:bg-brand-50 dark:border-brand-500/30 dark:bg-brand-500/8 dark:hover:border-brand-500/60"
                        >
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                <Icon name="key" className="h-4 w-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-semibold text-ink-800 dark:text-ink-100">{t('Vous faites partie du personnel ?')}</p>
                                <p className="text-xs leading-snug text-ink-500 dark:text-ink-400">{t('Créez votre compte et choisissez vos instituts.')}</p>
                            </div>
                            <Icon name="chevron-right" className="h-4 w-4 shrink-0 text-brand-500" />
                        </Link>
                    </div>

                    <div className="mt-6 space-y-2.5 text-center">
                        <p className="text-sm text-ink-500 dark:text-ink-400">
                            {t("Besoin d'aide ?")}{' '}
                            <Link href={routes.support} className="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
                                {t('Consulter le centre d’aide')}
                            </Link>
                        </p>
                        <p className="flex items-center justify-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.1em] text-ink-400">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            {t('Canal sécurisé — chiffré')}
                        </p>
                        <p className="text-[11px] text-ink-400">© {new Date().getFullYear()} La Majestueuse · Yaoundé</p>
                    </div>
                </div>
            </section>
        </div>
    );
}
