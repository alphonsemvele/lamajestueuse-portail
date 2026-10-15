import { Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Spinner from '@/components/spinner';
import Label from '@/components/label';
import { Card, Input, Select } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { routes, useT } from '@/lib/utils';
import type { PostType, Rubrique } from '@/types';

interface Props {
    rubriques: Rubrique[];
    familles: Record<PostType, string>;
}

/**
 * Les rubriques classent le site public. Chacune porte une famille, qui dit
 * où l'article se range sur le tableau de bord du portail.
 */
export default function Rubriques({ rubriques, familles }: Props) {
    const t = useT();
    const [edite, setEdite] = useState<Rubrique | null>(null);

    const vide = { nom: '', description: '', couleur: '#7c3aed', famille: 'news' as PostType, ordre: 0, actif: true };
    const { data, setData, post, put, processing, errors, reset } = useForm(vide);

    const ouvrir = (rubrique: Rubrique | null) => {
        setEdite(rubrique);

        if (rubrique) {
            setData({
                nom: rubrique.nom,
                description: rubrique.description ?? '',
                couleur: rubrique.couleur,
                famille: rubrique.famille,
                ordre: rubrique.ordre,
                actif: rubrique.actif,
            });
        } else {
            reset();
        }
    };

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();

        if (edite) {
            put(routes.informations.rubriqueUpdate(edite.id), {
                preserveScroll: true,
                onSuccess: () => ouvrir(null),
            });
        } else {
            post(routes.informations.rubriques, { preserveScroll: true, onSuccess: () => reset() });
        }
    };

    return (
        <PortalLayout title={t('Rubriques')}>
            <nav className="mb-6 flex items-center gap-1.5 text-sm text-ink-400">
                <Link href={routes.dashboard} className="font-medium transition hover:text-brand-600">
                    {t('Portail')}
                </Link>
                <Icon name="chevron-right" className="h-3.5 w-3.5" />
                <Link href={routes.informations.index} className="font-medium transition hover:text-brand-600">
                    LA MAJESTUEUSE Info
                </Link>
            </nav>

            <h1 className="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">{t('Rubriques')}</h1>
            <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">
                {t('Le classement du site public. Une rubrique vide peut être supprimée.')}
            </p>

            <div className="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <Card className="divide-y divide-ink-100 dark:divide-white/10">
                    {rubriques.length === 0 && (
                        <p className="px-5 py-10 text-center text-sm text-ink-400">{t('Aucune rubrique.')}</p>
                    )}

                    {rubriques.map((rubrique) => (
                        <div key={rubrique.id} className="flex items-center gap-3 px-5 py-4">
                            <span
                                className="h-8 w-8 shrink-0 rounded-lg"
                                style={{ backgroundColor: rubrique.couleur }}
                                aria-hidden
                            />

                            <span className="min-w-0 flex-1">
                                <span className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-semibold text-ink-900 dark:text-white">{rubrique.nom}</span>
                                    <span className="badge bg-ink-100 text-ink-500 dark:bg-white/10 dark:text-ink-300">
                                        {rubrique.familleLibelle}
                                    </span>
                                    {!rubrique.actif && (
                                        <span className="badge bg-amber-50 text-amber-700 dark:bg-amber-500/12 dark:text-amber-300">
                                            {t('Masquée')}
                                        </span>
                                    )}
                                </span>
                                <span className="mt-0.5 block truncate text-xs text-ink-400">
                                    {rubrique.description ?? t('Sans description')}
                                </span>
                            </span>

                            <span className="shrink-0 text-xs tabular-nums text-ink-400">
                                {rubrique.articles ?? 0} {(rubrique.articles ?? 0) > 1 ? t('articles') : t('article')}
                            </span>

                            <button
                                type="button"
                                title={t('Modifier')}
                                onClick={() => ouvrir(rubrique)}
                                className="rounded-lg p-1.5 text-ink-400 transition hover:bg-ink-100 hover:text-ink-800 dark:hover:bg-white/10"
                            >
                                <Icon name="pencil" className="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                title={t('Supprimer')}
                                onClick={() => {
                                    if (confirm(t('Supprimer cette rubrique ?'))) {
                                        router.delete(routes.informations.rubriqueUpdate(rubrique.id), { preserveScroll: true });
                                    }
                                }}
                                className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                            >
                                <Icon name="trash" className="h-4 w-4" />
                            </button>
                        </div>
                    ))}
                </Card>

                <Card className="space-y-4 p-5 lg:sticky lg:top-24 lg:self-start">
                    <h2 className="text-sm font-semibold text-ink-800 dark:text-ink-100">
                        {edite ? t('Modifier la rubrique') : t('Nouvelle rubrique')}
                    </h2>

                    <form onSubmit={enregistrer} className="space-y-4">
                        <div>
                            <Label htmlFor="nom" required>
                                {t('Nom')}
                            </Label>
                            <Input
                                id="nom"
                                className="mt-2"
                                value={data.nom}
                                onChange={(e) => setData('nom', e.target.value)}
                                maxLength={80}
                                required
                            />
                            {errors.nom && <p className="mt-1.5 text-xs text-red-600">{errors.nom}</p>}
                        </div>

                        <div>
                            <Label htmlFor="description">{t('Description')}</Label>
                            <Input
                                id="description"
                                className="mt-2"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                maxLength={220}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label htmlFor="couleur">{t('Couleur')}</Label>
                                <input
                                    id="couleur"
                                    type="color"
                                    value={data.couleur}
                                    onChange={(e) => setData('couleur', e.target.value)}
                                    className="mt-2 h-10 w-full cursor-pointer rounded-lg border border-ink-200 bg-white p-1 dark:border-white/10 dark:bg-white/5"
                                />
                            </div>
                            <div>
                                <Label htmlFor="ordre">{t('Ordre')}</Label>
                                <Input
                                    id="ordre"
                                    type="number"
                                    min={0}
                                    className="mt-2"
                                    value={data.ordre}
                                    onChange={(e) => setData('ordre', Number(e.target.value))}
                                />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="famille" required>
                                {t('Famille')}
                            </Label>
                            <Select
                                id="famille"
                                className="mt-2"
                                value={data.famille}
                                onChange={(e) => setData('famille', e.target.value as PostType)}
                            >
                                {Object.entries(familles).map(([valeur, intitule]) => (
                                    <option key={valeur} value={valeur}>
                                        {intitule}
                                    </option>
                                ))}
                            </Select>
                            <p className="mt-1.5 text-xs text-ink-400">
                                {t('Où les articles de cette rubrique se rangent sur le tableau de bord du portail.')}
                            </p>
                        </div>

                        <label className="flex items-center gap-2.5 text-sm text-ink-700 dark:text-ink-200">
                            <input
                                type="checkbox"
                                checked={data.actif}
                                onChange={(e) => setData('actif', e.target.checked)}
                                className="h-4 w-4 rounded border border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                            />
                            {t('Afficher sur le site public')}
                        </label>

                        <div className="flex gap-2">
                            <button type="submit" disabled={processing} className="btn-primary flex-1">
                                {processing ? <Spinner /> : <Icon name="check" className="h-4 w-4" />}
                                {edite ? t('Enregistrer') : t('Créer')}
                            </button>
                            {edite && (
                                <button type="button" onClick={() => ouvrir(null)} className="btn-ghost">
                                    {t('Annuler')}
                                </button>
                            )}
                        </div>
                    </form>
                </Card>
            </div>
        </PortalLayout>
    );
}
