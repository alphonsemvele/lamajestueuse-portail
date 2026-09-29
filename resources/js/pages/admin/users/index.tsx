import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { cn, routes, useChoice, useT } from '@/lib/utils';
import type { Paginated, PortalUser } from '@/types';

interface Props {
    users: Paginated<PortalUser>;
    pendingCount: number;
    institutions: { slug: string; name: string }[];
    filters: {
        q: string | null;
        role: string | null;
        status: string | null;
        application: string | null;
        ordre: string;
        sansMatricule: boolean;
    };
    sansMatriculeCount: number;
    prochainMatricule: string;
}

export default function UsersIndex({
    users,
    pendingCount,
    institutions,
    filters,
    sansMatriculeCount,
    prochainMatricule,
}: Props) {
    const t = useT();
    const choice = useChoice();
    const [q, setQ] = useState(filters.q ?? '');

    // Attribution des matricules : la sélection porte sur la page affichée.
    const [coches, setCoches] = useState<number[]>([]);
    const [remplacer, setRemplacer] = useState(false);

    const idsPage = users.data.map((user) => user.id);
    const toutCoche = idsPage.length > 0 && idsPage.every((id) => coches.includes(id));

    // Ceux qui portent déjà un numéro : les renuméroter est une décision.
    const dejaMatricules = users.data.filter((user) => coches.includes(user.id) && user.matricule);

    const attribuer = () => {
        if (coches.length === 0) return;

        if (
            remplacer &&
            dejaMatricules.length > 0 &&
            !confirm(
                `${dejaMatricules.length} compte(s) portent déjà un matricule et vont en recevoir un nouveau.\n\n` +
                    'Leurs anciens numéros seront perdus et ne seront réattribués à personne. ' +
                    'Les badges, fichiers et documents déjà émis porteront l’ancien numéro.\n\nContinuer ?',
            )
        ) {
            return;
        }

        router.post(
            routes.admin.userMatricules,
            { users: coches, remplacer },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCoches([]);
                    setRemplacer(false);
                },
            },
        );
    };

    const roles: Record<string, string> = { admin: t('Administrateur'), manager: t('Responsable'), employee: t('Employé') };
    const statuses: Record<string, string> = { active: t('Actif'), suspended: t('Suspendu'), pending: t('En attente') };
    const statusTones: Record<string, string> = {
        active: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300',
        suspended: 'bg-red-50 text-red-700 dark:bg-red-500/12 dark:text-red-300',
        pending: 'bg-amber-50 text-amber-700 dark:bg-amber-500/12 dark:text-amber-300',
    };

    const go = (params: Record<string, string>) =>
        router.get(
            routes.admin.users,
            {
                q,
                role: filters.role ?? '',
                status: filters.status ?? '',
                application: filters.application ?? '',
                ordre: filters.ordre ?? 'desc',
                ...params,
            },
            { preserveState: true, replace: true },
        );

    const search = (event: FormEvent) => {
        event.preventDefault();
        go({});
    };

    return (
        <AdminLayout title={t('Utilisateurs')} subheading={t('Le personnel de tous les instituts, ses accès et ses rôles dans chaque application.')}>
            {pendingCount > 0 && (
                <Link
                    href={`${routes.admin.users}?status=pending`}
                    className="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 transition hover:border-amber-400 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <Icon name="alert" className="h-5 w-5 shrink-0" />
                    <span className="flex-1">
                        {choice(
                            ":count demande d'inscription attend votre validation.|:count demandes d'inscription attendent votre validation.",
                            pendingCount,
                        )}
                    </span>
                    <Icon name="chevron-right" className="h-4 w-4 shrink-0" />
                </Link>
            )}

            <div className="flex flex-wrap items-center gap-3">
                <form onSubmit={search} className="flex flex-1 flex-wrap items-center gap-2">
                    <div className="relative min-w-[220px] flex-1">
                        <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                        <input value={q} onChange={(e) => setQ(e.target.value)} type="search" placeholder={t('Nom, e-mail, matricule…')} className="field-input pl-10" />
                    </div>
                    {institutions.length > 0 && (
                        <select value={filters.application ?? ''} onChange={(e) => go({ application: e.target.value })} className="field-input w-auto">
                            <option value="">{t('Tous les instituts')}</option>
                            {institutions.map((institution) => (
                                <option key={institution.slug} value={institution.slug}>
                                    {institution.name}
                                </option>
                            ))}
                        </select>
                    )}
                    <select value={filters.role ?? ''} onChange={(e) => go({ role: e.target.value })} className="field-input w-auto">
                        <option value="">{t('Tous les rôles')}</option>
                        {Object.entries(roles).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <select value={filters.status ?? ''} onChange={(e) => go({ status: e.target.value })} className="field-input w-auto">
                        <option value="">{t('Tous les statuts')}</option>
                        {Object.entries(statuses).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </form>

                <button
                    type="button"
                    onClick={() => go({ sans_matricule: filters.sansMatricule ? '' : '1' })}
                    className={cn(
                        'inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-medium transition',
                        filters.sansMatricule
                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-400/40 dark:bg-brand-500/10 dark:text-brand-300'
                            : 'border-ink-200 text-ink-600 hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5',
                    )}
                >
                    <Icon name="key" className="h-4 w-4" />
                    {t('Sans matricule')} ({sansMatriculeCount})
                </button>

                <Link href={routes.admin.userCreate} className="btn-primary">
                    <Icon name="plus" className="h-4 w-4" />
                    {t('Nouvel employé')}
                </Link>
            </div>

            {coches.length > 0 && (
                <div className="sticky top-4 z-30 mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-brand-300 bg-white p-3 shadow-lg dark:border-brand-400/40 dark:bg-ink-900">
                    <span className="text-sm font-medium text-ink-900 dark:text-white">
                        {t(':n compte(s) sélectionné(s)', { n: coches.length })}
                    </span>
                    <span className="text-xs text-ink-500 dark:text-ink-400">
                        {t('La numérotation reprend à :matricule.', { matricule: prochainMatricule })}
                    </span>

                    {dejaMatricules.length > 0 && (
                        <label className="flex cursor-pointer items-center gap-2 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                            <input
                                type="checkbox"
                                checked={remplacer}
                                onChange={(event) => setRemplacer(event.target.checked)}
                                className="h-4 w-4 rounded border-amber-400 text-amber-600 focus:ring-amber-500/30"
                            />
                            {t('Remplacer les :n matricule(s) existant(s)', { n: dejaMatricules.length })}
                        </label>
                    )}

                    <div className="ml-auto flex gap-2">
                        <button type="button" onClick={() => setCoches([])} className="btn-ghost">
                            {t('Tout décocher')}
                        </button>
                        <button type="button" onClick={attribuer} className="btn-primary">
                            <Icon name="key" className="h-4 w-4" />
                            {t('Attribuer les matricules')}
                        </button>
                    </div>
                </div>
            )}

            <Card className="overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1000px] text-left text-sm">
                        <thead className="border-b border-ink-100 bg-ink-50/70 text-[11px] uppercase tracking-[0.07em] text-ink-500 dark:border-white/10 dark:bg-white/5">
                            <tr>
                                <th className="w-10 px-5 py-3">
                                    <input
                                        type="checkbox"
                                        checked={toutCoche}
                                        disabled={idsPage.length === 0}
                                        onChange={() => setCoches(toutCoche ? [] : idsPage)}
                                        className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 disabled:opacity-40 dark:border-white/20 dark:bg-white/5"
                                        aria-label={t('Sélectionner tous les comptes de cette page')}
                                    />
                                </th>
                                <th className="px-5 py-3 font-semibold">{t('Employé')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Entité')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Portail')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Applications & rôles')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Statut')}</th>
                                <th className="px-5 py-3 font-semibold">
                                    <button
                                        type="button"
                                        onClick={() => go({ ordre: filters.ordre === 'asc' ? 'desc' : 'asc' })}
                                        className="inline-flex items-center gap-1 uppercase tracking-[0.07em] transition hover:text-ink-800 dark:hover:text-white"
                                        title={t('Trier par date d’inscription')}
                                    >
                                        {t('Inscrit le')}
                                        <Icon
                                            name={filters.ordre === 'asc' ? 'chevron-up' : 'chevron-down'}
                                            className="h-3 w-3"
                                        />
                                    </button>
                                </th>
                                <th className="px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-ink-100 dark:divide-white/10">
                            {users.data.map((user) => (
                                <tr key={user.id} className="transition hover:bg-ink-50/60 dark:hover:bg-white/5">
                                    <td className="px-5 py-3.5">
                                        <input
                                            type="checkbox"
                                            checked={coches.includes(user.id)}
                                            onChange={() =>
                                                setCoches((actuels) =>
                                                    actuels.includes(user.id)
                                                        ? actuels.filter((id) => id !== user.id)
                                                        : [...actuels, user.id],
                                                )
                                            }
                                            className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                            aria-label={t('Sélectionner :nom', { nom: user.fullName })}
                                        />
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <div className="flex items-center gap-3">
                                            <Avatar url={user.avatarUrl} initials={user.initials} className="h-9 w-9 text-[11px]" />
                                            <div className="min-w-0">
                                                <p className="font-medium text-ink-900 dark:text-white">
                                                    {user.fullName}
                                                    {user.selfRegistered && (
                                                        <span className="badge ml-1 bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300">{t('auto-inscrit')}</span>
                                                    )}
                                                </p>
                                                <p className="text-xs text-ink-400">
                                                    {user.matricule ? (
                                                        <span className="font-mono text-ink-500 dark:text-ink-300">{user.matricule}</span>
                                                    ) : (
                                                        <span className="text-amber-600 dark:text-amber-400">{t('sans matricule')}</span>
                                                    )}
                                                    {user.email && <span className="ml-2">{user.email}</span>}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-5 py-3.5 text-ink-600 dark:text-ink-300">
                                        {user.entite || '—'}
                                        {user.poste && <span className="block text-[11px] text-ink-400">{user.poste}</span>}
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <span
                                            className={cn(
                                                'badge',
                                                user.role === 'admin'
                                                    ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300'
                                                    : 'bg-ink-100 text-ink-600 dark:bg-white/8 dark:text-ink-300',
                                            )}
                                        >
                                            {roles[user.role]}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3.5">
                                        {user.acces && user.acces.length > 0 ? (
                                            <div className="flex max-w-[360px] flex-wrap gap-1.5">
                                                {user.acces.map((access) => (
                                                    <span
                                                        key={access.slug}
                                                        className="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px]"
                                                        style={{ borderColor: `${access.color}33`, background: `${access.color}0f` }}
                                                    >
                                                        <span className="font-semibold" style={{ color: access.color }}>
                                                            {access.name}
                                                        </span>
                                                        {access.roles.length > 0 && <span className="text-ink-600 dark:text-ink-300">{access.roles.join(', ')}</span>}
                                                    </span>
                                                ))}
                                            </div>
                                        ) : (
                                            <span className="text-xs text-ink-400">—</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <span className={cn('badge', statusTones[user.status])}>{statuses[user.status]}</span>
                                    </td>
                                    <td className="whitespace-nowrap px-5 py-3.5 text-[13px] tabular-nums text-ink-600 dark:text-ink-300">
                                        {user.inscritLe ?? '—'}
                                        {user.selfRegistered && (
                                            <span className="block text-[11px] text-ink-400">{t('inscription en ligne')}</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <div className="flex items-center justify-end gap-1">
                                            {user.status === 'pending' && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() => router.post(routes.admin.userApprove(user.id), {}, { preserveScroll: true })}
                                                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                                    >
                                                        <Icon name="check" className="h-3.5 w-3.5" />
                                                        {t('Valider')}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        title={t('Refuser')}
                                                        onClick={() => {
                                                            if (confirm(t('Refuser cette demande ?'))) {
                                                                router.post(routes.admin.userReject(user.id), {}, { preserveScroll: true });
                                                            }
                                                        }}
                                                        className="rounded-lg p-2 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                                    >
                                                        <Icon name="alert" className="h-4 w-4" />
                                                    </button>
                                                </>
                                            )}
                                            <Link
                                                href={routes.admin.userEdit(user.id)}
                                                title={t('Modifier')}
                                                className="rounded-lg p-2 text-ink-400 transition hover:bg-ink-100 hover:text-ink-800 dark:hover:bg-white/10"
                                            >
                                                <Icon name="pencil" className="h-4 w-4" />
                                            </Link>
                                            <button
                                                type="button"
                                                title={t('Supprimer')}
                                                onClick={() => {
                                                    if (confirm(t('Supprimer définitivement ce compte ?'))) {
                                                        router.delete(routes.admin.user(user.id));
                                                    }
                                                }}
                                                className="rounded-lg p-2 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                            >
                                                <Icon name="trash" className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <Pagination page={users} />
        </AdminLayout>
    );
}
