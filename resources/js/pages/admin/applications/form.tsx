import { Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Label from '@/components/label';
import MediaField from '@/components/media-field';
import { Card, Input, Select, Textarea } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { cn, routes, useT } from '@/lib/utils';
import type { Application, Category } from '@/types';

const ICONS = [
    'grid', 'academic', 'school', 'briefcase', 'heart', 'users', 'document', 'mail',
    'globe', 'building', 'clipboard', 'layers', 'newspaper', 'link', 'shield', 'settings',
];

interface ApplicationFormData {
    _method: string | null;
    name: string;
    description: string;
    slug: string;
    category_id: string;
    url: string;
    type: string;
    module_key: string;
    client_id: string;
    client_secret: string;
    regenerer_secret: boolean;
    roles: string;
    is_active: boolean;
    opens_new_tab: boolean;
    cover: string;
    logo: string;
    cover_file: File | null;
    logo_file: File | null;
    remove_cover: boolean;
    remove_logo: boolean;
    icon: string;
    color: string;
    sort_order: string;
}

interface PortalModule {
    key: string;
    name: string;
    description: string | null;
    roles: string[];
}

export default function ApplicationForm({
    application,
    categories,
    modules,
}: {
    application: Application | null;
    categories: Category[];
    modules: PortalModule[];
}) {
    const t = useT();
    const editing = application !== null;

    const { data, setData, post, processing, errors } = useForm<ApplicationFormData>({
        _method: editing ? 'put' : null,
        name: application?.name ?? '',
        description: application?.description ?? '',
        slug: application?.slug ?? '',
        category_id: application?.categoryId ? String(application.categoryId) : '',
        url: application?.url ?? '',
        type: application?.type ?? 'application',
        module_key: application?.moduleKey ?? '',
        client_id: application?.clientId ?? '',
        client_secret: '',
        regenerer_secret: false,
        roles: (application?.roles ?? []).join(', '),
        is_active: application ? application.isActive : true,
        opens_new_tab: application?.opensNewTab ?? false,
        cover: application?.cover ?? '',
        logo: application?.logo ?? '',
        cover_file: null,
        logo_file: null,
        remove_cover: false,
        remove_logo: false,
        icon: application?.icon ?? 'grid',
        color: application?.color ?? '#1d4ed8',
        sort_order: String(application?.sortOrder ?? 0),
    });

    const [coverPreview, setCoverPreview] = useState<string | null>(application?.coverUrl ?? null);
    const [logoPreview, setLogoPreview] = useState<string | null>(application?.logoUrl ?? null);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(editing ? routes.admin.application(application.slug) : routes.admin.applications, { forceFormData: true });
    };

    const host = (() => {
        try {
            return new URL(String(data.url)).host;
        } catch {
            return '';
        }
    })();

    return (
        <AdminLayout
            title={editing ? t('Modifier une application') : t('Nouvelle application')}
            heading={editing ? application.name : t('Nouvelle application')}
            subheading={t('Déclarez le projet et son lien de redirection depuis le portail.')}
        >
            <form onSubmit={submit} className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div className="space-y-6">
                    <Card className="p-6">
                        <h2 className="text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">{t('Identité du projet')}</h2>

                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label htmlFor="name" required>
                                    {t('Nom affiché')}
                                </Label>
                                <Input
                                    id="name"
                                    className="mt-2"
                                    placeholder={t('Ex. Fondation Médicale')}
                                    value={String(data.name)}
                                    onChange={(e) => setData('name', e.target.value)}
                                    maxLength={120}
                                />
                                {errors.name && <p className="mt-1.5 text-xs text-red-600">{errors.name}</p>}
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="description">{t('Description')}</Label>
                                <Textarea
                                    id="description"
                                    rows={3}
                                    className="mt-2"
                                    placeholder={t("Ce que l'employé trouvera dans cette application.")}
                                    value={String(data.description ?? '')}
                                    onChange={(e) => setData('description', e.target.value)}
                                    maxLength={500}
                                />
                            </div>

                            <div>
                                <Label htmlFor="slug">{t('Identifiant technique (slug)')}</Label>
                                <Input
                                    id="slug"
                                    className="mt-2"
                                    placeholder={t('généré automatiquement')}
                                    value={String(data.slug ?? '')}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    maxLength={120}
                                />
                                <p className="mt-1.5 text-xs text-ink-400">{t("Utilisé dans l'URL du portail. Laissez vide pour le déduire du nom.")}</p>
                                {errors.slug && <p className="mt-1.5 text-xs text-red-600">{errors.slug}</p>}
                            </div>

                            <div>
                                <Label htmlFor="category_id">{t('Catégorie')}</Label>
                                <Select id="category_id" className="mt-2" value={String(data.category_id ?? '')} onChange={(e) => setData('category_id', e.target.value)}>
                                    <option value="">{t('Aucune')}</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        </div>
                    </Card>

                    <Card className="border-brand-200 p-6 dark:border-brand-500/25">
                        <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.09em] text-brand-700 dark:text-brand-300">
                            <Icon name="link" className="h-4 w-4" />
                            {data.type === 'module' ? t('Destination') : t('Lien de redirection')}
                        </h2>
                        <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">
                            {data.type === 'module'
                                ? t("Ce module fait partie du portail : l'ouverture reste à l'intérieur, aucune adresse extérieure n'est nécessaire.")
                                : t("C'est vers cette adresse que le portail envoie l'employé lorsqu'il ouvre l'application depuis son tableau de bord.")}
                        </p>

                        <div className="mt-5 space-y-5">
                            {data.type === 'module' ? (
                                <div>
                                    <Label htmlFor="module_key" required>
                                        {t('Module du portail')}
                                    </Label>
                                    <Select
                                        id="module_key"
                                        className="mt-2"
                                        value={String(data.module_key)}
                                        onChange={(e) => setData('module_key', e.target.value)}
                                    >
                                        <option value="">{t('Choisir un module…')}</option>
                                        {modules.map((module) => (
                                            <option key={module.key} value={module.key}>
                                                {module.name}
                                            </option>
                                        ))}
                                    </Select>
                                    {(() => {
                                        const chosen = modules.find((m) => m.key === data.module_key);
                                        return chosen ? (
                                            <p className="mt-1.5 text-xs text-ink-400">
                                                {chosen.description}
                                                {chosen.roles.length > 0 && (
                                                    <>
                                                        {' '}
                                                        {t('Rôles pouvant y publier :')}{' '}
                                                        <span className="font-mono text-ink-600 dark:text-ink-300">{chosen.roles.join(', ')}</span>
                                                    </>
                                                )}
                                            </p>
                                        ) : (
                                            <p className="mt-1.5 text-xs text-ink-400">
                                                {t("Ce module est servi par le portail : il n'a pas d'adresse extérieure.")}
                                            </p>
                                        );
                                    })()}
                                    {errors.module_key && <p className="mt-1.5 text-xs text-red-600">{errors.module_key}</p>}
                                </div>
                            ) : (
                                <div>
                                    <Label htmlFor="url" required>
                                        {t('URL de destination')}
                                    </Label>
                                    <Input
                                        id="url"
                                        type="url"
                                        className="mt-2 font-mono text-[13px]"
                                        placeholder="https://fondation.lamajestueuse.cm"
                                        value={String(data.url)}
                                        onChange={(e) => setData('url', e.target.value)}
                                        maxLength={255}
                                    />
                                    {host && (
                                        <p className="mt-1.5 text-xs text-ink-400">
                                            {t('Domaine visé :')} <span className="font-mono text-ink-600 dark:text-ink-300">{host}</span>
                                        </p>
                                    )}
                                    {errors.url && <p className="mt-1.5 text-xs text-red-600">{errors.url}</p>}
                                </div>
                            )}

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="type" required>
                                        {t('Type')}
                                    </Label>
                                    <Select id="type" className="mt-2" value={String(data.type)} onChange={(e) => setData('type', e.target.value)}>
                                        <option value="application">{t('Application métier')}</option>
                                        <option value="quick_link">{t('Lien rapide (service externe)')}</option>
                                        <option value="module">{t('Module du portail')}</option>
                                    </Select>
                                    <p className="mt-1.5 text-xs text-ink-400">
                                        {t('Les applications métier apparaissent dans « Mes applications », les liens rapides dans « Liens rapides ».')}
                                    </p>
                                </div>

                                <div>
                                    <Label htmlFor="client_id">{t('Identifiant client SSO')}</Label>
                                    <Input
                                        id="client_id"
                                        className="mt-2 font-mono text-[13px]"
                                        placeholder="fondation-medicale"
                                        value={String(data.client_id ?? '')}
                                        onChange={(e) => setData('client_id', e.target.value)}
                                        maxLength={120}
                                    />
                                    <p className="mt-1.5 text-xs text-ink-400">
                                        {t("À renseigner avec le secret ci-dessous pour que l'employé entre sans mot de passe.")}
                                    </p>
                                    {errors.client_id && <p className="mt-1.5 text-xs text-red-600">{errors.client_id}</p>}
                                </div>

                                <div className="sm:col-span-2">
                                    <Label htmlFor="client_secret">{t('Secret partagé')}</Label>
                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        <Input
                                            id="client_secret"
                                            type="password"
                                            autoComplete="off"
                                            className="min-w-[16rem] flex-1 font-mono text-[13px]"
                                            placeholder={
                                                application?.hasClientSecret
                                                    ? t('Un secret est enregistré — laissez vide pour le conserver')
                                                    : t('32 caractères minimum')
                                            }
                                            value={String(data.client_secret ?? '')}
                                            onChange={(e) => setData('client_secret', e.target.value)}
                                            disabled={Boolean(data.regenerer_secret)}
                                            maxLength={255}
                                        />
                                        <label className="flex cursor-pointer items-center gap-2 text-[13px] text-ink-600 dark:text-ink-300">
                                            <input
                                                type="checkbox"
                                                checked={Boolean(data.regenerer_secret)}
                                                onChange={(e) => setData('regenerer_secret', e.target.checked)}
                                                className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                            />
                                            {t('En générer un nouveau')}
                                        </label>
                                    </div>
                                    <p className="mt-1.5 text-xs text-ink-400">
                                        {t("Le même secret doit figurer dans le fichier .env de l'application, sous PORTAIL_CLIENT_SECRET. Il n'est jamais réaffiché ici.")}
                                    </p>
                                    {errors.client_secret && <p className="mt-1.5 text-xs text-red-600">{errors.client_secret}</p>}
                                </div>

                                <div className="sm:col-span-2">
                                    <Label htmlFor="roles">{t('Rôles acceptés par l’application')}</Label>
                                    <Input
                                        id="roles"
                                        className="mt-2 font-mono text-[13px]"
                                        placeholder="admin, enseignant, personnel, etudiant"
                                        value={String(data.roles ?? '')}
                                        onChange={(e) => setData('roles', e.target.value)}
                                        maxLength={600}
                                    />
                                    <p className="mt-1.5 text-xs text-ink-400">
                                        {application?.rolesSyncedAt
                                            ? t('Rôles envoyés par l’application le :date. Utilisez « Synchroniser » sur la page Accès pour les mettre à jour.', { date: application.rolesSyncedAt })
                                            : t("Séparés par des virgules. Chaque employé peut en recevoir plusieurs à l'ouverture ; laissez vide pour autoriser la saisie libre. Une application raccordée peut aussi envoyer elle-même ses rôles.")}
                                    </p>
                                    {(data.roles as string)?.trim() && (
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {String(data.roles)
                                                .split(',')
                                                .map((r) => r.trim())
                                                .filter(Boolean)
                                                .map((r) => {
                                                    const known = application?.roleCatalogue.find((role) => role.code === r);

                                                    return (
                                                        <span key={r} title={known?.description ?? undefined} className="badge bg-ink-100 text-ink-600 dark:bg-white/8 dark:text-ink-300">
                                                            {known && known.libelle !== r ? (
                                                                <>
                                                                    {known.libelle} <span className="ml-1 font-mono text-ink-400">{r}</span>
                                                                </>
                                                            ) : (
                                                                <span className="font-mono">{r}</span>
                                                            )}
                                                        </span>
                                                    );
                                                })}
                                        </div>
                                    )}
                                    {errors.roles && <p className="mt-1.5 text-xs text-red-600">{errors.roles}</p>}
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-ink-200 p-3.5 transition hover:border-ink-300 dark:border-white/10">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(data.is_active)}
                                        onChange={(e) => setData('is_active', e.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                    />
                                    <span>
                                        <span className="block text-sm font-medium text-ink-800 dark:text-ink-100">{t('Application active')}</span>
                                        <span className="block text-xs text-ink-400">{t('Décochez pour la retirer des tableaux de bord.')}</span>
                                    </span>
                                </label>

                                <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-ink-200 p-3.5 transition hover:border-ink-300 dark:border-white/10">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(data.opens_new_tab)}
                                        onChange={(e) => setData('opens_new_tab', e.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                    />
                                    <span>
                                        <span className="block text-sm font-medium text-ink-800 dark:text-ink-100">{t('Ouvrir dans un nouvel onglet')}</span>
                                        <span className="block text-xs text-ink-400">{t('Recommandé pour les services externes.')}</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <h2 className="text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">{t('Apparence de la tuile')}</h2>

                        <div className="mt-5 space-y-5">
                            <div className="grid gap-5 sm:grid-cols-2">
                                <MediaField
                                    label={t('Image de couverture')}
                                    hint={t('JPG, PNG ou WebP — 4 Mo maximum.')}
                                    emptyLabel={t('Aucune image')}
                                    preview={coverPreview}
                                    externalValue={String(data.cover ?? '')}
                                    externalPlaceholder={t('ou une URL externe')}
                                    onExternalChange={(value) => setData('cover', value)}
                                    onPick={(file) => {
                                        setData((current) => ({ ...current, cover_file: file, remove_cover: false }));
                                        setCoverPreview(URL.createObjectURL(file));
                                    }}
                                    onDrop={() => {
                                        setData((current) => ({ ...current, cover_file: null, remove_cover: true, cover: '' }));
                                        setCoverPreview(null);
                                    }}
                                />

                                <MediaField
                                    label={t('Logo du projet')}
                                    hint={t('Carré de préférence — 1 Mo maximum.')}
                                    emptyLabel={t("L'icône ci-dessous sera utilisée")}
                                    preview={logoPreview}
                                    round
                                    externalValue={String(data.logo ?? '')}
                                    externalPlaceholder={t('ou une URL externe')}
                                    onExternalChange={(value) => setData('logo', value)}
                                    onPick={(file) => {
                                        setData((current) => ({ ...current, logo_file: file, remove_logo: false }));
                                        setLogoPreview(URL.createObjectURL(file));
                                    }}
                                    onDrop={() => {
                                        setData((current) => ({ ...current, logo_file: null, remove_logo: true, logo: '' }));
                                        setLogoPreview(null);
                                    }}
                                />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-[140px_120px]">
                                <div>
                                    <Label>{t('Couleur')}</Label>
                                    <div className="mt-2 flex items-center gap-2">
                                        <input
                                            type="color"
                                            value={String(data.color)}
                                            onChange={(e) => setData('color', e.target.value)}
                                            className="h-11 w-12 shrink-0 cursor-pointer rounded-lg border border-ink-200 bg-white p-1 dark:border-white/10 dark:bg-white/5"
                                        />
                                        <Input className="font-mono text-[13px]" value={String(data.color)} onChange={(e) => setData('color', e.target.value)} maxLength={20} />
                                    </div>
                                </div>
                                <div>
                                    <Label htmlFor="sort_order">{t('Ordre')}</Label>
                                    <Input
                                        id="sort_order"
                                        type="number"
                                        min={0}
                                        max={999}
                                        className="mt-2"
                                        value={String(data.sort_order)}
                                        onChange={(e) => setData('sort_order', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div>
                                <Label>{t('Icône')}</Label>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {ICONS.map((icon) => (
                                        <button
                                            key={icon}
                                            type="button"
                                            title={icon}
                                            onClick={() => setData('icon', icon)}
                                            className={cn(
                                                'rounded-xl border p-2.5 transition',
                                                data.icon === icon
                                                    ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'
                                                    : 'border-ink-200 text-ink-500 hover:border-ink-400 dark:border-white/10 dark:text-ink-400',
                                            )}
                                        >
                                            <Icon name={icon} className="h-5 w-5" />
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                <div className="space-y-4 xl:sticky xl:top-24 xl:self-start">
                    <Card className="p-5">
                        <p className="text-xs font-semibold uppercase tracking-[0.09em] text-ink-400">{t('Aperçu de la tuile')}</p>

                        <div className="mt-4 overflow-hidden rounded-2xl border border-ink-200 dark:border-white/10">
                            <div className="relative h-20" style={{ background: `linear-gradient(135deg, ${data.color}, ${data.color}22)` }}>
                                {coverPreview && <img src={coverPreview} alt="" className="h-full w-full object-cover" />}
                            </div>
                            <div className="relative -mt-6 px-4 pb-4">
                                <div className="flex items-start gap-3">
                                    <span
                                        className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-md ring-1 ring-ink-900/5 dark:bg-ink-800 dark:ring-white/10"
                                        style={{ color: String(data.color) }}
                                    >
                                        {logoPreview ? <img src={logoPreview} alt="" className="h-full w-full object-contain p-1" /> : <Icon name={String(data.icon)} className="h-5 w-5" />}
                                    </span>
                                    <div className="min-w-0 pt-6">
                                        <p className="truncate text-[15px] font-semibold text-ink-900 dark:text-white">{String(data.name) || t('Nom du projet')}</p>
                                    </div>
                                </div>
                                <p className="mt-3 line-clamp-2 min-h-[2.5rem] text-[13px] text-ink-500 dark:text-ink-400">
                                    {String(data.description ?? '') || t('Description de l’application…')}
                                </p>
                                <div className="mt-3 flex items-center justify-between border-t border-ink-100 pt-3 text-[13px] dark:border-white/10">
                                    <span className="shrink-0 whitespace-nowrap text-ink-600 dark:text-ink-300">
                                        {data.type === 'quick_link' ? t('Ouvrir') : t("Ouvrir l'application")}
                                    </span>
                                    <span className="truncate pl-2 font-mono text-[11px] text-ink-400">
                                        {data.type === 'module' ? t('Module du portail') : host}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card className="space-y-2.5 p-5">
                        <button type="submit" disabled={processing} className="btn-primary w-full">
                            <Icon name="check" className="h-4 w-4" />
                            {editing ? t('Enregistrer les modifications') : t('Créer l’application')}
                        </button>
                        <Link href={routes.admin.applications} className="btn-ghost w-full">
                            {t('Annuler')}
                        </Link>
                        {editing && (
                            <Link href={routes.admin.applicationAccess(application.slug)} className="btn-ghost w-full">
                                <Icon name="users" className="h-4 w-4" />
                                {t('Gérer les accès')}
                            </Link>
                        )}
                    </Card>
                </div>
            </form>
        </AdminLayout>
    );
}
