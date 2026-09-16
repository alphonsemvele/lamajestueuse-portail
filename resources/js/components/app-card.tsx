import { Link } from '@inertiajs/react';
import { type ReactNode, useState } from 'react';
import Icon from '@/components/icon';
import { cn, routes, useT } from '@/lib/utils';
import type { Application } from '@/types';

export default function AppCard({ application, variant = 'app' }: { application: Application; variant?: 'app' | 'quick' }) {
    const t = useT();
    const isQuick = variant === 'quick';

    // Une application déclarée mais sans lien de destination n'est pas encore
    // en ligne : la tuile reste visible et annonce son ouverture prochaine.
    const bientot = application.destination === null;
    const [annonce, setAnnonce] = useState(false);

    const style =
        'group card flex flex-col overflow-hidden text-left transition duration-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-xl hover:shadow-brand-900/8 dark:hover:border-brand-500/40';

    const contenu: ReactNode = (
        <>
            <div
                className="relative h-24 overflow-hidden"
                style={{ background: `linear-gradient(135deg, ${application.color}, ${application.color}22)` }}
            >
                {application.coverUrl && (
                    <>
                        <img
                            src={application.coverUrl}
                            alt=""
                            className="h-full w-full object-cover opacity-90 transition duration-500 group-hover:scale-[1.06]"
                        />
                        <div className="absolute inset-0 bg-linear-to-t from-black/45 to-transparent" />
                    </>
                )}

                {isQuick && (
                    <span className="badge absolute right-2.5 top-2.5 gap-1 bg-white/95 text-ink-600 shadow-sm">
                        <Icon name="pin" className="h-3 w-3" />
                        {t('Épinglé')}
                    </span>
                )}

                {bientot && (
                    <span className="badge absolute left-2.5 top-2.5 gap-1 bg-white/95 text-ink-600 shadow-sm">
                        <Icon name="clock" className="h-3 w-3" />
                        {t('Bientôt')}
                    </span>
                )}
            </div>

            <div className="relative -mt-6 flex flex-1 flex-col px-4 pb-4">
                <div className="flex items-start gap-3">
                    <span
                        className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-md ring-1 ring-ink-900/5 dark:bg-ink-800 dark:ring-white/10"
                        style={{ color: application.color }}
                    >
                        {application.logoUrl ? (
                            <img src={application.logoUrl} alt="" className="h-full w-full object-contain p-1" />
                        ) : (
                            <Icon name={application.icon} className="h-5 w-5" />
                        )}
                    </span>
                    <div className="min-w-0 pt-6">
                        <p className="truncate text-[15px] font-semibold text-ink-900 dark:text-white">{application.name}</p>
                        {application.category && (
                            <span
                                className="badge mt-1 border"
                                style={{
                                    color: application.category.color,
                                    borderColor: `${application.category.color}33`,
                                    background: `${application.category.color}12`,
                                }}
                            >
                                {application.category.name}
                            </span>
                        )}
                    </div>
                </div>

                <p className="mt-3 line-clamp-2 min-h-[2.5rem] text-[13px] leading-relaxed text-ink-500 dark:text-ink-400">
                    {application.description}
                </p>

                <div className="mt-4 flex items-center justify-between border-t border-ink-100 pt-3 dark:border-white/10">
                    <span className="text-[13px] font-medium text-ink-600 group-hover:text-brand-700 dark:text-ink-200 dark:group-hover:text-brand-300">
                        {bientot ? t('Bientôt disponible') : isQuick ? t('Ouvrir') : t("Ouvrir l'application")}
                    </span>
                    <span
                        className={cn(
                            'flex h-7 w-7 items-center justify-center rounded-full bg-ink-100 text-ink-500 transition group-hover:bg-brand-600 group-hover:text-white dark:bg-white/8 dark:text-ink-300',
                        )}
                    >
                        <Icon name={bientot ? 'clock' : isQuick ? 'external' : 'chevron-right'} className="h-3.5 w-3.5" />
                    </span>
                </div>
            </div>
        </>
    );

    return (
        <>
            {bientot ? (
                <button type="button" onClick={() => setAnnonce(true)} className={style}>
                    {contenu}
                </button>
            ) : (
                <Link
                    href={routes.openApp(application.slug)}
                    target={application.opensNewTab ? '_blank' : undefined}
                    rel={application.opensNewTab ? 'noopener' : undefined}
                    className={style}
                >
                    {contenu}
                </Link>
            )}

            {annonce && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby={`bientot-${application.id}`}
                    className="fixed inset-0 z-50 flex items-center justify-center p-4"
                    onClick={() => setAnnonce(false)}
                >
                    <div className="absolute inset-0 bg-ink-900/50 backdrop-blur-[2px]" />
                    <div
                        className="relative w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl dark:bg-ink-800"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <span
                            className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl"
                            style={{ color: application.color, background: `${application.color}14` }}
                        >
                            <Icon name="clock" className="h-6 w-6" />
                        </span>
                        <h2 id={`bientot-${application.id}`} className="mt-4 text-lg font-semibold text-ink-900 dark:text-white">
                            {application.name}
                        </h2>
                        <p className="mt-2 text-sm text-ink-500 dark:text-ink-400">
                            {t('Cette application sera bientôt disponible. Elle apparaîtra ici dès sa mise en ligne.')}
                        </p>
                        <button type="button" autoFocus onClick={() => setAnnonce(false)} className="btn-primary mt-5 w-full justify-center">
                            {t("J'ai compris")}
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
