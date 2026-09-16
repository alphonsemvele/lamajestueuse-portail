import { Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { routes, useT } from '@/lib/utils';
import type { Application, Paginated } from '@/types';

interface Props {
    applications: Paginated<Application>;
    filters: { q: string | null; type: string | null };
}

export default function ApplicationsIndex({ applications, filters }: Props) {
    const t = useT();
    const [q, setQ] = useState(filters.q ?? '');

    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get(routes.admin.applications, { q, type: filters.type ?? '' }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout title={t('Applications')} subheading={t('Les projets accessibles depuis le portail et leur lien de redirection.')}>
            <div className="flex flex-wrap items-center gap-3">
                <form onSubmit={search} className="flex flex-1 flex-wrap items-center gap-2">
                    <div className="relative min-w-[220px] flex-1">
                        <Icon name="search" className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                        <input value={q} onChange={(e) => setQ(e.target.value)} type="search" placeholder={t('Rechercher…')} className="field-input pl-10" />
                    </div>
                    <select
                        value={filters.type ?? ''}
                        onChange={(e) => router.get(routes.admin.applications, { q, type: e.target.value }, { preserveState: true, replace: true })}
                        className="field-input w-auto"
                    >
                        <option value="">{t('Tous les types')}</option>
                        <option value="application">{t('Applications métier')}</option>
                        <option value="quick_link">{t('Liens rapides')}</option>
                    </select>
                </form>

                <Link href={routes.admin.applicationCreate} className="btn-primary">
                    <Icon name="plus" className="h-4 w-4" />
                    {t('Nouvelle application')}
                </Link>
            </div>

            <Card className="overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[820px] text-left text-sm">
                        <thead className="border-b border-ink-100 bg-ink-50/70 text-[11px] uppercase tracking-[0.07em] text-ink-500 dark:border-white/10 dark:bg-white/5">
                            <tr>
                                <th className="px-5 py-3 font-semibold">{t('Application')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Lien de redirection')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Catégorie')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Accès')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Statut')}</th>
                                <th className="px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-ink-100 dark:divide-white/10">
                            {applications.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center text-sm text-ink-400">
                                        {t('Aucune application enregistrée.')}
                                    </td>
                                </tr>
                            )}

                            {applications.data.map((application) => (
                                <tr key={application.id} className="transition hover:bg-ink-50/60 dark:hover:bg-white/5">
                                    <td className="px-5 py-3.5">
                                        <div className="flex items-center gap-3">
                                            <span
                                                className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg ring-1 ring-ink-900/5 dark:ring-white/10"
                                                style={{ color: application.color, background: `${application.color}14` }}
                                            >
                                                {application.logoUrl ? (
                                                    <img src={application.logoUrl} alt="" className="h-full w-full object-contain p-0.5" />
                                                ) : (
                                                    <Icon name={application.icon} className="h-4 w-4" />
                                                )}
                                            </span>
                                            <div className="min-w-0">
                                                <p className="font-medium text-ink-900 dark:text-white">{application.name}</p>
                                                <p className="text-xs text-ink-400">
                                                    {application.type === 'quick_link'
                                                        ? t('Lien rapide')
                                                        : application.type === 'module'
                                                          ? t('Module du portail')
                                                          : t('Application métier')}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-5 py-3.5">
                                        {application.type === 'module' ? (
                                            <span className="inline-flex items-center gap-1.5 text-[12px] text-ink-500 dark:text-ink-400">
                                                <Icon name="layers" className="h-3.5 w-3.5" />
                                                {t('Module du portail')}
                                            </span>
                                        ) : (
                                            <a
                                                href={application.url ?? '#'}
                                                target="_blank"
                                                rel="noopener"
                                                className="inline-flex max-w-[280px] items-center gap-1.5 truncate font-mono text-[12px] text-brand-600 hover:underline dark:text-brand-400"
                                            >
                                                {application.url}
                                                <Icon name="external" className="h-3 w-3 shrink-0" />
                                            </a>
                                        )}
                                    </td>
                                    <td className="px-5 py-3.5">
                                        {application.category ? (
                                            <span
                                                className="badge border"
                                                style={{
                                                    color: application.category.color,
                                                    borderColor: `${application.category.color}33`,
                                                    background: `${application.category.color}12`,
                                                }}
                                            >
                                                {application.category.name}
                                            </span>
                                        ) : (
                                            <span className="text-xs text-ink-400">—</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <Link
                                            href={routes.admin.applicationAccess(application.slug)}
                                            className="inline-flex items-center gap-1.5 text-ink-600 transition hover:text-brand-600 dark:text-ink-300"
                                        >
                                            <Icon name="users" className="h-4 w-4 text-ink-400" />
                                            {application.usersCount}
                                        </Link>
                                    </td>
                                    <td className="px-5 py-3.5">
                                        {application.isActive ? (
                                            <span className="badge bg-emerald-50 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300">{t('Active')}</span>
                                        ) : (
                                            <span className="badge bg-ink-100 text-ink-500 dark:bg-white/8 dark:text-ink-400">{t('Inactive')}</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={routes.admin.applicationEdit(application.slug)}
                                                title={t('Modifier')}
                                                className="rounded-lg p-2 text-ink-400 transition hover:bg-ink-100 hover:text-ink-800 dark:hover:bg-white/10"
                                            >
                                                <Icon name="pencil" className="h-4 w-4" />
                                            </Link>
                                            <button
                                                type="button"
                                                title={t('Supprimer')}
                                                onClick={() => {
                                                    if (confirm(t('Supprimer cette application du portail ?'))) {
                                                        router.delete(routes.admin.application(application.slug));
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

            <Pagination page={applications} />
        </AdminLayout>
    );
}
