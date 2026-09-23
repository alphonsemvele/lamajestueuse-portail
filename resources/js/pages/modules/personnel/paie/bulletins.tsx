import { Link, router } from '@inertiajs/react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card, Input, Select } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { routes } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Champ, Entete, fcfa, Statut, Tableau, Vide } from '../parts';

interface BulletinLigne {
    id: number;
    agent: string | null;
    matricule: string | null;
    employeur: string | null;
    poste: string | null;
    periode: string;
    salaireBase: number;
    totalIndemnites: number;
    totalRetenues: number;
    salaireNet: number;
    statut: string;
}

interface Props {
    bulletins: Paginated<BulletinLigne>;
    filtres: { q: string; annee: string | null; employeur: number | null; statut: string | null };
    employeurs: { id: number; sigle: string }[];
    annees: number[];
    peutGerer: boolean;
}

/**
 * Registre des bulletins, toutes périodes confondues : on y retrouve un
 * bulletin ancien par agent, par employeur ou par année.
 */
export default function RegistreBulletins({ bulletins, filtres, employeurs, annees, peutGerer }: Props) {
    const [q, setQ] = useState(filtres.q);

    const chercher = (params: Record<string, string> = {}) =>
        router.get(
            routes.personnel.bulletins,
            {
                q,
                annee: filtres.annee ?? '',
                employeur: filtres.employeur ?? '',
                statut: filtres.statut ?? '',
                ...params,
            },
            { preserveState: true, preserveScroll: true, replace: true, only: ['bulletins', 'filtres'] },
        );

    // Recherche au fil de la frappe, sans une requête par lettre.
    const premierRendu = useRef(true);

    useEffect(() => {
        if (premierRendu.current) {
            premierRendu.current = false;
            return;
        }

        if (q === filtres.q) return;

        const minuteur = setTimeout(() => chercher(), 250);

        return () => clearTimeout(minuteur);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const soumettre = (event: FormEvent) => {
        event.preventDefault();
        chercher();
    };

    return (
        <PersonnelLayout
            title="Bulletins de paie"
            peutGerer={peutGerer}
            entete={<Entete titre="Bulletins de paie" sous={`${bulletins.total} bulletin(s) édité(s) à ce jour.`} />}
        >
            <Card className="p-4">
                <form onSubmit={soumettre} className="flex flex-wrap items-end gap-3">
                    <Champ libelle="Rechercher" className="min-w-[220px] flex-1">
                        <Input
                            type="search"
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Nom ou matricule de l'agent…"
                        />
                    </Champ>

                    <Champ libelle="Année">
                        <Select value={filtres.annee ?? ''} onChange={(event) => chercher({ annee: event.target.value })} className="w-auto">
                            <option value="">Toutes</option>
                            {annees.map((annee) => (
                                <option key={annee} value={annee}>
                                    {annee}
                                </option>
                            ))}
                        </Select>
                    </Champ>

                    <Champ libelle="Employeur">
                        <Select
                            value={filtres.employeur ?? ''}
                            onChange={(event) => chercher({ employeur: event.target.value })}
                            className="w-auto"
                        >
                            <option value="">Tous</option>
                            {employeurs.map((employeur) => (
                                <option key={employeur.id} value={employeur.id}>
                                    {employeur.sigle}
                                </option>
                            ))}
                        </Select>
                    </Champ>

                    <Champ libelle="Statut">
                        <Select value={filtres.statut ?? ''} onChange={(event) => chercher({ statut: event.target.value })} className="w-auto">
                            <option value="">Tous</option>
                            <option value="brouillon">Brouillons</option>
                            <option value="valide">Validés</option>
                            <option value="paye">Payés</option>
                        </Select>
                    </Champ>
                </form>
            </Card>

            <Card className="p-4">
                {bulletins.data.length === 0 ? (
                    <Vide message="Aucun bulletin ne correspond à cette recherche." icon="document" />
                ) : (
                    <Tableau entetes={['Période', 'Agent', 'Employeur', 'Base', 'Indemnités', 'Retenues', 'Net', 'Statut', '']}>
                        {bulletins.data.map((bulletin) => (
                            <tr key={bulletin.id} className="hover:bg-ink-50/60 dark:hover:bg-white/5">
                                <td className="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{bulletin.periode}</td>
                                <td className="px-3 py-2.5">
                                    <p className="text-ink-900 dark:text-white">{bulletin.agent ?? '—'}</p>
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
                                        Voir
                                        <Icon name="chevron-right" className="h-3.5 w-3.5" />
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </Tableau>
                )}

                <Pagination page={bulletins} />
            </Card>
        </PersonnelLayout>
    );
}
