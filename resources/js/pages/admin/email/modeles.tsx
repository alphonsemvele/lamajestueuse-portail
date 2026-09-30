import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '@/components/icon';
import Spinner from '@/components/spinner';
import { Alert, Card, Input } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { routes, useT } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Courriel {
    cle: string;
    titre: string;
    description: string;
}

interface Props {
    groupes: { nom: string; courriels: Courriel[] }[];
    destinataire: string | null;
    envoi: { transport: string; expediteur: string | null };
}

export default function ModelesEmail({ groupes, destinataire, envoi }: Props) {
    const t = useT();
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;

    const [adresse, setAdresse] = useState(destinataire ?? '');
    const [enCours, setEnCours] = useState<string | null>(null);

    const envoyer = (courriel: Courriel) => {
        if (!adresse) return;

        setEnCours(courriel.cle);
        router.post(
            routes.admin.emailEnvoyer(courriel.cle),
            { destinataire: adresse },
            { preserveScroll: true, onFinish: () => setEnCours(null) },
        );
    };

    return (
        <AdminLayout
            title={t("Modèles d'e-mail")}
            heading={t("Modèles d'e-mail")}
            subheading={t("Relisez ou envoyez n'importe quel e-mail de la plateforme, sans avoir à reproduire la situation qui le déclenche. Les données affichées sont des exemples ; rien n'est enregistré.")}
        >
            <div className="mx-auto max-w-3xl space-y-5">
                {errors.envoi && (
                    <Alert tone="danger" icon="alert">
                        {errors.envoi}{' '}
                        <Link href={routes.admin.email} className="font-medium underline">
                            {t('Vérifier les réglages e-mail')}
                        </Link>
                    </Alert>
                )}

                {errors.destinataire && (
                    <Alert tone="danger" icon="alert">
                        {errors.destinataire}
                    </Alert>
                )}

                <Card className="p-5">
                    <label className="block">
                        <span className="mb-1 block text-[11px] font-semibold uppercase tracking-[0.07em] text-ink-500">
                            {t('Adresse qui recevra les envois de test')}
                        </span>
                        <Input
                            type="email"
                            value={adresse}
                            onChange={(event) => setAdresse(event.target.value)}
                            placeholder="vous@exemple.com"
                        />
                    </label>
                    <p className="mt-2 text-xs text-ink-500 dark:text-ink-400">
                        {t('Envoi via')} <strong>{envoi.transport}</strong>, {t('de la part de')}{' '}
                        <strong>{envoi.expediteur ?? '—'}</strong>.
                    </p>
                </Card>

                {groupes.map((groupe) => (
                    <Card key={groupe.nom} className="overflow-hidden">
                        <p className="border-b border-ink-100 px-5 py-3.5 text-sm font-semibold text-ink-900 dark:border-white/10 dark:text-white">
                            {groupe.nom}
                        </p>

                        <div className="divide-y divide-ink-100 dark:divide-white/10">
                            {groupe.courriels.map((courriel) => (
                                <div
                                    key={courriel.cle}
                                    className="flex flex-wrap items-center gap-4 px-5 py-4 transition hover:bg-ink-50/60 dark:hover:bg-white/5"
                                >
                                    <div className="min-w-[200px] flex-1">
                                        <p className="text-sm font-medium text-ink-900 dark:text-white">
                                            {courriel.titre}
                                        </p>
                                        <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                                            {courriel.description}
                                        </p>
                                    </div>

                                    <div className="flex shrink-0 items-center gap-2">
                                        <a
                                            href={routes.admin.emailApercu(courriel.cle)}
                                            target="_blank"
                                            rel="noopener"
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-medium text-ink-600 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                                        >
                                            <Icon name="eye" className="h-3.5 w-3.5" />
                                            {t('Voir')}
                                        </a>

                                        <button
                                            type="button"
                                            onClick={() => envoyer(courriel)}
                                            disabled={!adresse || enCours !== null}
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50"
                                        >
                                            {enCours === courriel.cle ? (
                                                <Spinner />
                                            ) : (
                                                <Icon name="mail" className="h-3.5 w-3.5" />
                                            )}
                                            {t('Envoyer')}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                ))}
            </div>
        </AdminLayout>
    );
}
