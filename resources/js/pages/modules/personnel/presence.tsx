import { router } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card, Input } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { cn, routes } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Pointage {
    id: number;
    numero: string;
    nom: string;
    matricule: string | null;
    heure: string;
    jour: string;
    sens: 'entree' | 'sortie' | 'inconnu';
    moyen: string;
    moyenLibelle: string;
    connu: boolean;
}

interface Props {
    pointages: Paginated<Pointage>;
    filtres: { jour: string; q: string };
    resume: {
        passages: number;
        personnes: number;
        premier: string | null;
        dernier: string | null;
        inconnus: number;
    };
    lecteur: { vu: string | null; present: boolean; dernierPointage: string | null };
    peutGerer: boolean;
}

/** L'heure seule d'un horodatage, pour les deux bornes de la journée. */
function heureDe(valeur: string | null): string {
    if (!valeur) return '—';

    const date = new Date(valeur.replace(' ', 'T'));

    return Number.isNaN(date.getTime())
        ? valeur.slice(11, 16)
        : date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

export default function Presence({ pointages, filtres, resume, lecteur }: Props) {
    const [recherche, setRecherche] = useState(filtres.q);

    /*
     * L'écran se tient à jour tout seul : on le laisse ouvert sur un coin de
     * bureau, et les passages s'y posent à mesure. Seules les données sont
     * rechargées — la page ne clignote pas, et ce qu'on est en train de taper
     * reste en place.
     */
    useEffect(() => {
        const minuterie = setInterval(() => {
            router.reload({ only: ['pointages', 'resume', 'lecteur'] });
        }, 10_000);

        return () => clearInterval(minuterie);
    }, []);

    const filtrer = (event: FormEvent) => {
        event.preventDefault();
        router.get(routes.personnel.presence, { jour: filtres.jour, q: recherche }, { preserveState: true });
    };

    const jourAutre = (jour: string) => {
        router.get(routes.personnel.presence, { jour, q: filtres.q }, { preserveState: true });
    };

    const aujourdhui = new Date().toISOString().slice(0, 10);

    const cases: { libelle: string; valeur: string; detail?: string }[] = [
        { libelle: 'Passages', valeur: String(resume.passages) },
        { libelle: 'Personnes', valeur: String(resume.personnes) },
        { libelle: 'Premier', valeur: heureDe(resume.premier) },
        { libelle: 'Dernier', valeur: heureDe(resume.dernier) },
    ];

    return (
        <PersonnelLayout title="Présence" peutGerer>
            <div className="space-y-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold text-ink-900 dark:text-white">Présence</h1>
                        <p className="mt-1 text-sm text-ink-500 dark:text-ink-400">
                            Les passages devant le lecteur, à mesure qu’ils arrivent.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 text-xs">
                        <span
                            className={cn(
                                'inline-flex h-2 w-2 rounded-full',
                                lecteur.present ? 'bg-emerald-500' : 'bg-ink-300 dark:bg-white/20',
                            )}
                        />
                        <span className="text-ink-500 dark:text-ink-400">
                            {lecteur.vu ? `Lecteur vu ${lecteur.vu}` : 'Lecteur jamais vu'}
                            {lecteur.dernierPointage && ` · dernier passage ${lecteur.dernierPointage}`}
                        </span>
                    </div>
                </div>

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
                    <form onSubmit={filtrer} className="flex flex-wrap items-end gap-3">
                        <label className="block">
                            <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                Jour
                            </span>
                            <Input
                                type="date"
                                value={filtres.jour}
                                max={aujourdhui}
                                onChange={(event) => jourAutre(event.target.value)}
                                className="w-44"
                            />
                        </label>

                        <label className="block min-w-[220px] flex-1">
                            <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                Nom ou matricule
                            </span>
                            <Input
                                value={recherche}
                                onChange={(event) => setRecherche(event.target.value)}
                                placeholder="NKOA, 260147…"
                            />
                        </label>

                        <button type="submit" className="btn-ghost py-2 text-[13px]">
                            <Icon name="search" className="h-4 w-4" />
                            Chercher
                        </button>
                    </form>
                </Card>

                {resume.inconnus > 0 && (
                    <p className="text-xs text-amber-700 dark:text-amber-400">
                        {resume.inconnus} numéro(s) pointé(s) ne correspondent à aucun compte du portail : leur
                        matricule a changé, ou la personne n’y est pas encore.
                    </p>
                )}

                <Card className="overflow-hidden">
                    {pointages.data.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-ink-500 dark:text-ink-400">
                            Aucun passage ce jour-là.
                        </p>
                    ) : (
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-ink-100 text-left text-[11px] uppercase tracking-wide text-ink-500 dark:border-white/10 dark:text-ink-400">
                                    <th className="px-4 py-2.5 font-medium">Heure</th>
                                    <th className="px-4 py-2.5 font-medium">Personne</th>
                                    <th className="px-4 py-2.5 font-medium">Matricule</th>
                                    <th className="px-4 py-2.5 font-medium">Sens</th>
                                    <th className="px-4 py-2.5 font-medium">Moyen</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pointages.data.map((pointage) => (
                                    <tr
                                        key={pointage.id}
                                        className="border-b border-ink-50 text-sm last:border-0 dark:border-white/5"
                                    >
                                        <td className="px-4 py-2.5 font-medium tabular-nums text-ink-900 dark:text-white">
                                            {pointage.heure}
                                        </td>
                                        <td className="px-4 py-2.5 text-ink-800 dark:text-ink-100">
                                            {pointage.nom}
                                            {!pointage.connu && (
                                                <span className="ml-2 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                                    inconnu du portail
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 font-mono text-[13px] tabular-nums text-ink-600 dark:text-ink-300">
                                            {pointage.matricule ?? pointage.numero}
                                        </td>
                                        <td className="px-4 py-2.5 text-ink-600 dark:text-ink-300">
                                            {pointage.sens === 'entree'
                                                ? 'Entrée'
                                                : pointage.sens === 'sortie'
                                                  ? 'Sortie'
                                                  : '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-ink-600 dark:text-ink-300">
                                            {pointage.moyenLibelle}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Card>

                <Pagination page={pointages} />
            </div>
        </PersonnelLayout>
    );
}
