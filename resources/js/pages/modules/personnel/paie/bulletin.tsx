import { Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import { Alert, Card, Input, Select, Textarea } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { routes } from '@/lib/utils';
import type { SharedProps } from '@/types';
import { Bouton, Champ, fcfa, Modale, Statut, Vide } from '../parts';

interface LigneDetail {
    libelle: string;
    type: 'fixe' | 'pourcentage';
    valeur: number;
    montant: number;
    source: 'profil' | 'ajustement';
}

interface Bulletin {
    id: number;
    contratId: number;
    agent: string | null;
    matricule: string | null;
    employeur: string | null;
    poste: string | null;
    mois: number;
    annee: number;
    periode: string;
    salaireBase: number;
    totalIndemnites: number;
    totalRetenues: number;
    salaireNet: number;
    detail: { indemnites: LigneDetail[]; retenues: LigneDetail[] };
    statut: string;
    note: string | null;
    valideLe: string | null;
    payeLe: string | null;
}

interface Props {
    bulletin: Bulletin;
    contrat: { id: number; quotite: number; echelon: string | null; profil: string | null; typeLibelle: string; agentId: number } | null;
    employeur: { nom: string; sigle: string; niu: string | null; numeroCnps: string | null; signataire: string | null } | null;
    agent: { id: number; nom: string | null; numeroCnps: string | null; enfants: number } | null;
    ajustements: { id: number; type: string; mode: string; libelle: string; montant: number; motif: string | null }[];
    peutGerer: boolean;
}

/** Une ligne du décompte, avec la règle qui l'a produite. */
function Ligne({ ligne, signe }: { ligne: LigneDetail; signe: '+' | '−' }) {
    return (
        <div className="flex items-center justify-between gap-4 py-1.5">
            <div className="min-w-0">
                <p className="truncate text-sm text-ink-800 dark:text-ink-100">{ligne.libelle}</p>
                <p className="text-[11px] text-ink-400">
                    {ligne.type === 'pourcentage' ? `${ligne.valeur} % du salaire de base` : 'montant fixe'}
                    {ligne.source === 'ajustement' && ' · exceptionnel ce mois'}
                </p>
            </div>
            <span
                className={
                    signe === '+'
                        ? 'shrink-0 text-sm font-medium tabular-nums text-emerald-700 dark:text-emerald-300'
                        : 'shrink-0 text-sm font-medium tabular-nums text-red-600 dark:text-red-400'
                }
            >
                {signe} {fcfa(ligne.montant)}
            </span>
        </div>
    );
}

export default function DetailBulletin({ bulletin, contrat, employeur, agent, ajustements, peutGerer }: Props) {
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [ouvert, setOuvert] = useState(false);

    const action = useForm({});
    const modifiable = bulletin.statut === 'brouillon';

    const ajustement = useForm({
        mois: bulletin.mois,
        annee: bulletin.annee,
        type: 'bonus',
        mode: 'fixe',
        libelle: '',
        montant: '',
        motif: '',
    });

    const ajouterAjustement = (event: FormEvent) => {
        event.preventDefault();
        ajustement.post(routes.personnel.ajustements(bulletin.contratId), {
            preserveScroll: true,
            onSuccess: () => {
                setOuvert(false);
                ajustement.reset();
            },
        });
    };

    const note = useForm({ note: bulletin.note ?? '' });

    return (
        <PersonnelLayout title={`Bulletin — ${bulletin.periode}`} peutGerer={peutGerer}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Link
                    href={`${routes.personnel.paie}?mois=${bulletin.mois}&annee=${bulletin.annee}`}
                    className="inline-flex items-center gap-1.5 text-sm text-ink-500 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-white"
                >
                    <Icon name="chevron-right" className="h-4 w-4 rotate-180" />
                    Retour à la paie
                </Link>

                <button
                    type="button"
                    onClick={() => window.print()}
                    className="inline-flex items-center gap-1.5 text-sm text-ink-500 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-white"
                >
                    <Icon name="print" className="h-4 w-4" />
                    Imprimer
                </button>
            </div>

            {errors?.paie && <Alert tone="danger">{errors.paie}</Alert>}
            {errors?.ajustement && <Alert tone="danger">{errors.ajustement}</Alert>}

            <Card className="p-6">
                <div className="flex flex-wrap items-start justify-between gap-4 border-b border-ink-100 pb-5 dark:border-white/5">
                    <div>
                        <p className="text-[11px] uppercase tracking-wide text-ink-400">Bulletin de paie</p>
                        <h1 className="mt-0.5 text-xl font-semibold text-ink-900 dark:text-white">{bulletin.periode}</h1>
                        <p className="mt-1 text-sm text-ink-600 dark:text-ink-300">
                            {employeur?.nom}
                            {employeur?.niu && ` · NIU ${employeur.niu}`}
                        </p>
                    </div>
                    <Statut valeur={bulletin.statut} />
                </div>

                <div className="grid gap-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p className="text-[11px] uppercase tracking-wide text-ink-400">Agent</p>
                        <Link
                            href={contrat ? routes.personnel.agent(contrat.agentId) : '#'}
                            className="mt-0.5 block text-sm font-medium text-ink-900 hover:underline dark:text-white"
                        >
                            {bulletin.agent}
                        </Link>
                        <p className="text-xs text-ink-500 dark:text-ink-400">{bulletin.matricule}</p>
                    </div>
                    <div>
                        <p className="text-[11px] uppercase tracking-wide text-ink-400">Poste</p>
                        <p className="mt-0.5 text-sm text-ink-900 dark:text-white">{bulletin.poste}</p>
                        <p className="text-xs text-ink-500 dark:text-ink-400">{contrat?.typeLibelle}</p>
                    </div>
                    <div>
                        <p className="text-[11px] uppercase tracking-wide text-ink-400">Échelon</p>
                        <p className="mt-0.5 text-sm text-ink-900 dark:text-white">{contrat?.echelon ?? '—'}</p>
                        <p className="text-xs text-ink-500 dark:text-ink-400">
                            {contrat && contrat.quotite < 100 ? `quotité ${contrat.quotite} %` : 'temps plein'}
                        </p>
                    </div>
                    <div>
                        <p className="text-[11px] uppercase tracking-wide text-ink-400">N° CNPS</p>
                        <p className="mt-0.5 text-sm text-ink-900 dark:text-white">{agent?.numeroCnps ?? '—'}</p>
                        <p className="text-xs text-ink-500 dark:text-ink-400">{agent?.enfants ?? 0} enfant(s) à charge</p>
                    </div>
                </div>

                <div className="space-y-5 border-t border-ink-100 pt-5 dark:border-white/5">
                    <div className="flex items-center justify-between gap-4">
                        <p className="text-sm font-semibold text-ink-900 dark:text-white">Salaire de base</p>
                        <span className="text-sm font-semibold tabular-nums text-ink-900 dark:text-white">{fcfa(bulletin.salaireBase)}</span>
                    </div>

                    <div>
                        <p className="mb-1 text-[11px] uppercase tracking-wide text-ink-400">Indemnités</p>
                        {bulletin.detail.indemnites.length === 0 ? (
                            <p className="py-1.5 text-sm text-ink-400">Aucune</p>
                        ) : (
                            <div className="divide-y divide-ink-100 dark:divide-white/5">
                                {bulletin.detail.indemnites.map((ligne, index) => (
                                    <Ligne key={index} ligne={ligne} signe="+" />
                                ))}
                            </div>
                        )}
                        <div className="mt-1.5 flex justify-between border-t border-ink-100 pt-1.5 text-sm dark:border-white/5">
                            <span className="text-ink-500 dark:text-ink-400">Total indemnités</span>
                            <span className="font-medium tabular-nums text-emerald-700 dark:text-emerald-300">
                                + {fcfa(bulletin.totalIndemnites)}
                            </span>
                        </div>
                    </div>

                    <div>
                        <p className="mb-1 text-[11px] uppercase tracking-wide text-ink-400">Retenues</p>
                        {bulletin.detail.retenues.length === 0 ? (
                            <p className="py-1.5 text-sm text-ink-400">Aucune</p>
                        ) : (
                            <div className="divide-y divide-ink-100 dark:divide-white/5">
                                {bulletin.detail.retenues.map((ligne, index) => (
                                    <Ligne key={index} ligne={ligne} signe="−" />
                                ))}
                            </div>
                        )}
                        <div className="mt-1.5 flex justify-between border-t border-ink-100 pt-1.5 text-sm dark:border-white/5">
                            <span className="text-ink-500 dark:text-ink-400">Total retenues</span>
                            <span className="font-medium tabular-nums text-red-600 dark:text-red-400">− {fcfa(bulletin.totalRetenues)}</span>
                        </div>
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-xl bg-teal-50 px-4 py-3 dark:bg-teal-500/10">
                        <span className="text-sm font-semibold text-teal-900 dark:text-teal-200">Net à payer</span>
                        <span className="text-lg font-semibold tabular-nums text-teal-900 dark:text-teal-200">{fcfa(bulletin.salaireNet)}</span>
                    </div>
                </div>

                {(bulletin.valideLe || bulletin.payeLe) && (
                    <p className="mt-4 text-xs text-ink-500 dark:text-ink-400">
                        {bulletin.valideLe && `Validé le ${bulletin.valideLe}. `}
                        {bulletin.payeLe && `Payé le ${bulletin.payeLe}.`}
                        {employeur?.signataire && ` Signataire : ${employeur.signataire}.`}
                    </p>
                )}
            </Card>

            {peutGerer && (
                <Card className="p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Traitement</h2>
                            <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                                {modifiable
                                    ? 'Le brouillon peut encore être recalculé ou ajusté.'
                                    : 'Ce bulletin est figé : les montants ne changent plus.'}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {modifiable && (
                                <>
                                    <Bouton
                                        variante="secondaire"
                                        icon="refresh"
                                        onClick={() => action.post(routes.personnel.bulletinRecalculer(bulletin.id), { preserveScroll: true })}
                                        disabled={action.processing}
                                    >
                                        Recalculer
                                    </Bouton>
                                    <Bouton
                                        icon="check"
                                        onClick={() => action.post(routes.personnel.bulletinValider(bulletin.id), { preserveScroll: true })}
                                        disabled={action.processing}
                                    >
                                        Valider
                                    </Bouton>
                                </>
                            )}
                            {bulletin.statut === 'valide' && (
                                <Bouton
                                    icon="wallet"
                                    onClick={() => action.post(routes.personnel.bulletinPayer(bulletin.id), { preserveScroll: true })}
                                    disabled={action.processing}
                                >
                                    Marquer payé
                                </Bouton>
                            )}
                        </div>
                    </div>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            note.put(routes.personnel.bulletinNote(bulletin.id), { preserveScroll: true });
                        }}
                        className="mt-4 border-t border-ink-100 pt-4 dark:border-white/5"
                    >
                        <Champ libelle="Note interne" erreur={note.errors.note} aide="Visible seulement des gestionnaires.">
                            <Textarea rows={2} value={note.data.note} onChange={(event) => note.setData('note', event.target.value)} />
                        </Champ>
                        <div className="mt-2 flex justify-end">
                            <Bouton type="submit" variante="secondaire" disabled={note.processing}>
                                {note.processing ? 'Enregistrement…' : 'Enregistrer la note'}
                            </Bouton>
                        </div>
                    </form>
                </Card>
            )}

            <Card className="p-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Ajustements du mois</h2>
                        <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                            Primes et retenues exceptionnelles, valables pour cette seule période.
                        </p>
                    </div>
                    {peutGerer && modifiable && (
                        <Bouton icon="plus" onClick={() => setOuvert(true)}>
                            Ajouter
                        </Bouton>
                    )}
                </div>

                <div className="mt-4 space-y-2">
                    {ajustements.length === 0 && <Vide message="Aucun ajustement sur cette période." icon="sliders" />}

                    {ajustements.map((ligne) => (
                        <div
                            key={ligne.id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-200 px-4 py-2.5 dark:border-white/10"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-ink-900 dark:text-white">{ligne.libelle}</p>
                                <p className="text-xs text-ink-500 dark:text-ink-400">
                                    {ligne.type === 'bonus' ? 'Prime' : 'Retenue'} ·{' '}
                                    {ligne.mode === 'pourcentage' ? `${ligne.montant} % du base` : fcfa(ligne.montant)}
                                    {ligne.motif && ` · ${ligne.motif}`}
                                </p>
                            </div>
                            {peutGerer && modifiable && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (confirm('Retirer cet ajustement ?')) {
                                            action.delete(routes.personnel.ajustement(ligne.id), { preserveScroll: true });
                                        }
                                    }}
                                    className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                    aria-label="Supprimer"
                                >
                                    <Icon name="trash" className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            </Card>

            <Modale titre="Ajouter un ajustement" ouverte={ouvert} onFermer={() => setOuvert(false)}>
                <form onSubmit={ajouterAjustement} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Champ libelle="Nature" erreur={ajustement.errors.type}>
                            <Select value={ajustement.data.type} onChange={(event) => ajustement.setData('type', event.target.value)}>
                                <option value="bonus">Prime (s'ajoute)</option>
                                <option value="retenue">Retenue (se déduit)</option>
                            </Select>
                        </Champ>
                        <Champ libelle="Mode de calcul" erreur={ajustement.errors.mode}>
                            <Select value={ajustement.data.mode} onChange={(event) => ajustement.setData('mode', event.target.value)}>
                                <option value="fixe">Montant fixe</option>
                                <option value="pourcentage">Pourcentage du salaire de base</option>
                            </Select>
                        </Champ>
                    </div>

                    <Champ libelle="Libellé" erreur={ajustement.errors.libelle}>
                        <Input value={ajustement.data.libelle} onChange={(event) => ajustement.setData('libelle', event.target.value)} required />
                    </Champ>

                    <Champ
                        libelle={ajustement.data.mode === 'pourcentage' ? 'Pourcentage' : 'Montant (FCFA)'}
                        erreur={ajustement.errors.montant}
                    >
                        <Input
                            type="number"
                            min={0}
                            step={ajustement.data.mode === 'pourcentage' ? '0.1' : '1'}
                            value={ajustement.data.montant}
                            onChange={(event) => ajustement.setData('montant', event.target.value)}
                            required
                        />
                    </Champ>

                    <Champ libelle="Motif" erreur={ajustement.errors.motif}>
                        <Textarea rows={2} value={ajustement.data.motif} onChange={(event) => ajustement.setData('motif', event.target.value)} />
                    </Champ>

                    <div className="flex justify-end gap-2">
                        <Bouton type="button" variante="secondaire" onClick={() => setOuvert(false)}>
                            Annuler
                        </Bouton>
                        <Bouton type="submit" icon="check" disabled={ajustement.processing}>
                            {ajustement.processing ? 'Enregistrement…' : 'Enregistrer'}
                        </Bouton>
                    </div>
                </form>
            </Modale>
        </PersonnelLayout>
    );
}
