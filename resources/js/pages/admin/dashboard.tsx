import { Link } from '@inertiajs/react';
import Icon from '@/components/icon';
import { Card } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { routes, useT } from '@/lib/utils';
import type { Application } from '@/types';

interface Props {
    stats: { applications: number; quickLinks: number; users: number; activeUsers: number; posts: number; opensToday: number };
    topApps: Application[];
    recentLogs: { id: number; action: string; user: string | null; application: string | null; ip: string | null; ago: string }[];
}

export default function AdminDashboard({ stats, topApps, recentLogs }: Props) {
    const t = useT();

    const tiles: [string, string, string | number, string][] = [
        ['layers', t('Applications métier'), stats.applications, 'text-brand-600 bg-brand-50 dark:bg-brand-500/12'],
        ['link', t('Liens rapides'), stats.quickLinks, 'text-violet-600 bg-violet-50 dark:bg-violet-500/12'],
        ['users', t('Comptes actifs'), `${stats.activeUsers} / ${stats.users}`, 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/12'],
        ['arrow-right', t('Ouvertures aujourd’hui'), stats.opensToday, 'text-amber-600 bg-amber-50 dark:bg-amber-500/12'],
    ];

    return (
        <AdminLayout title={t('Tableau de bord')} subheading={t('Vue d’ensemble du portail La Majestueuse.')}>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {tiles.map(([icon, label, value, tone]) => (
                    <Card key={label} className="flex items-center gap-4 p-5">
                        <span className={`flex h-11 w-11 items-center justify-center rounded-xl ${tone}`}>
                            <Icon name={icon} className="h-5 w-5" />
                        </span>
                        <div className="min-w-0">
                            <p className="text-2xl font-semibold text-ink-900 dark:text-white">{value}</p>
                            <p className="truncate text-xs text-ink-400">{label}</p>
                        </div>
                    </Card>
                ))}
            </div>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
                <Card className="overflow-hidden">
                    <div className="flex items-center justify-between border-b border-ink-100 px-5 py-4 dark:border-white/10">
                        <h2 className="text-sm font-semibold text-ink-900 dark:text-white">{t('Dernières ouvertures')}</h2>
                        <Link href={routes.admin.logs} className="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                            {t('Voir le journal')}
                        </Link>
                    </div>
                    <ul className="divide-y divide-ink-100 dark:divide-white/10">
                        {recentLogs.length === 0 && <li className="px-5 py-14 text-center text-sm text-ink-400">{t('Aucune activité enregistrée.')}</li>}
                        {recentLogs.map((log) => (
                            <li key={log.id} className="flex items-center gap-3 px-5 py-3">
                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ink-100 text-ink-500 dark:bg-white/8 dark:text-ink-300">
                                    <Icon name={log.action === 'login' ? 'login' : log.action === 'logout' ? 'logout' : 'arrow-right'} className="h-4 w-4" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm text-ink-800 dark:text-ink-100">
                                        <span className="font-medium">{log.user ?? t('Compte supprimé')}</span>{' '}
                                        {log.action === 'open' ? (
                                            <>
                                                {t('a ouvert')} <span className="font-medium">{log.application}</span>
                                            </>
                                        ) : log.action === 'login' ? (
                                            t('s’est connecté au portail')
                                        ) : (
                                            t('s’est déconnecté')
                                        )}
                                    </p>
                                    <p className="text-xs text-ink-400">
                                        {log.ago} · {log.ip}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>

                <div className="space-y-6">
                    <Card className="overflow-hidden">
                        <div className="border-b border-ink-100 px-5 py-4 dark:border-white/10">
                            <h2 className="text-sm font-semibold text-ink-900 dark:text-white">{t('Applications les plus distribuées')}</h2>
                        </div>
                        <ul className="divide-y divide-ink-100 dark:divide-white/10">
                            {topApps.map((app) => (
                                <li key={app.id} className="flex items-center gap-3 px-5 py-3">
                                    <span
                                        className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg"
                                        style={{ color: app.color, background: `${app.color}14` }}
                                    >
                                        {app.logoUrl ? <img src={app.logoUrl} alt="" className="h-full w-full object-contain p-0.5" /> : <Icon name={app.icon} className="h-4 w-4" />}
                                    </span>
                                    <span className="flex-1 truncate text-sm text-ink-800 dark:text-ink-100">{app.name}</span>
                                    <span className="text-sm font-semibold text-ink-500 dark:text-ink-400">{app.usersCount}</span>
                                </li>
                            ))}
                        </ul>
                    </Card>

                    <Card className="p-5">
                        <h2 className="text-sm font-semibold text-ink-900 dark:text-white">{t('Raccourcis')}</h2>
                        <div className="mt-4 space-y-2.5">
                            <Link href={routes.admin.applicationCreate} className="btn-primary w-full">
                                <Icon name="plus" className="h-4 w-4" />
                                {t('Déclarer une application')}
                            </Link>
                            <Link href={routes.admin.userCreate} className="btn-ghost w-full">
                                <Icon name="users" className="h-4 w-4" />
                                {t('Créer un compte employé')}
                            </Link>
                            <Link href={routes.admin.postCreate} className="btn-ghost w-full">
                                <Icon name="megaphone" className="h-4 w-4" />
                                {t('Publier une annonce')}
                            </Link>
                        </div>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
