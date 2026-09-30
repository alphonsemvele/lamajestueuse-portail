import { useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Spinner from '@/components/spinner';
import { Alert, Card, Input, Select } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { routes, useT } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Props {
    reglages: {
        actif: boolean;
        hote: string | null;
        port: number | null;
        identifiant: string | null;
        chiffrement: string;
        expediteur: string | null;
        nomExpediteur: string | null;
        motDePasseEnregistre: boolean;
        testeLe: string | null;
    };
    chiffrements: Record<string, string>;
    active: {
        transport: string;
        hote: string | null;
        port: number | null;
        expediteur: string | null;
        nomExpediteur: string | null;
    };
}

export default function ReglagesEmail({ reglages, chiffrements, active }: Props) {
    const t = useT();
    const { errors: pageErrors } = usePage<SharedProps & { errors: Record<string, string> }>().props;

    const formulaire = useForm({
        actif: reglages.actif,
        hote: reglages.hote ?? '',
        port: reglages.port ?? 587,
        identifiant: reglages.identifiant ?? '',
        mot_de_passe: '',
        chiffrement: reglages.chiffrement,
        expediteur: reglages.expediteur ?? 'info@lamajestueuse.com',
        nom_expediteur: reglages.nomExpediteur ?? 'La Majestueuse',
    });

    const essai = useForm({ destinataire: '' });
    const [envoye, setEnvoye] = useState(false);

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();
        formulaire.put(routes.admin.email, {
            preserveScroll: true,
            onSuccess: () => formulaire.setData('mot_de_passe', ''),
        });
    };

    const tester = (event: FormEvent) => {
        event.preventDefault();
        setEnvoye(false);
        essai.post(routes.admin.emailTest, {
            preserveScroll: true,
            onSuccess: () => setEnvoye(true),
        });
    };

    const champ = (
        nom: 'hote' | 'identifiant' | 'expediteur' | 'nom_expediteur',
        libelle: string,
        options: { type?: string; placeholder?: string; mono?: boolean } = {},
    ) => (
        <label className="block">
            <span className="mb-1 block text-[11px] font-semibold uppercase tracking-[0.07em] text-ink-500">
                {libelle}
            </span>
            <Input
                type={options.type ?? 'text'}
                value={formulaire.data[nom]}
                onChange={(event) => formulaire.setData(nom, event.target.value)}
                placeholder={options.placeholder}
                className={options.mono ? 'font-mono text-[13px]' : undefined}
            />
            {formulaire.errors[nom] && (
                <span className="mt-1 block text-xs font-medium text-red-600">{formulaire.errors[nom]}</span>
            )}
        </label>
    );

    return (
        <AdminLayout
            title={t('Réglages e-mail')}
            heading={t('Réglages e-mail')}
            subheading={t("Le relais par lequel le portail envoie ses messages, et de quoi l'essayer.")}
        >
            <div className="mx-auto max-w-3xl space-y-5">
                <Alert tone="info" icon="mail">
                    {t('Configuration active')} — {t('transport')} : <strong>{active.transport}</strong> ·{' '}
                    {t('expéditeur')} : <strong>{active.expediteur ?? '—'}</strong>
                    {active.hote && (
                        <>
                            {' '}
                            · {t('hôte')} : <strong>{active.hote}</strong>
                        </>
                    )}
                </Alert>

                {pageErrors.essai && (
                    <Alert tone="danger" icon="alert">
                        {pageErrors.essai}
                    </Alert>
                )}

                <Card className="p-6">
                    <form onSubmit={enregistrer} className="space-y-5">
                        <label className="flex cursor-pointer items-center gap-3">
                            <input
                                type="checkbox"
                                checked={formulaire.data.actif}
                                onChange={(event) => formulaire.setData('actif', event.target.checked)}
                                className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                            />
                            <span className="text-sm font-semibold text-ink-900 dark:text-white">
                                {t('Activer ces réglages SMTP')}
                            </span>
                            <span className="text-xs text-ink-400">{t('sinon, configuration du serveur')}</span>
                        </label>

                        <div className="grid gap-5 sm:grid-cols-2">
                            {champ('hote', t('Serveur SMTP (hôte)'), { placeholder: 'smtp-relay.brevo.com', mono: true })}

                            <label className="block">
                                <span className="mb-1 block text-[11px] font-semibold uppercase tracking-[0.07em] text-ink-500">
                                    {t('Port')}
                                </span>
                                <Input
                                    type="number"
                                    min={1}
                                    max={65535}
                                    value={formulaire.data.port}
                                    onChange={(event) => formulaire.setData('port', Number(event.target.value))}
                                />
                                {formulaire.errors.port && (
                                    <span className="mt-1 block text-xs font-medium text-red-600">
                                        {formulaire.errors.port}
                                    </span>
                                )}
                            </label>

                            {champ('identifiant', t('Identifiant'), { mono: true })}

                            <label className="block">
                                <span className="mb-1 block text-[11px] font-semibold uppercase tracking-[0.07em] text-ink-500">
                                    {t('Chiffrement')}
                                </span>
                                <Select
                                    value={formulaire.data.chiffrement}
                                    onChange={(event) => formulaire.setData('chiffrement', event.target.value)}
                                >
                                    {Object.entries(chiffrements).map(([cle, libelle]) => (
                                        <option key={cle} value={cle}>
                                            {libelle}
                                        </option>
                                    ))}
                                </Select>
                            </label>
                        </div>

                        <label className="block">
                            <span className="mb-1 block text-[11px] font-semibold uppercase tracking-[0.07em] text-ink-500">
                                {reglages.motDePasseEnregistre
                                    ? t('Mot de passe — laisser vide pour garder l’actuel')
                                    : t('Mot de passe')}
                            </span>
                            <Input
                                type="password"
                                value={formulaire.data.mot_de_passe}
                                onChange={(event) => formulaire.setData('mot_de_passe', event.target.value)}
                                placeholder={reglages.motDePasseEnregistre ? '•••••••• (inchangé)' : ''}
                                autoComplete="new-password"
                            />
                            {formulaire.errors.mot_de_passe && (
                                <span className="mt-1 block text-xs font-medium text-red-600">
                                    {formulaire.errors.mot_de_passe}
                                </span>
                            )}
                        </label>

                        <div className="grid gap-5 sm:grid-cols-2">
                            {champ('expediteur', t('Expéditeur'), { type: 'email', placeholder: 'info@lamajestueuse.com' })}
                            {champ('nom_expediteur', t("Nom de l'expéditeur"), { placeholder: 'La Majestueuse' })}
                        </div>

                        <p className="text-xs text-ink-500 dark:text-ink-400">
                            {t("L'expéditeur doit être une adresse vérifiée chez le fournisseur d'envoi, sur le domaine du groupe. Sinon les messages partent sans erreur mais sont classés en indésirables.")}
                        </p>

                        <button type="submit" disabled={formulaire.processing} className="btn-primary w-full">
                            {formulaire.processing ? <Spinner /> : <Icon name="check" className="h-4 w-4" />}
                            {formulaire.processing ? t('Enregistrement…') : t('Enregistrer les réglages')}
                        </button>
                    </form>
                </Card>

                <Card className="p-6">
                    <h2 className="text-sm font-semibold text-ink-900 dark:text-white">{t('Envoyer un e-mail de test')}</h2>
                    <p className="mt-1 text-xs text-ink-500 dark:text-ink-400">
                        {reglages.testeLe
                            ? t('Dernier essai réussi le :date.', { date: reglages.testeLe })
                            : t("Aucun essai n'a encore abouti.")}
                    </p>

                    <form onSubmit={tester} className="mt-4 flex flex-wrap gap-3">
                        <div className="min-w-[220px] flex-1">
                            <Input
                                type="email"
                                value={essai.data.destinataire}
                                onChange={(event) => essai.setData('destinataire', event.target.value)}
                                placeholder="vous@exemple.com"
                                required
                            />
                            {essai.errors.destinataire && (
                                <span className="mt-1 block text-xs font-medium text-red-600">
                                    {essai.errors.destinataire}
                                </span>
                            )}
                        </div>
                        <button type="submit" disabled={essai.processing} className="btn-primary shrink-0">
                            {essai.processing ? <Spinner /> : <Icon name="mail" className="h-4 w-4" />}
                            {essai.processing ? t('Envoi…') : t('Tester')}
                        </button>
                    </form>

                    {envoye && (
                        <p className="mt-3 text-xs text-emerald-700 dark:text-emerald-300">
                            {t("Si le message n'arrive pas, regardez aussi les indésirables.")}
                        </p>
                    )}
                </Card>
            </div>
        </AdminLayout>
    );
}
