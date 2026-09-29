import { Link, router } from '@inertiajs/react';
import Icon from '@/components/icon';
import { Alert, Card } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { cn, routes, useT } from '@/lib/utils';

interface Module {
    cle: string;
    nom: string;
    description: string;
    icon: string;
    color: string;
    ouvertATous: boolean;
    rolesGestion: string[];
    lienAdmin: string | null;
    lienModule: string | null;
    pose: boolean;
    id: number | null;
    slug: string | null;
    actif: boolean;
    acces: number;
    aTraiter: { nombre: number; libelle: string } | null;
}

interface Props {
    modules: Module[];
    orphelins: { id: number; name: string; slug: string; moduleKey: string | null }[];
}

export default function AdminModules({ modules, orphelins }: Props) {
    const t = useT();

    const basculer = (module: Module) => {
        if (!module.slug) return;

        router.post(routes.admin.moduleToggle(module.slug), {}, { preserveScroll: true });
    };

    return (
        <AdminLayout
            title={t('Modules')}
            heading={t('Modules du portail')}
            subheading={t('Les applications servies par le portail lui-même : leur état, leurs accès, et par où on les administre.')}
        >
            <div className="space-y-4">
                {modules.map((module) => (
                    <Card key={module.cle} className="p-5">
                        <div className="flex flex-wrap items-start gap-4">
                            <span
                                className="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-white"
                                style={{ backgroundColor: module.color }}
                            >
                                <Icon name={module.icon} className="h-5 w-5" />
                            </span>

                            <div className="min-w-[220px] flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-sm font-semibold text-ink-900 dark:text-white">{module.nom}</h2>

                                    {!module.pose ? (
                                        <span className="badge bg-ink-100 text-ink-600 dark:bg-white/10 dark:text-ink-300">
                                            {t('non installé')}
                                        </span>
                                    ) : module.actif ? (
                                        <span className="badge bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">
                                            {t('en service')}
                                        </span>
                                    ) : (
                                        <span className="badge bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">
                                            {t('retiré')}
                                        </span>
                                    )}

                                    {module.ouvertATous && (
                                        <span className="badge bg-indigo-100 text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-200">
                                            {t('ouvert à tous')}
                                        </span>
                                    )}
                                </div>

                                <p className="mt-1 text-sm text-ink-500 dark:text-ink-400">{module.description}</p>

                                <div className="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                                    <span className="font-mono text-ink-400">{module.cle}</span>

                                    <span>
                                        {module.ouvertATous
                                            ? t('visible par tout le personnel')
                                            : t(':n accès accordé(s)', { n: module.acces })}
                                    </span>

                                    {module.rolesGestion.length > 0 && (
                                        <span>
                                            {t('Gestion :')} {module.rolesGestion.join(', ')}
                                        </span>
                                    )}
                                </div>

                                {module.aTraiter && (
                                    <p className="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                        <Icon name="alert" className="h-3.5 w-3.5" />
                                        {module.aTraiter.libelle}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                {module.lienAdmin && module.actif && (
                                    <a
                                        href={module.lienAdmin}
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700"
                                    >
                                        <Icon name="settings" className="h-3.5 w-3.5" />
                                        {t('Administrer')}
                                    </a>
                                )}

                                {module.lienModule && (
                                    <a
                                        href={module.lienModule}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-medium text-ink-600 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                                    >
                                        <Icon name="eye" className="h-3.5 w-3.5" />
                                        {t('Ouvrir')}
                                    </a>
                                )}

                                {module.slug && (
                                    <>
                                        <Link
                                            href={routes.admin.applicationAccess(module.slug)}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-medium text-ink-600 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                                        >
                                            <Icon name="key" className="h-3.5 w-3.5" />
                                            {t('Accès')}
                                        </Link>

                                        <Link
                                            href={routes.admin.applicationEdit(module.slug)}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-3 py-1.5 text-xs font-medium text-ink-600 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                                        >
                                            <Icon name="pencil" className="h-3.5 w-3.5" />
                                            {t('Tuile')}
                                        </Link>

                                        <button
                                            type="button"
                                            onClick={() => basculer(module)}
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition',
                                                module.actif
                                                    ? 'border-red-200 text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:hover:bg-red-500/10'
                                                    : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/30 dark:hover:bg-emerald-500/10',
                                            )}
                                        >
                                            {module.actif ? t('Retirer') : t('Remettre en service')}
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>

                        {!module.pose && (
                            <p className="mt-4 rounded-lg bg-ink-50 px-3 py-2 text-xs text-ink-600 dark:bg-white/5 dark:text-ink-300">
                                {t("Ce module est déclaré dans le code mais n'a pas de tuile. Créez-la depuis Applications, en choisissant le type « Module du portail » et la clé :cle.", { cle: module.cle })}
                            </p>
                        )}
                    </Card>
                ))}

                {orphelins.length > 0 && (
                    <Alert tone="warning" icon="alert">
                        {t("Ces tuiles pointent vers un module que le code ne déclare plus :")}{' '}
                        {orphelins.map((o) => o.name).join(', ')}.{' '}
                        {t('Elles restent visibles mais ne mènent nulle part.')}
                    </Alert>
                )}
            </div>
        </AdminLayout>
    );
}
