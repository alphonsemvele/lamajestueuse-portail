import { router } from '@inertiajs/react';
import Pagination from '@/components/pagination';
import { Card } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { cn, routes, useT } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Log {
    id: number;
    action: string;
    user: string | null;
    application: string | null;
    ip: string | null;
    date: string;
}

export default function LogsIndex({ logs, filters }: { logs: Paginated<Log>; filters: { action: string | null } }) {
    const t = useT();

    const labels: Record<string, string> = {
        login: t('Connexion'),
        logout: t('Déconnexion'),
        open: t('Ouverture'),
        register: t('Inscription'),
    };

    const tones: Record<string, string> = {
        login: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300',
        logout: 'bg-ink-100 text-ink-600 dark:bg-white/8 dark:text-ink-300',
        open: 'bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300',
        register: 'bg-amber-50 text-amber-700 dark:bg-amber-500/12 dark:text-amber-300',
    };

    return (
        <AdminLayout title={t('Journal d’accès')} subheading={t('Qui s’est connecté, et quelle application a été ouverte.')}>
            <select
                value={filters.action ?? ''}
                onChange={(event) => router.get(routes.admin.logs, { action: event.target.value }, { preserveState: true, replace: true })}
                className="field-input w-auto"
            >
                <option value="">{t('Toutes les actions')}</option>
                <option value="login">{t('Connexions')}</option>
                <option value="logout">{t('Déconnexions')}</option>
                <option value="open">{t('Ouvertures d’application')}</option>
            </select>

            <Card className="overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="border-b border-ink-100 bg-ink-50/70 text-[11px] uppercase tracking-[0.07em] text-ink-500 dark:border-white/10 dark:bg-white/5">
                            <tr>
                                <th className="px-5 py-3 font-semibold">{t('Date')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Employé')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Action')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Application')}</th>
                                <th className="px-5 py-3 font-semibold">{t('Adresse IP')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-ink-100 dark:divide-white/10">
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-5 py-14 text-center text-sm text-ink-400">
                                        {t('Aucune entrée.')}
                                    </td>
                                </tr>
                            )}
                            {logs.data.map((log) => (
                                <tr key={log.id} className="transition hover:bg-ink-50/60 dark:hover:bg-white/5">
                                    <td className="whitespace-nowrap px-5 py-3 text-ink-600 dark:text-ink-300">{log.date}</td>
                                    <td className="px-5 py-3 text-ink-800 dark:text-ink-100">{log.user ?? '—'}</td>
                                    <td className="px-5 py-3">
                                        <span className={cn('badge', tones[log.action])}>{labels[log.action] ?? log.action}</span>
                                    </td>
                                    <td className="px-5 py-3 text-ink-600 dark:text-ink-300">{log.application ?? '—'}</td>
                                    <td className="px-5 py-3 font-mono text-[12px] text-ink-400">{log.ip}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <Pagination page={logs} />
        </AdminLayout>
    );
}
