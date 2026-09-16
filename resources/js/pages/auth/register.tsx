import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Label from '@/components/label';
import LocaleSwitch from '@/components/locale-switch';
import Logo from '@/components/logo';
import PhotoField from '@/components/photo-field';
import ThemeToggle from '@/components/theme-toggle';
import { Card, ErrorSummary, Input } from '@/components/ui';
import { cn, routes, useT } from '@/lib/utils';
import type { Application, SharedProps } from '@/types';

export default function Register({ instituts }: { instituts: Application[] }) {
    const t = useT();
    const { flash } = usePage<SharedProps>().props;
    const registered = flash.registered;

    const { data, setData, post, processing, errors, clearErrors } = useForm<{
        name: string;
        lastname: string;
        sexe: string;
        matricule: string;
        email: string;
        phone: string;
        password: string;
        password_confirmation: string;
        photo: File | null;
        instituts: number[];
        postes: Record<number, string>;
    }>({
        name: '',
        lastname: '',
        sexe: '',
        matricule: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        photo: null,
        instituts: [],
        postes: {},
    });

    const [chosen, setChosen] = useState<number[]>([]);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);

    const toggle = (id: number) => {
        const next = chosen.includes(id) ? chosen.filter((value) => value !== id) : [...chosen, id];
        setChosen(next);
        setData('instituts', next);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(routes.register, { forceFormData: true });
    };

    return (
        <div className="min-h-dvh bg-constellation bg-ink-50 dark:bg-ink-950">
            <Head title={t('Inscription du personnel')} />

            <header className="border-b border-ink-200/70 bg-white/85 backdrop-blur-md dark:border-white/10 dark:bg-ink-950/85">
                <div className="mx-auto flex h-16 max-w-4xl items-center gap-3 px-5">
                    <Link href={routes.login} className="flex items-center gap-2.5">
                        <Logo size="sm" />
                        <span className="leading-tight">
                            <span className="block text-[15px] font-semibold text-ink-900 dark:text-white">La Majestueuse</span>
                            <span className="block text-[10px] uppercase tracking-[0.14em] text-ink-400">{t('Portail entreprise')}</span>
                        </span>
                    </Link>
                    <div className="ml-auto flex items-center gap-2">
                        <LocaleSwitch />
                        <ThemeToggle />
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-4xl px-5 py-10">
                {registered ? (
                    <Card className="mx-auto max-w-2xl p-8 text-center">
                        <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/12 dark:text-emerald-300">
                            <Icon name="check" className="h-7 w-7" />
                        </span>
                        <h1 className="mt-5 text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">
                            {t('Votre demande a bien été enregistrée')}
                        </h1>
                        <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed text-ink-500 dark:text-ink-400">
                            {t(
                                'Votre compte a été créé. Vous pourrez vous connecter avec :identifiant dès qu’un administrateur aura validé votre inscription.',
                                { identifiant: registered ?? '' },
                            )}
                        </p>
                        <Link href={routes.login} className="btn-primary mx-auto mt-7">
                            <Icon name="login" className="h-4 w-4" />
                            {t('Revenir à la connexion')}
                        </Link>
                    </Card>
                ) : (
                    <>
                        <div className="mb-8">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-600 dark:text-brand-400">
                                {t('Personnel du groupe')}
                            </p>
                            <h1 className="mt-2 text-3xl font-semibold tracking-tight text-ink-900 dark:text-white">{t('Inscription')}</h1>
                            <p className="mt-1.5 max-w-2xl text-sm text-ink-500 dark:text-ink-400">
                                {t(
                                    "Renseignez votre identité. Votre compte sera activé après validation par l'administration, qui vous attribuera vos accès.",
                                )}
                            </p>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            <ErrorSummary errors={errors as Record<string, string>} title={t('Veuillez corriger les points suivants :')} />

                            <Card className="p-6">
                                <h2 className="text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">{t('Identité')}</h2>

                                <div className="mt-5">
                                    <Label required>{t('Photo de profil')}</Label>
                                    <div className="mt-2">
                                        <PhotoField
                                            preview={photoPreview}
                                            error={errors.photo}
                                            hint={t('Portrait récent, visage dégagé. JPG, PNG ou WebP — 4 Mo maximum.')}
                                            onPick={(file) => {
                                                setData('photo', file);
                                                setPhotoPreview(URL.createObjectURL(file));
                                                clearErrors('photo');
                                            }}
                                            onDrop={() => {
                                                setData('photo', null);
                                                setPhotoPreview(null);
                                            }}
                                        />
                                    </div>
                                </div>

                                <div className="mt-5 grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="name" required>
                                            {t('Prénom')}
                                        </Label>
                                        <Input id="name" className="mt-2" value={data.name} onChange={(e) => setData('name', e.target.value)} maxLength={80} autoFocus />
                                    </div>
                                    <div>
                                        <Label htmlFor="lastname" required>
                                            {t('Nom de famille')}
                                        </Label>
                                        <Input id="lastname" className="mt-2" value={data.lastname} onChange={(e) => setData('lastname', e.target.value)} maxLength={80} />
                                    </div>

                                    <div>
                                        <Label required>{t('Sexe')}</Label>
                                        <div className="mt-2 grid grid-cols-2 gap-2.5">
                                            {(
                                                [
                                                    ['M', t('Masculin')],
                                                    ['F', t('Féminin')],
                                                ] as const
                                            ).map(([value, label]) => (
                                                <label
                                                    key={value}
                                                    className={cn(
                                                        'flex cursor-pointer items-center gap-2.5 rounded-xl border px-4 py-3 text-sm transition',
                                                        data.sexe === value
                                                            ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10'
                                                            : 'border-ink-200 hover:border-ink-400 dark:border-white/10',
                                                    )}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="sexe"
                                                        value={value}
                                                        checked={data.sexe === value}
                                                        onChange={() => setData('sexe', value)}
                                                        className="h-4 w-4 border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                                    />
                                                    <span className="text-ink-800 dark:text-ink-100">{label}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="matricule" required>
                                            {t('Matricule')}
                                        </Label>
                                        <Input
                                            id="matricule"
                                            className="mt-2 font-mono text-[13px]"
                                            placeholder="LM-0000"
                                            value={data.matricule}
                                            onChange={(e) => setData('matricule', e.target.value)}
                                            maxLength={40}
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="email">{t('Adresse e-mail')}</Label>
                                        <Input id="email" type="email" className="mt-2" value={data.email} onChange={(e) => setData('email', e.target.value)} maxLength={150} />
                                    </div>
                                    <div>
                                        <Label htmlFor="phone" required>
                                            {t('Numéro de téléphone')}
                                        </Label>
                                        <Input
                                            id="phone"
                                            className="mt-2"
                                            placeholder="+237 6 00 00 00 00"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            maxLength={40}
                                        />
                                    </div>
                                </div>
                            </Card>

                            <Card className="p-6">
                                <h2 className="flex flex-wrap items-center gap-2 text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">
                                    {t('Vos instituts')}
                                    <span className="font-normal normal-case tracking-normal text-ink-400">({t('optionnel')})</span>
                                </h2>
                                <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">
                                    {t("Si vous savez déjà où vous exercez, cochez les instituts concernés. Sinon, laissez vide : l'administration vous attribuera vos accès à la validation.")}
                                </p>

                                <div className="mt-5 space-y-3">
                                    {instituts.length === 0 && (
                                        <p className="rounded-xl border border-dashed border-ink-300 px-4 py-10 text-center text-sm text-ink-400 dark:border-white/15">
                                            {t("Aucun institut n'est disponible pour le moment.")}
                                        </p>
                                    )}

                                    {instituts.map((institut) => {
                                        const selected = chosen.includes(institut.id);

                                        return (
                                            <div
                                                key={institut.id}
                                                className={cn(
                                                    'overflow-hidden rounded-xl border transition',
                                                    selected
                                                        ? 'border-brand-400 bg-brand-50/50 dark:border-brand-500/40 dark:bg-brand-500/8'
                                                        : 'border-ink-200 dark:border-white/10',
                                                )}
                                            >
                                                <label className="flex cursor-pointer items-center gap-3 p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selected}
                                                        onChange={() => toggle(institut.id)}
                                                        className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                                    />
                                                    <span
                                                        className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg ring-1 ring-ink-900/5 dark:ring-white/10"
                                                        style={{ color: institut.color, background: `${institut.color}14` }}
                                                    >
                                                        {institut.logoUrl ? (
                                                            <img src={institut.logoUrl} alt="" className="h-full w-full object-contain p-1" />
                                                        ) : (
                                                            <Icon name={institut.icon} className="h-5 w-5" />
                                                        )}
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block text-sm font-semibold text-ink-900 dark:text-white">{institut.name}</span>
                                                        {institut.category && <span className="block text-xs text-ink-400">{institut.category.name}</span>}
                                                    </span>
                                                </label>

                                                {selected && (
                                                    <div className="border-t border-ink-200/70 px-4 py-3.5 dark:border-white/10">
                                                        <Label htmlFor={`poste-${institut.id}`} required>
                                                            {t('Votre poste à :institut', { institut: institut.name })}
                                                        </Label>
                                                        <Input
                                                            id={`poste-${institut.id}`}
                                                            className="mt-2"
                                                            placeholder={t('Enseignant, comptable, infirmier…')}
                                                            value={data.postes[institut.id] ?? ''}
                                                            onChange={(e) => setData('postes', { ...data.postes, [institut.id]: e.target.value })}
                                                            maxLength={120}
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </Card>

                            <Card className="p-6">
                                <h2 className="text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">{t('Mot de passe')}</h2>
                                <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">{t('8 caractères minimum.')}</p>

                                <div className="mt-5 grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="password" required>
                                            {t('Mot de passe')}
                                        </Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            autoComplete="new-password"
                                            className="mt-2"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="password_confirmation" required>
                                            {t('Confirmation')}
                                        </Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            autoComplete="new-password"
                                            className="mt-2"
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                        />
                                    </div>
                                </div>
                            </Card>

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-xs text-ink-400">
                                    {t("Votre compte n'ouvrira aucune application tant qu'un administrateur ne l'aura pas validé.")}
                                </p>
                                <div className="flex shrink-0 gap-2.5">
                                    <Link href={routes.login} className="btn-ghost">
                                        {t('Annuler')}
                                    </Link>
                                    <button type="submit" disabled={processing} className="btn-primary">
                                        <Icon name="check" className="h-4 w-4" />
                                        {t('Envoyer ma demande')}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </>
                )}
            </main>

            <footer className="mx-auto max-w-4xl px-5 pb-10 text-center text-xs text-ink-400">
                © {new Date().getFullYear()} La Majestueuse · Yaoundé
            </footer>
        </div>
    );
}
