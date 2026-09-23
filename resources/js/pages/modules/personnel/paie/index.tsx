import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '@/components/icon';
import { Alert, Card, Select } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { routes } from '@/lib/utils';
import type { SharedProps } from '@/types';
import { Bouton, Chiffre, Entete, fcfa, MOIS, Statut, Tableau, Vide } from '../parts';

interface BulletinLigne {
    id: number;
    agent: string | null;
    matricule: string | null;
    employeur: string | null;
    poste: string | null;
    salaireBase: number;
    totalIndemnites: number;
    totalRetenues: number;
    salaireNet: number;
    statut: string;
}

interface Props {
    periode: { mois: number; annee: number };
    moisLibelles: Record<string, string>;
    bulletins: BulletinLigne[];
    filtres: { employeur: number | null; statut: string | null };
    employeurs: { id: number; sigle: string; nom: string }[];
    masse: { bulletins: number; brut: number; retenues: number; net: number; a_payer: number };
    repartition: { brouillon: number; valide: number; paye: number };
    peutGerer: boolean;
}

export default function PaieDuMois({ periode, bulletins, filtres, employeurs, masse, repartition, peutGerer }: Props) {
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [selection, setSelection] = useState<number[]>([]);

    const naviguer = (params: Record<string, string | number>) =>
        router.get(
            routes.personnel.paie,
            {
                mois: periode.mois,
                annee: periode.annee,
                employeur: filtres.employeur ?? '',
                statut: filtres.statut ?? '',
                ...params,
            },
            { preserveState: true, preserveScroll: true },
        );

    const [enCours, setEnCours] = useState(false);

    const generer = () =>
        router.post(
            routes.personnel.paieGenerer,
            { mois: periode.mois, annee: periode.annee, employeur_id: filtres.employeur ?? '' },
            { preserveScroll: true, onStart: () => setEnCours(true), onFinish: () => setEnCours(false) },
        );

    const traiter = (action: 'valider' | 'payer') => {
        if (selection.length === 0) return;

        router.post(
            routes.personnel.paieLot,
            { action, bulletins: selection },
            {
                preserveScroll: true,
                onStart: () => setEnCours(true),
                onFinish: () => setEnCours(false),
                onSuccess: () => setSelection([]),
            },
        );
    };

    const basculer = (id: number) =>
        setSelection((actuelle) => (actuelle.includes(id) ? actuelle.filter((item) => item !== id) : [...actuelle, id]));

    const selectionnables = bulletins.filter((bulletin) => bulletin.statut !== 'paye').map((bulletin) => bulletin.id);
    const toutSelectionne = selectionnables.length > 0 && selectionnables.every((id) => selection.includes(id));

    const annees = Array.from({ length: 5 }, (_, index) => new Date().getFullYear() - 3 + index);

    return (
        <PersonnelLayout
            title="Payer les salaires"
            peutGerer={peutGerer}
            entete={<Entete titre="Payer les salaires" sous={`Période : ${MOIS[periode.mois - 1]} ${periode.annee}`} />}
        >
            {errors?.paie && <Alert tone="danger">{errors.paie}</Alert>}

            <Card className="p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Select value={periode.mois} onChange={(event) => naviguer({ mois: event.target.value })} className="w-auto" aria-label="Mois">
                        {MOIS.map((libelle, index) => (
                            <option key={libelle} value={index + 1}>
                                {libelle}
                            </option>
                        ))}
                    </Select>

                    <Select value={periode.annee} onChange={(event) => naviguer({ annee: event.target.value })} className="w-auto" aria-label="Année">
                        {annees.map((annee) => (
                            <option key={annee} value={annee}>
                                {annee}
                            </option>
                        ))}
                    </Select>

                    <Select
                        value={filtres.employeur ?? ''}
                        onChange={(event) => naviguer({ employeur: event.target.value })}
                        className="w-auto"
                        aria-label="Employeur"
                    >
                        <option value="">Tous les employeurs</option>
                        {employeurs.map((employeur) => (
                            <option key={employeur.id} value={employeur.id}>
                                {employeur.sigle}
                            </option>
                        ))}
                    </Select>

                    <Select
                        value={filtres.statut ?? ''}
                        onChange={(event) => naviguer({ statut: event.target.value })}
                        className="w-auto"
                        aria-label="Statut"
                    >
                        <option value="">Tous les statuts</option>
                        <option value="brouillon">Brouillons ({repartition.brouillon})</option>
                        <option value="valide">Validés ({repartition.valide})</option>
                        <option value="paye">Payés ({repartition.paye})</option>
                    </Select>

                    {peutGerer && (
                        <Bouton icon="refresh" onClick={generer} disabled={enCours} className="ml-auto">
                            {enCours ? 'Préparation…' : 'Préparer les bulletins'}
                        </Bouton>
                    )}
                </div>

                {peutGerer && (
                    <p className="mt-2 text-xs text-ink-500 dark:text-ink-400">
                        La préparation crée les brouillons manquants et recalcule ceux qui existent. Les bulletins
                        validés ou payés ne bougent plus.
                    </p>
                )}
            </Card>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Chiffre icon="document" libelle="Bulletins" valeur={String(masse.bulletins)} detail={`${repartition.brouillon} brouillon(s)`} />
                <Chiffre icon="wallet" libelle="Brut" valeur={fcfa(masse.brut)} detail="base + indemnités" />
                <Chiffre icon="layers" libelle="Retenues" valeur={fcfa(masse.retenues)} />
                <Chiffre icon="check" libelle="Net à payer" valeur={fcfa(masse.net)} detail={`${fcfa(masse.a_payer)} restant`} />
            </div>

            {peutGerer && selection.length > 0 && (
                <div className="sticky bottom-4 z-30 flex flex-wrap items-center gap-3 rounded-2xl border border-teal-300 bg-white p-3 shadow-lg dark:border-teal-500/30 dark:bg-ink-900">
                    <span className="text-sm font-medium text-ink-900 dark:text-white">
                        {selection.length} bulletin(s) sélectionné(s)
                    </span>
                    <div className="ml-auto flex gap-2">
                        <Bouton variante="secondaire" onClick={() => setSelection([])}>
                            Tout désélectionner
                        </Bouton>
                        <Bouton icon="check" onClick={() => traiter('valider')} disabled={enCours}>
                            Valider
                        </Bouton>
                        <Bouton icon="wallet" onClick={() => traiter('payer')} disabled={enCours}>
                            Marquer payés
                        </Bouton>
                    </div>
                </div>
            )}

            <Card className="p-4">
                {bulletins.length === 0 ? (
                    <Vide
                        message={
                            peutGerer
                                ? 'Aucun bulletin pour cette période. Utilisez « Préparer les bulletins ».'
                                : 'Aucun bulletin pour cette période.'
                        }
                        icon="wallet"
                    />
                ) : (
                    <Tableau
                        entetes={[
                            peutGerer ? (
                                <input
                                    key="tout"
                                    type="checkbox"
                                    checked={toutSelectionne}
                                    onChange={() => setSelection(toutSelectionne ? [] : selectionnables)}
                                    className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                                    aria-label="Tout sélectionner"
                                />
                            ) : (
                                ''
                            ),
                            'Agent',
                            'Employeur',
                            'Base',
                            'Indemnités',
                            'Retenues',
                            'Net',
                            'Statut',
                            '',
                        ]}
                    >
                        {bulletins.map((bulletin) => (
                            <tr key={bulletin.id} className="hover:bg-ink-50/60 dark:hover:bg-white/5">
                                <td className="px-3 py-2.5">
                                    {peutGerer && bulletin.statut !== 'paye' && (
                                        <input
                                            type="checkbox"
                                            checked={selection.includes(bulletin.id)}
                                            onChange={() => basculer(bulletin.id)}
                                            className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                                            aria-label={`Sélectionner ${bulletin.agent}`}
                                        />
                                    )}
                                </td>
                                <td className="px-3 py-2.5">
                                    <p className="font-medium text-ink-900 dark:text-white">{bulletin.agent ?? '—'}</p>
                                    <p className="text-xs text-ink-500 dark:text-ink-400">
                                        {bulletin.matricule} · {bulletin.poste}
                                    </p>
                                </td>
                                <td className="px-3 py-2.5 text-ink-600 dark:text-ink-300">{bulletin.employeur}</td>
                                <td className="px-3 py-2.5 tabular-nums text-ink-600 dark:text-ink-300">{fcfa(bulletin.salaireBase)}</td>
                                <td className="px-3 py-2.5 tabular-nums text-emerald-700 dark:text-emerald-300">
                                    {bulletin.totalIndemnites > 0 ? `+ ${fcfa(bulletin.totalIndemnites)}` : '—'}
                                </td>
                                <td className="px-3 py-2.5 tabular-nums text-red-600 dark:text-red-400">
                                    {bulletin.totalRetenues > 0 ? `− ${fcfa(bulletin.totalRetenues)}` : '—'}
                                </td>
                                <td className="px-3 py-2.5 font-semibold tabular-nums text-ink-900 dark:text-white">
                                    {fcfa(bulletin.salaireNet)}
                                </td>
                                <td className="px-3 py-2.5">
                                    <Statut valeur={bulletin.statut} />
                                </td>
                                <td className="px-3 py-2.5 text-right">
                                    <Link
                                        href={routes.personnel.bulletin(bulletin.id)}
                                        className="inline-flex items-center gap-1 text-sm font-medium text-teal-700 hover:underline dark:text-teal-300"
                                    >
                                        Détail
                                        <Icon name="chevron-right" className="h-3.5 w-3.5" />
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </Tableau>
                )}
            </Card>
        </PersonnelLayout>
    );
}
