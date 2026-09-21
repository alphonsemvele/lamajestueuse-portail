import { Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import Spinner from '@/components/spinner';
import MultiSelect from '@/components/multi-select';
import { Alert, Card, Input } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { routes, useChoice, useT } from '@/lib/utils';
import type { Application, PortalUser } from '@/types';

interface Props {
    application: Application;
    users: PortalUser[];
    granted: Record<number, string[]>;
    references: Record<number, string | null>;
    /** L'application reçoit-elle une identité signée du portail ? */
    raccordee: boolean;
}

export default function ApplicationAccess({ application, users, granted, references, raccordee }: Props) {
    const t = useT();
    const choice = useChoice();
    const pageErrors = usePage().props.errors as Record<string, string>;
    const [filter, setFilter] = useState('');
    const [onlyGranted, setOnlyGranted] = useState(false);
    const [syncing, setSyncing] = useState(false);

    const { data, setData, put, processing, errors } = useForm<{
        users: number[];
        roles: Record<number, string[]>;
        references: Record<number, string>;
    }>({
        users: Object.keys(granted).map(Number),
        roles: Object.fromEntries(Object.entries(granted).map(([id, roles]) => [Number(id), roles ?? []])),
        references: Object.fromEntries(Object.entries(references).map(([id, ref]) => [Number(id), ref ?? ''])),
    });

    const options = application.roleCatalogue.map((role) => ({ value: role.code, label: role.libelle, description: role.description }));

    const toggle = (id: number) =>
        setData('users', data.users.includes(id) ? data.users.filter((value) => value !== id) : [...data.users, id]);

    // Attribuer un rôle à un employé lui donne aussi l'accès à l'application.
    const setRoles = (id: number, roles: string[]) =>
        setData((current) => ({
            ...current,
            roles: { ...current.roles, [id]: roles },
            users: roles.length > 0 && !current.users.includes(id) ? [...current.users, id] : current.users,
        }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(routes.admin.applicationAccess(application.slug), { preserveScroll: true });
    };

    const synchronize = () =>
        router.post(routes.admin.applicationSync(application.slug), {}, {
            preserveScroll: true,
            onStart: () => setSyncing(true),
            onFinish: () => setSyncing(false),
        });

    const visible = users.filter(
        (user) =>
            (!onlyGranted || data.users.includes(user.id)) &&
            `${user.fullName} ${user.email ?? ''} ${user.matricule ?? ''} ${user.entite ?? ''} ${user.poste ?? ''}`.toLowerCase().includes(filter.toLowerCase()),
    );

    const errorFor = (id: number) =>
        Object.entries(errors as Record<string, string>).find(([key]) => key === `roles.${id}` || key.startsWith(`roles.${id}.`))?.[1];

    return (
        <AdminLayout
            title={t('Accès')}
            heading={t('Accès à :app', { app: application.name })}
            subheading={t('Cochez les employés autorisés et attribuez-leur un ou plusieurs rôles dans l’application.')}
        >
            <form onSubmit={submit} className="space-y-5">
                {raccordee && (
                    <Card className="flex flex-wrap items-center gap-4 p-5">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/12 dark:text-brand-300">
                            <Icon name="layers" className="h-5 w-5" />
                        </span>
                        <div className="min-w-[220px] flex-1">
                            <p className="text-sm font-semibold text-ink-900 dark:text-white">
                                {choice(':count rôle déclaré par l’application|:count rôles déclarés par l’application', application.roleCatalogue.length)}
                            </p>
                            <p className="text-xs text-ink-500 dark:text-ink-400">
                                {application.rolesSyncedAt
                                    ? t('Dernière synchronisation le :date. Elle met à jour les rôles et ajoute le personnel de l’application.', { date: application.rolesSyncedAt })
                                    : t('Pas encore synchronisée : récupérez les rôles et le personnel définis par l’application.')}
                            </p>
                        </div>
                        <button type="button" onClick={synchronize} disabled={syncing} className="btn-ghost border border-ink-200 dark:border-white/10">
                            {syncing ? <Spinner /> : <Icon name="upload" className="h-4 w-4" />}
                            {syncing ? t('Synchronisation en cours…') : t('Synchroniser les rôles et le personnel')}
                        </button>
                        {application.roleCatalogue.length > 0 && (
                            <div className="flex w-full flex-wrap gap-1.5">
                                {application.roleCatalogue.map((role) => (
                                    <span
                                        key={role.code}
                                        title={role.description ?? undefined}
                                        className="badge bg-ink-100 text-ink-600 dark:bg-white/8 dark:text-ink-300"
                                    >
                                        {role.libelle}
                                    </span>
                                ))}
                            </div>
                        )}
                    </Card>
                )}

                {pageErrors.synchronisation && (
                    <Alert tone="danger" icon="alert">
                        {pageErrors.synchronisation}
                    </Alert>
                )}

                <Card className="p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="relative min-w-[240px] flex-1">
                            <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                            <input
                                type="search"
                                value={filter}
                                onChange={(event) => setFilter(event.target.value)}
                                placeholder={t('Filtrer les employés…')}
                                className="field-input pl-10"
                            />
                        </div>
                        <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                            <input
                                type="checkbox"
                                checked={onlyGranted}
                                onChange={(event) => setOnlyGranted(event.target.checked)}
                                className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                            />
                            {t('Seulement le personnel ayant accès')}
                        </label>
                        <p className="text-sm text-ink-500 dark:text-ink-400">
                            {choice(':count accès actuellement accordé|:count accès actuellement accordés', data.users.length)}
                        </p>
                    </div>
                </Card>

                {raccordee && (
                    <Alert tone="info" icon="key">
                        {t("Cette application reçoit une identité signée du portail : l'employé y entre sans mot de passe, avec les rôles attribués ici. Renseignez son matricule local s'il y possède déjà un compte, sinon un nouveau sera créé à sa première entrée.")}
                    </Alert>
                )}

                <Card className="divide-y divide-ink-100 dark:divide-white/10">
                    {visible.map((user) => (
                        <div key={user.id} className="flex flex-wrap items-center gap-4 px-5 py-3.5 transition hover:bg-ink-50/60 dark:hover:bg-white/5">
                            <label className="flex min-w-[240px] flex-1 cursor-pointer items-center gap-4">
                                <input
                                    type="checkbox"
                                    checked={data.users.includes(user.id)}
                                    onChange={() => toggle(user.id)}
                                    className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                />

                                <Avatar url={user.avatarUrl} initials={user.initials} className="h-9 w-9 text-[11px]" />

                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium text-ink-900 dark:text-white">{user.fullName}</span>
                                    <span className="block text-xs text-ink-400">
                                        {user.email ?? user.matricule ?? '—'}
                                        {user.entite && ` · ${user.entite}`}
                                        {user.poste && ` · ${user.poste}`}
                                    </span>
                                </span>
                            </label>

                            <div className="w-full sm:w-72">
                                {options.length > 0 ? (
                                    <MultiSelect
                                        options={options}
                                        value={data.roles[user.id] ?? []}
                                        onChange={(roles) => setRoles(user.id, roles)}
                                        placeholder={t('aucun rôle')}
                                    />
                                ) : (
                                    <Input
                                        className="py-2 text-[13px]"
                                        placeholder={t('rôles, séparés par des virgules')}
                                        value={(data.roles[user.id] ?? []).join(', ')}
                                        onChange={(event) =>
                                            setRoles(
                                                user.id,
                                                event.target.value.split(',').map((role) => role.trimStart()).filter((role, index, all) => role !== '' || index === all.length - 1),
                                            )
                                        }
                                        maxLength={300}
                                    />
                                )}
                                {errorFor(user.id) && <p className="mt-1 text-xs text-red-600">{errorFor(user.id)}</p>}
                            </div>

                            {raccordee && (
                                <Input
                                    className="w-44 py-2 font-mono text-[12px]"
                                    placeholder={t('matricule local')}
                                    title={t("Identifiant de cet employé DANS l'application, s'il y a déjà un compte.")}
                                    value={data.references[user.id] ?? ''}
                                    onChange={(event) => setData('references', { ...data.references, [user.id]: event.target.value })}
                                    maxLength={120}
                                />
                            )}
                        </div>
                    ))}
                    {visible.length === 0 && <p className="px-5 py-8 text-center text-sm text-ink-400">{t('Aucun employé ne correspond.')}</p>}
                </Card>

                <div className="flex flex-wrap items-center gap-3">
                    <button type="submit" disabled={processing} className="btn-primary">
                        {processing ? <Spinner /> : <Icon name="check" className="h-4 w-4" />}
                        {processing ? t('Enregistrement…') : t('Enregistrer les accès')}
                    </button>
                    <Link href={routes.admin.applications} className="btn-ghost">
                        {t('Retour')}
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
