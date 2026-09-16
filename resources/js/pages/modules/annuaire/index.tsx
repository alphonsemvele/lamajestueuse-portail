import { Link, router } from '@inertiajs/react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card, Input, Select } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { cn, routes, useChoice, useT } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Fiche {
    id: number;
    fullName: string;
    initials: string;
    avatarUrl: string | null;
    poste: string | null;
    entite: string | null;
    matricule: string | null;
    email: string | null;
    phone: string | null;
    sexe: 'M' | 'F' | null;
    instituts: { name: string; color: string; poste: string | null }[];
}

interface Props {
    personnel: Paginated<Fiche> | null;
    filters: { q: string; institut: string | null; poste: string; tri: string; ordre: string };
    instituts: { slug: string; name: string; color: string }[];
    postes: string[];
    total: number;
}

/** Une ligne d'information, masquée si la valeur est absente. */
function Ligne({ icon, children }: { icon: string; children: React.ReactNode }) {
    if (!children) return null;

    return (
        <div className="flex items-start gap-2 text-[13px] text-ink-600 dark:text-ink-300">
            <Icon name={icon} className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-400" />
            <span className="min-w-0 break-words">{children}</span>
        </div>
    );
}

export default function Annuaire({ personnel, filters, instituts, postes, total }: Props) {
    const t = useT();
    const choice = useChoice();

    const [q, setQ] = useState(filters.q);
    const [poste, setPoste] = useState(filters.poste);
    const [filtresOuverts, setFiltresOuverts] = useState(Boolean(filters.institut || filters.poste));
    const [enCours, setEnCours] = useState(false);

    const chercher = (params: Record<string, string> = {}) =>
        router.get(
            routes.annuaire,
            { q, poste, institut: filters.institut ?? '', tri: filters.tri, ordre: filters.ordre, ...params },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                // Rechargement partiel : inutile de renvoyer la liste des
                // instituts et des postes a chaque frappe.
                only: ['personnel', 'filters'],
                onStart: () => setEnCours(true),
                onFinish: () => setEnCours(false),
            },
        );

    /**
     * Recherche au fil de la frappe. Le delai evite une requete par lettre ;
     * Inertia annule d'elle-meme la visite precedente si une autre part.
     */
    const premierRendu = useRef(true);

    useEffect(() => {
        if (premierRendu.current) {
            premierRendu.current = false;
            return;
        }

        if (q === filters.q && poste === filters.poste) {
            return;
        }

        const minuteur = setTimeout(() => chercher(), 250);

        return () => clearTimeout(minuteur);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q, poste]);

    const soumettre = (event: FormEvent) => {
        event.preventDefault();
        chercher();
    };

    const barre = (
        <form onSubmit={soumettre}>
            <div className="flex items-center gap-2 rounded-2xl border border-ink-200 bg-white px-4 py-2.5 shadow-lg shadow-ink-900/5 transition focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-500/10 dark:border-white/10 dark:bg-ink-900">
                <Icon name="search" className="h-4 w-4 shrink-0 text-ink-400" />
                <input
                    type="search"
                    value={q}
                    autoFocus={!personnel}
                    onChange={(event) => setQ(event.target.value)}
                    placeholder={t('Rechercher par nom, matricule, e-mail ou téléphone…')}
                    className="min-w-0 flex-1 bg-transparent py-1.5 text-sm text-ink-900 placeholder:text-ink-400 focus:outline-none dark:text-white"
                />
                {(q || filters.q) && (
                    <button
                        type="button"
                        onClick={() => {
                            setQ('');
                            chercher({ q: '' });
                        }}
                        className="rounded-lg p-1 text-ink-400 transition hover:text-ink-700 dark:hover:text-white"
                        aria-label={t('Effacer')}
                    >
                        ✕
                    </button>
                )}
                <button
                    type="button"
                    onClick={() => setFiltresOuverts((ouvert) => !ouvert)}
                    className={cn(
                        'inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium transition',
                        filtresOuverts || filters.institut || filters.poste
                            ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/12 dark:text-brand-300'
                            : 'text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white',
                    )}
                >
                    <Icon name="sliders" className="h-3.5 w-3.5" />
                    {t('Filtres')}
                </button>
                {enCours && (
                    <span className="shrink-0 pr-1" aria-live="polite" aria-label={t('Recherche en cours…')}>
                        <span className="block h-4 w-4 animate-spin rounded-full border-2 border-ink-200 border-t-brand-600 dark:border-white/15 dark:border-t-brand-400" />
                    </span>
                )}
            </div>

            {filtresOuverts && (
                <div className="mt-2 grid gap-4 rounded-2xl border border-ink-200 bg-white p-4 shadow-lg shadow-ink-900/5 dark:border-white/10 dark:bg-ink-900 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <span className="field-label">{t('Poste')}</span>
                        <Input
                            list="postes-annuaire"
                            className="mt-2 py-2 text-[13px]"
                            placeholder={t('Tous')}
                            value={poste}
                            onChange={(event) => setPoste(event.target.value)}
                        />
                        <datalist id="postes-annuaire">
                            {postes.map((valeur) => (
                                <option key={valeur} value={valeur} />
                            ))}
                        </datalist>
                    </div>

                    <div>
                        <span className="field-label">{t('Institut')}</span>
                        <Select
                            className="mt-2 py-2 text-[13px]"
                            value={filters.institut ?? ''}
                            onChange={(event) => chercher({ institut: event.target.value })}
                        >
                            <option value="">{t('Tous')}</option>
                            {instituts.map((institut) => (
                                <option key={institut.slug} value={institut.slug}>
                                    {institut.name}
                                </option>
                            ))}
                        </Select>
                    </div>

                    <div>
                        <span className="field-label">{t('Trier par')}</span>
                        <Select className="mt-2 py-2 text-[13px]" value={filters.tri} onChange={(event) => chercher({ tri: event.target.value })}>
                            <option value="nom">{t('Nom')}</option>
                            <option value="matricule">{t('Matricule')}</option>
                            <option value="entite">{t('Entité')}</option>
                            <option value="poste">{t('Poste')}</option>
                        </Select>
                    </div>

                    <div>
                        <span className="field-label">{t('Ordre')}</span>
                        <Select className="mt-2 py-2 text-[13px]" value={filters.ordre} onChange={(event) => chercher({ ordre: event.target.value })}>
                            <option value="asc">{t('Croissant')}</option>
                            <option value="desc">{t('Décroissant')}</option>
                        </Select>
                    </div>
                </div>
            )}
        </form>
    );

    const accueil = personnel === null;

    return (
        <PortalLayout title={t('Annuaire')}>
            {/* La barre de recherche garde la meme identite (key) dans les deux
                etats : sans cela React la demonte au premier resultat et le
                champ perd le focus en pleine frappe. */}
            <div className={cn('mx-auto', accueil ? 'flex max-w-3xl flex-col items-center px-2 pt-6 text-center sm:pt-16' : 'max-w-none')}>
                <Link
                    key="retour"
                    href={routes.dashboard}
                    className={cn(
                        'inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition hover:text-brand-600 dark:text-ink-400',
                        accueil ? 'order-last mt-8' : 'mb-5 self-start',
                    )}
                >
                    <Icon name="chevron-right" className="h-4 w-4 rotate-180" />
                    {t('Retour au portail')}
                </Link>

                {accueil && (
                    <div key="hero" className="flex flex-col items-center">
                        <span className="flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-600 ring-8 ring-brand-50/40 dark:bg-brand-500/12 dark:text-brand-300 dark:ring-brand-500/5">
                            <Icon name="search" className="h-6 w-6" />
                        </span>
                        <h1 className="mt-7 text-4xl font-semibold tracking-tight text-ink-900 dark:text-white sm:text-5xl">
                            {t('Trouvez qui vous cherchez à')}{' '}
                            <span className="text-brand-600 dark:text-brand-400">La Majestueuse</span>
                        </h1>
                        <p className="mt-4 max-w-lg text-sm leading-relaxed text-ink-500 dark:text-ink-400">
                            {t('Cherchez dans tout le groupe par nom, matricule ou adresse — affinez avec les filtres si besoin.')}
                        </p>
                    </div>
                )}

                <div key="barre" className={cn('w-full text-left', accueil ? 'mt-9' : 'mx-auto max-w-4xl')}>
                    {barre}
                </div>

                {accueil ? (
                    <div key="reperes" className="mt-7 w-full">
                        <div className="grid gap-3 sm:grid-cols-3">
                            {(
                                [
                                    ['user', t('Par nom'), t('Complet ou partiel')],
                                    ['clipboard', t('Par matricule'), t("Identifiant de l'employé")],
                                    ['mail', t('Par e-mail'), t('Adresse professionnelle')],
                                ] as const
                            ).map(([icon, titre, detail]) => (
                                <div
                                    key={titre}
                                    className="flex items-center gap-3 rounded-xl border border-ink-200/70 bg-white/70 px-3.5 py-3 text-left dark:border-white/10 dark:bg-white/5"
                                >
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/12 dark:text-brand-300">
                                        <Icon name={icon} className="h-4 w-4" />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block text-[13px] font-semibold text-ink-800 dark:text-ink-100">{titre}</span>
                                        <span className="block truncate text-xs text-ink-400">{detail}</span>
                                    </span>
                                </div>
                            ))}
                        </div>

                        <p className="mt-8 text-center text-xs text-ink-400">
                            {choice(':count membre du personnel référencé|:count membres du personnel référencés', total)}
                        </p>
                    </div>
                ) : (
                    <div key="resultats" className="w-full">
                        <p className="mx-auto mt-4 flex max-w-4xl items-center gap-2 text-[13px] text-ink-500 dark:text-ink-400">
                            <Icon name="users" className="h-4 w-4 text-ink-400" />
                            {filters.q
                                ? choice(':count résultat pour « :terme »|:count résultats pour « :terme »', personnel.total).replace(
                                      ':terme',
                                      filters.q,
                                  )
                                : choice(':count résultat|:count résultats', personnel.total)}
                        </p>

                        {personnel.data.length === 0 ? (
                            <Card className="mx-auto mt-6 flex max-w-2xl flex-col items-center gap-3 px-6 py-16 text-center">
                                <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-ink-100 text-ink-400 dark:bg-white/5">
                                    <Icon name="users" className="h-6 w-6" />
                                </span>
                                <p className="text-sm font-medium text-ink-700 dark:text-ink-200">{t('Aucun membre du personnel trouvé')}</p>
                                <p className="max-w-sm text-xs text-ink-400">
                                    {t('Vérifiez l’orthographe, ou élargissez la recherche en retirant les filtres.')}
                                </p>
                            </Card>
                        ) : (
                            <div className="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                {personnel.data.map((fiche) => (
                                    <Card key={fiche.id} className="overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg">
                                        <div className="relative h-20 bg-linear-to-br from-brand-500 to-brand-700">
                                            <span className="badge absolute right-2.5 top-2.5 gap-1 bg-white/95 text-emerald-700 shadow-sm">
                                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                                {t('Actif')}
                                            </span>
                                        </div>

                                        <div className="relative z-10 -mt-10 px-4 pb-4">
                                            <Avatar
                                                url={fiche.avatarUrl}
                                                initials={fiche.initials}
                                                className="mx-auto h-20 w-20 text-xl ring-4 ring-white dark:ring-ink-900"
                                            />

                                            <p className="mt-3 text-center text-[15px] font-semibold leading-snug text-ink-900 dark:text-white">
                                                {fiche.fullName}
                                            </p>
                                            {fiche.poste && (
                                                <p className="mt-0.5 text-center text-[13px] text-brand-600 dark:text-brand-400">{fiche.poste}</p>
                                            )}

                                            {fiche.email && (
                                                <a
                                                    href={`mailto:${fiche.email}`}
                                                    className="mt-3 flex items-center gap-2 rounded-lg bg-brand-50/70 px-3 py-2 text-[13px] text-brand-700 transition hover:bg-brand-50 dark:bg-brand-500/10 dark:text-brand-300"
                                                >
                                                    <Icon name="mail" className="h-3.5 w-3.5 shrink-0" />
                                                    <span className="min-w-0 truncate">{fiche.email}</span>
                                                </a>
                                            )}

                                            <div className="mt-3 space-y-1.5">
                                                <Ligne icon="building">{fiche.entite}</Ligne>
                                                <Ligne icon="clipboard">{fiche.matricule}</Ligne>
                                                {fiche.phone && (
                                                    <div className="flex items-start gap-2 text-[13px]">
                                                        <Icon name="phone" className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-400" />
                                                        <a
                                                            href={`tel:${fiche.phone.replace(/\s/g, '')}`}
                                                            className="text-ink-600 transition hover:text-brand-600 dark:text-ink-300"
                                                        >
                                                            {fiche.phone}
                                                        </a>
                                                    </div>
                                                )}
                                                <Ligne icon="user">
                                                    {fiche.sexe === 'M' ? t('Masculin') : fiche.sexe === 'F' ? t('Féminin') : null}
                                                </Ligne>
                                            </div>

                                            {fiche.instituts.length > 0 && (
                                                <div className="mt-3 flex flex-wrap gap-1.5 border-t border-ink-100 pt-3 dark:border-white/10">
                                                    {fiche.instituts.map((institut) => (
                                                        <span
                                                            key={institut.name}
                                                            className="badge border"
                                                            title={institut.poste ?? undefined}
                                                            style={{
                                                                color: institut.color,
                                                                borderColor: `${institut.color}33`,
                                                                background: `${institut.color}12`,
                                                            }}
                                                        >
                                                            {institut.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        )}

                        <Pagination page={personnel} />
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
