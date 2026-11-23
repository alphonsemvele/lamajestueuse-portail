import { router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card, Input } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { cn, routes, useT } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Log {
    id: number;
    action: string;
    user: string | null;
    application: string | null;
    cible: string | null;
    ip: string | null;
    date: string;
}

interface Ligne {
    userId: number;
    nom: string;
    matricule: string | null;
    connexions: number;
    visites: number;
    ouvertures: number;
    entrees: number;
    premier: string;
    dernier: string;
}

interface Props {
    logs: Paginated<Log>;
    parUtilisateur: Ligne[];
    resume: { entrees: number; personnes: number; connexions: number; visites: number };
    filters: { action: string | null; du: string; au: string; q: string; vue: 'detail' | 'utilisateurs' };
}

export default function LogsIndex({ logs, parUtilisateur, resume, filters }: Props) {
    const t = useT();
    const [vue, setVue] = useState<'detail' | 'utilisateurs'>(filters.vue);
    const [recherche, setRecherche] = useState(filters.q);

    const labels: Record<string, string> = {
        login: t('Connexion'),
        logout: t('Déconnexion'),
        visite: t('Page'),
        open: t('Ouverture'),
        sso: t('Ouverture'),
        register: t('Inscription'),
    };

    const tones: Record<string, string> = {
        login: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300',
        logout: 'bg-ink-100 text-ink-600 dark:bg-white/8 dark:text-ink-300',
        visite: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/12 dark:text-indigo-300',
        open: 'bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300',
        sso: 'bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300',
        register: 'bg-amber-50 text-amber-700 dark:bg-amber-500/12 dark:text-amber-300',
    };

    const voir = (champs: Partial<{ action: string; du: string; au: string; q: string }>) => {
        router.get(
            routes.admin.logs,
            { action: filters.action ?? '', du: filters.du, au: filters.au, q: recherche, ...champs },
            { preserveState: true, replace: true },
        );
    };

    /** Le lien d'export porte la période, le filtre et la vue affichés. */
    const emporter = (format: 'csv' | 'pdf' = 'csv') =>
        `${routes.admin.logsExport}?${new URLSearchParams({
            du: filters.du,
            au: filters.au,
            q: filters.q,
            action: filters.action ?? '',
            vue,
            format,
        }).toString()}`;

    const chercher = (event: FormEvent) => {
        event.preventDefault();
        voir({});
    };

    const cases = [
        { libelle: t('Entrées'), valeur: resume.entrees },
        { libelle: t('Personnes'), valeur: resume.personnes },
        { libelle: t('Connexions'), valeur: resume.connexions },
        { libelle: t('Pages visitées'), valeur: resume.visites },
    ];

    return (
        <AdminLayout
            title={t('Journal d’accès')}
            subheading={t('Qui s’est connecté, quelles pages ont été ouvertes, et combien de fois.')}
        >
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {cases.map((item) => (
                    <Card key={item.libelle} className="p-4">
                        <p className="text-xs text-ink-500 dark:text-ink-400">{item.libelle}</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums text-ink-900 dark:text-white">
                            {item.valeur}
                        </p>
                    </Card>
                ))}
            </div>

            <Card className="p-4">
                <form onSubmit={chercher} className="flex flex-wrap items-end gap-3">
                    <label className="block">
                        <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">{t('Du')}</span>
                        <Input type="date" value={filters.du} onChange={(e) => voir({ du: e.target.value })} className="w-44" />
                    </label>

                    <label className="block">
                        <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">{t('Au')}</span>
                        <Input type="date" value={filters.au} onChange={(e) => voir({ au: e.target.value })} className="w-44" />
                    </label>

                    <label className="block">
                        <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">{t('Action')}</span>
                        <select
                            value={filters.action ?? ''}
                            onChange={(event) => voir({ action: event.target.value })}
                            className="field-input w-auto"
                        >
                            <option value="">{t('Toutes les actions')}</option>
                            <option value="login">{t('Connexions')}</option>
                            <option value="visite">{t('Pages visitées')}</option>
                            <option value="open">{t('Ouvertures d’application')}</option>
                            <option value="logout">{t('Déconnexions')}</option>
                        </select>
                    </label>

                    <label className="block min-w-[200px] flex-1">
                        <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                            {t('Nom ou matricule')}
                        </span>
                        <Input value={recherche} onChange={(event) => setRecherche(event.target.value)} />
                    </label>

                    <button type="submit" className="btn-ghost py-2 text-[13px]">
                        <Icon name="search" className="h-4 w-4" />
                        {t('Chercher')}
                    </button>
                </form>

                <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-ink-100 pt-3 dark:border-white/10">
                    {(
                        [
                            ['detail', t('Détail')],
                            ['utilisateurs', t('Par utilisateur')],
                        ] as const
                    ).map(([cle, libelle]) => (
                        <button
                            key={cle}
                            type="button"
                            onClick={() => setVue(cle)}
                            className={cn(
                                'rounded-lg px-3 py-1.5 text-xs font-medium transition',
                                vue === cle
                                    ? 'bg-brand-600 text-white'
                                    : 'border border-ink-200 text-ink-600 hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5',
                            )}
                        >
                            {libelle}
                        </button>
                    ))}

                    <span className="ml-auto flex flex-wrap gap-2">
                        <a href={emporter('pdf')} className="btn-ghost py-1.5 text-xs">
                            <Icon name="document" className="h-3.5 w-3.5" />
                            {t('PDF · personnel utilisant le logiciel')}
                        </a>
                        <a href={emporter()} className="btn-ghost py-1.5 text-xs">
                            <Icon name="download" className="h-3.5 w-3.5" />
                            {vue === 'utilisateurs' ? t('Excel · par utilisateur') : t('Excel · détail')}
                        </a>
                    </span>
                </div>
            </Card>

            {vue === 'utilisateurs' ? (
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="border-b border-ink-100 bg-ink-50/70 text-[11px] uppercase tracking-[0.07em] text-ink-500 dark:border-white/10 dark:bg-white/5">
                                <tr>
                                    <th className="px-5 py-3 font-semibold">{t('Employé')}</th>
                                    <th className="px-5 py-3 font-semibold">{t('Matricule')}</th>
                                    <th className="px-5 py-3 text-center font-semibold">{t('Connexions')}</th>
                                    <th className="px-5 py-3 text-center font-semibold">{t('Pages')}</th>
                                    <th className="px-5 py-3 text-center font-semibold">{t('Ouvertures')}</th>
                                    <th className="px-5 py-3 font-semibold">{t('Dernière trace')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-ink-100 dark:divide-white/10">
                                {parUtilisateur.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-5 py-14 text-center text-sm text-ink-400">
                                            {t('Aucune entrée.')}
                                        </td>
                                    </tr>
                                )}
                                {parUtilisateur.map((ligne) => (
                                    <tr key={ligne.userId} className="transition hover:bg-ink-50/60 dark:hover:bg-white/5">
                                        <td className="px-5 py-3 text-ink-800 dark:text-ink-100">{ligne.nom}</td>
                                        <td className="px-5 py-3 font-mono text-[12px] text-ink-500">
                                            {ligne.matricule ?? '—'}
                                        </td>
                                        <td className="px-5 py-3 text-center font-semibold tabular-nums text-ink-900 dark:text-white">
                                            {ligne.connexions}
                                        </td>
                                        <td className="px-5 py-3 text-center tabular-nums text-ink-600 dark:text-ink-300">
                                            {ligne.visites}
                                        </td>
                                        <td className="px-5 py-3 text-center tabular-nums text-ink-600 dark:text-ink-300">
                                            {ligne.ouvertures}
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-3 tabular-nums text-ink-600 dark:text-ink-300">
                                            {ligne.dernier}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            ) : (
                <>
                    <Card className="overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-left text-sm">
                                <thead className="border-b border-ink-100 bg-ink-50/70 text-[11px] uppercase tracking-[0.07em] text-ink-500 dark:border-white/10 dark:bg-white/5">
                                    <tr>
                                        <th className="px-5 py-3 font-semibold">{t('Date')}</th>
                                        <th className="px-5 py-3 font-semibold">{t('Employé')}</th>
                                        <th className="px-5 py-3 font-semibold">{t('Action')}</th>
                                        <th className="px-5 py-3 font-semibold">{t('Page ou application')}</th>
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
                                            <td className="whitespace-nowrap px-5 py-3 text-ink-600 dark:text-ink-300">
                                                {log.date}
                                            </td>
                                            <td className="px-5 py-3 text-ink-800 dark:text-ink-100">{log.user ?? '—'}</td>
                                            <td className="px-5 py-3">
                                                <span className={cn('badge', tones[log.action])}>
                                                    {labels[log.action] ?? log.action}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 text-ink-600 dark:text-ink-300">
                                                {log.application ?? log.cible ?? '—'}
                                            </td>
                                            <td className="px-5 py-3 font-mono text-[12px] text-ink-400">{log.ip}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    <Pagination page={logs} />
                </>
            )}
        </AdminLayout>
    );
}
