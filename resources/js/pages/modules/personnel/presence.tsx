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
    filtres: { du: string; au: string; q: string };
    resume: {
        passages: number;
        personnes: number;
        jours: number;
        premier: string | null;
        dernier: string | null;
        inconnus: number;
    };
    parPersonne: {
        numero: string;
        nom: string;
        jours: number;
        passages: number;
        premier: string;
        dernier: string;
        connu: boolean;
    }[];
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

export default function Presence({ pointages, filtres, resume, parPersonne, lecteur }: Props) {
    const [vue, setVue] = useState<'passages' | 'personnes'>('passages');
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

    const voir = (champs: Partial<{ du: string; au: string; q: string }>) => {
        router.get(
            routes.personnel.presence,
            { du: filtres.du, au: filtres.au, q: recherche, ...champs },
            { preserveState: true },
        );
    };

    const filtrer = (event: FormEvent) => {
        event.preventDefault();
        voir({});
    };

    /** Le lien d'export porte la période et le filtre affichés. */
    const emporter = (format: 'pdf' | 'csv') =>
        `${routes.personnel.presenceExport}?${new URLSearchParams({
            du: filtres.du,
            au: filtres.au,
            q: filtres.q,
            vue,
            format,
        }).toString()}`;

    const raccourci = (jours: number) => {
        const fin = new Date();
        const debut = new Date();
        debut.setDate(debut.getDate() - jours);

        voir({ du: debut.toISOString().slice(0, 10), au: fin.toISOString().slice(0, 10) });
    };

    const aujourdhui = new Date().toISOString().slice(0, 10);

    const cases: { libelle: string; valeur: string; detail?: string }[] = [
        { libelle: 'Passages', valeur: String(resume.passages) },
        { libelle: 'Personnes', valeur: String(resume.personnes) },
        { libelle: 'Jours avec passage', valeur: String(resume.jours) },
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

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
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
                            <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">Du</span>
                            <Input
                                type="date"
                                value={filtres.du}
                                max={aujourdhui}
                                onChange={(event) => voir({ du: event.target.value })}
                                className="w-44"
                            />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">Au</span>
                            <Input
                                type="date"
                                value={filtres.au}
                                max={aujourdhui}
                                onChange={(event) => voir({ au: event.target.value })}
                                className="w-44"
                            />
                        </label>

                        <label className="block min-w-[200px] flex-1">
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

                    <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-ink-100 pt-3 dark:border-white/10">
                        {[
                            { libelle: 'Aujourd’hui', jours: 0 },
                            { libelle: '7 jours', jours: 6 },
                            { libelle: '30 jours', jours: 29 },
                        ].map((choix) => (
                            <button
                                key={choix.libelle}
                                type="button"
                                onClick={() => raccourci(choix.jours)}
                                className="rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-medium text-ink-600 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                            >
                                {choix.libelle}
                            </button>
                        ))}

                        <span className="ml-auto flex flex-wrap gap-2">
                            <a href={emporter('pdf')} className="btn-ghost py-1.5 text-xs">
                                <Icon name="document" className="h-3.5 w-3.5" />
                                Registre PDF
                            </a>
                            <a href={emporter('csv')} className="btn-ghost py-1.5 text-xs">
                                <Icon name="download" className="h-3.5 w-3.5" />
                                {vue === 'personnes' ? 'Excel · par personne' : 'Excel · tous les passages'}
                            </a>
                        </span>
                    </div>
                </Card>

                {resume.inconnus > 0 && (
                    <p className="text-xs text-amber-700 dark:text-amber-400">
                        {resume.inconnus} numéro(s) pointé(s) ne correspondent à aucun compte du portail : leur
                        matricule a changé, ou la personne n’y est pas encore.
                    </p>
                )}

                <div className="flex gap-2">
                    {([
                        ['passages', 'Passages'],
                        ['personnes', 'Par personne'],
                    ] as const).map(([cle, libelle]) => (
                        <button
                            key={cle}
                            type="button"
                            onClick={() => setVue(cle)}
                            className={cn(
                                'rounded-xl px-3.5 py-2 text-[13px] font-medium transition',
                                vue === cle
                                    ? 'bg-brand-600 text-white'
                                    : 'border border-ink-200 text-ink-600 hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5',
                            )}
                        >
                            {libelle}
                        </button>
                    ))}
                </div>

                {vue === 'personnes' ? (
                    <Card className="overflow-hidden">
                        {parPersonne.length === 0 ? (
                            <p className="px-5 py-12 text-center text-sm text-ink-500 dark:text-ink-400">
                                Aucun passage sur cette période.
                            </p>
                        ) : (
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-ink-100 text-left text-[11px] uppercase tracking-wide text-ink-500 dark:border-white/10 dark:text-ink-400">
                                        <th className="px-4 py-2.5 font-medium">Personne</th>
                                        <th className="px-4 py-2.5 font-medium">Numéro</th>
                                        <th className="px-4 py-2.5 text-center font-medium">Jours</th>
                                        <th className="px-4 py-2.5 text-center font-medium">Passages</th>
                                        <th className="px-4 py-2.5 font-medium">Premier</th>
                                        <th className="px-4 py-2.5 font-medium">Dernier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {parPersonne.map((personne) => (
                                        <tr
                                            key={personne.numero}
                                            className="border-b border-ink-50 text-sm last:border-0 dark:border-white/5"
                                        >
                                            <td className="px-4 py-2.5 text-ink-800 dark:text-ink-100">
                                                {personne.nom}
                                                {!personne.connu && (
                                                    <span className="ml-2 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                                        inconnu du portail
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-2.5 font-mono text-[13px] tabular-nums text-ink-600 dark:text-ink-300">
                                                {personne.numero}
                                            </td>
                                            <td className="px-4 py-2.5 text-center tabular-nums text-ink-900 dark:text-white">
                                                {personne.jours}
                                            </td>
                                            <td className="px-4 py-2.5 text-center tabular-nums text-ink-600 dark:text-ink-300">
                                                {personne.passages}
                                            </td>
                                            <td className="px-4 py-2.5 tabular-nums text-ink-600 dark:text-ink-300">
                                                {personne.premier}
                                            </td>
                                            <td className="px-4 py-2.5 tabular-nums text-ink-600 dark:text-ink-300">
                                                {personne.dernier}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Card>
                ) : (
                <Card className="overflow-hidden">
                    {pointages.data.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-ink-500 dark:text-ink-400">
                            Aucun passage ce jour-là.
                        </p>
                    ) : (
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-ink-100 text-left text-[11px] uppercase tracking-wide text-ink-500 dark:border-white/10 dark:text-ink-400">
                                    {filtres.du !== filtres.au && (
                                        <th className="px-4 py-2.5 font-medium">Jour</th>
                                    )}
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
                                        {filtres.du !== filtres.au && (
                                            <td className="px-4 py-2.5 tabular-nums text-ink-600 dark:text-ink-300">
                                                {pointage.jour}
                                            </td>
                                        )}
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
                )}

                {vue === 'passages' && <Pagination page={pointages} />}
            </div>
        </PersonnelLayout>
    );
}
