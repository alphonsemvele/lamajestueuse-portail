import { Link, router } from '@inertiajs/react';
import Icon from '@/components/icon';
import { Card, Select } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { routes } from '@/lib/utils';
import { Chiffre, Entete, fcfa, MOIS } from './parts';

interface Masse {
    bulletins: number;
    brut: number;
    retenues: number;
    net: number;
    a_payer: number;
}

interface EmployeurLigne {
    id: number;
    nom: string;
    sigle: string;
    actif: boolean;
    effectif: number;
    masse: Masse;
}

interface Props {
    periode: { mois: number; annee: number };
    employeurs: EmployeurLigne[];
    chiffres: { agents: number; contratsActifs: number; sansDossier: number; masse: Masse };
    echeances: {
        id: number;
        agent: string | null;
        userId: number;
        employeur: string | null;
        poste: string;
        typeLibelle: string;
        dateFin: string | null;
    }[];
    evenements: { id: number; agent: string | null; userId: number; date: string | null; typeLibelle: string; libelle: string }[];
    peutGerer: boolean;
    /** L'utilisateur ne suit qu'une partie des entités du groupe. */
    perimetreLimite: boolean;
}

function dateCourte(valeur: string | null): string {
    if (!valeur) return '—';

    const [annee, mois, jour] = valeur.split('-');

    return `${jour}/${mois}/${annee}`;
}

export default function TableauDeBordPersonnel({
    periode,
    employeurs,
    chiffres,
    echeances,
    evenements,
    peutGerer,
    perimetreLimite,
}: Props) {
    const changerPeriode = (champ: 'mois' | 'annee', valeur: number) =>
        router.get(routes.personnel.index, { ...periode, [champ]: valeur }, { preserveState: true, preserveScroll: true });

    const annees = Array.from({ length: 5 }, (_, index) => new Date().getFullYear() - 3 + index);

    return (
        <PersonnelLayout
            title="Personnel & paie"
            peutGerer={peutGerer}
            entete={
                <Entete
                    titre="Tableau de bord"
                    sous={
                        perimetreLimite
                            ? `Dossiers, carrière et rémunération — ${employeurs.map((e) => e.sigle).join(', ') || 'aucune entité attribuée'}.`
                            : "Dossiers, carrière et rémunération de l'ensemble du groupe."
                    }
                />
            }
        >
            {perimetreLimite && employeurs.length === 0 && (
                <div className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                    Aucune entité ne vous est attribuée : demandez à un administrateur du portail de vous rattacher aux
                    instituts dont vous suivez le personnel.
                </div>
            )}
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm text-ink-500 dark:text-ink-400">Période de paie</span>
                <Select
                    value={periode.mois}
                    onChange={(event) => changerPeriode('mois', Number(event.target.value))}
                    className="w-auto"
                    aria-label="Mois"
                >
                    {MOIS.map((libelle, index) => (
                        <option key={libelle} value={index + 1}>
                            {libelle}
                        </option>
                    ))}
                </Select>
                <Select
                    value={periode.annee}
                    onChange={(event) => changerPeriode('annee', Number(event.target.value))}
                    className="w-auto"
                    aria-label="Année"
                >
                    {annees.map((annee) => (
                        <option key={annee} value={annee}>
                            {annee}
                        </option>
                    ))}
                </Select>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Chiffre icon="users" libelle="Dossiers ouverts" valeur={String(chiffres.agents)} detail={`${chiffres.contratsActifs} contrat(s) actif(s)`} />
                <Chiffre
                    icon="wallet"
                    libelle="Masse salariale nette"
                    valeur={fcfa(chiffres.masse.net)}
                    detail={`${chiffres.masse.bulletins} bulletin(s) sur la période`}
                />
                <Chiffre icon="clock" libelle="Reste à payer" valeur={fcfa(chiffres.masse.a_payer)} detail="bulletins non encore payés" />
                <Chiffre
                    icon="alert"
                    libelle="Comptes sans dossier"
                    valeur={String(chiffres.sansDossier)}
                    detail="membres du portail à rattacher"
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="p-5 lg:col-span-2">
                    <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Par employeur</h2>
                    <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                        Chaque institut déclare et paie son propre personnel.
                    </p>

                    <div className="mt-4 space-y-2">
                        {employeurs.length === 0 && (
                            <p className="py-6 text-center text-sm text-ink-500 dark:text-ink-400">
                                Aucun employeur enregistré.{' '}
                                {peutGerer && (
                                    <Link href={routes.personnel.employeurs} className="font-medium text-teal-700 hover:underline dark:text-teal-300">
                                        Commencer par les employeurs
                                    </Link>
                                )}
                            </p>
                        )}

                        {employeurs.map((employeur) => (
                            <Link
                                key={employeur.id}
                                href={`${routes.personnel.paie}?employeur=${employeur.id}&mois=${periode.mois}&annee=${periode.annee}`}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-200 px-4 py-3 transition hover:border-teal-400 hover:bg-teal-50/40 dark:border-white/10 dark:hover:border-teal-500/40 dark:hover:bg-teal-500/5"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-ink-900 dark:text-white">
                                        {employeur.sigle}
                                        {!employeur.actif && <span className="ml-2 text-xs font-normal text-ink-400">(inactif)</span>}
                                    </p>
                                    <p className="truncate text-xs text-ink-500 dark:text-ink-400">{employeur.nom}</p>
                                </div>
                                <div className="flex items-center gap-6 text-right">
                                    <div>
                                        <p className="text-[11px] uppercase tracking-wide text-ink-400">Effectif</p>
                                        <p className="text-sm font-semibold tabular-nums text-ink-900 dark:text-white">{employeur.effectif}</p>
                                    </div>
                                    <div>
                                        <p className="text-[11px] uppercase tracking-wide text-ink-400">Net du mois</p>
                                        <p className="text-sm font-semibold tabular-nums text-ink-900 dark:text-white">{fcfa(employeur.masse.net)}</p>
                                    </div>
                                    <Icon name="chevron-right" className="h-4 w-4 text-ink-300" />
                                </div>
                            </Link>
                        ))}
                    </div>
                </Card>

                <Card className="p-5">
                    <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Contrats à échéance</h2>
                    <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Dans les deux prochains mois.</p>

                    <div className="mt-4 space-y-2">
                        {echeances.length === 0 && (
                            <p className="py-6 text-center text-sm text-ink-500 dark:text-ink-400">Aucune échéance proche.</p>
                        )}

                        {echeances.map((contrat) => (
                            <Link
                                key={contrat.id}
                                href={routes.personnel.agent(contrat.userId)}
                                className="block rounded-xl border border-amber-200 bg-amber-50/60 px-3 py-2.5 transition hover:bg-amber-50 dark:border-amber-500/25 dark:bg-amber-500/10"
                            >
                                <p className="truncate text-sm font-medium text-ink-900 dark:text-white">{contrat.agent ?? '—'}</p>
                                <p className="truncate text-xs text-ink-600 dark:text-ink-300">
                                    {contrat.typeLibelle} · {contrat.employeur} · fin le {dateCourte(contrat.dateFin)}
                                </p>
                            </Link>
                        ))}
                    </div>
                </Card>
            </div>

            <Card className="p-5">
                <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Derniers mouvements de carrière</h2>

                <div className="mt-4 space-y-1">
                    {evenements.length === 0 && (
                        <p className="py-6 text-center text-sm text-ink-500 dark:text-ink-400">Rien d'enregistré pour l'instant.</p>
                    )}

                    {evenements.map((evenement) => (
                        <Link
                            key={evenement.id}
                            href={routes.personnel.agent(evenement.userId)}
                            className="flex flex-wrap items-center gap-3 rounded-lg px-2 py-2 transition hover:bg-ink-50 dark:hover:bg-white/5"
                        >
                            <span className="w-24 shrink-0 text-xs tabular-nums text-ink-400">{dateCourte(evenement.date)}</span>
                            <span className="rounded-full bg-teal-50 px-2.5 py-0.5 text-[11px] font-semibold text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                                {evenement.typeLibelle}
                            </span>
                            <span className="text-sm font-medium text-ink-900 dark:text-white">{evenement.agent ?? '—'}</span>
                            <span className="text-sm text-ink-500 dark:text-ink-400">{evenement.libelle}</span>
                        </Link>
                    ))}
                </div>
        </Card>
        </PersonnelLayout>
    );
}
