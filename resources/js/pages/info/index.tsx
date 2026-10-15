import { Link, router } from '@inertiajs/react';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import InfoLayout from '@/layouts/info-layout';
import { useRechercheInstantanee } from '@/lib/recherche';
import { routes } from '@/lib/utils';
import type { Article, Paginated, Rubrique } from '@/types';

interface Props {
    articles: Paginated<Article>;
    laUne: Article | null;
    rubriques: Rubrique[];
    filtres: { q: string; rubrique: string | null };
}

/** La ligne de pied d'un article : date, lecture, audience. */
function Reperes({ article, clair = false, bref = false }: { article: Article; clair?: boolean; bref?: boolean }) {
    const ton = clair ? 'text-white/70' : 'text-ink-400 dark:text-ink-500';

    return (
        <div className={`flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs ${ton}`}>
            <span>{article.publieLeLibelle}</span>
            <span aria-hidden>·</span>
            <span>{article.lecture} min{bref ? '' : ' de lecture'}</span>
            <span className="inline-flex items-center gap-1">
                <Icon name="eye" className="h-3.5 w-3.5" />
                {article.vues}
            </span>
            {article.partages > 0 && (
                <span className="inline-flex items-center gap-1">
                    <Icon name="share" className="h-3.5 w-3.5" />
                    {article.partages}
                </span>
            )}
        </div>
    );
}

function Etiquette({ article }: { article: Article }) {
    if (!article.rubrique) return null;

    return (
        <span
            className="inline-block rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-white"
            style={{ backgroundColor: article.rubrique.couleur }}
        >
            {article.rubrique.nom}
        </span>
    );
}

function Carte({ article }: { article: Article }) {
    return (
        <article className="group overflow-hidden rounded-2xl border border-ink-100 bg-white transition hover:-translate-y-0.5 hover:shadow-lg dark:border-white/10 dark:bg-ink-900">
            <Link href={routes.info.article(article.slug)} className="block">
                <div className="aspect-[16/10] overflow-hidden bg-ink-100 dark:bg-ink-800">
                    {article.imageUrl ? (
                        <img
                            src={article.imageUrl}
                            alt=""
                            loading="lazy"
                            className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                        />
                    ) : (
                        <span className="flex h-full items-center justify-center text-ink-300 dark:text-ink-600">
                            <Icon name="newspaper" className="h-10 w-10" />
                        </span>
                    )}
                </div>

                <div className="space-y-2.5 p-5">
                    <Etiquette article={article} />
                    <h2 className="text-lg font-semibold leading-snug text-ink-900 transition group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">
                        {article.titre}
                    </h2>
                    <p className="line-clamp-3 text-sm leading-relaxed text-ink-600 dark:text-ink-300">{article.chapeau}</p>
                    <Reperes article={article} bref />
                </div>
            </Link>
        </article>
    );
}

export default function InfoIndex({ articles, laUne, rubriques, filtres }: Props) {
    const [q, setQ] = useRechercheInstantanee(filtres.q ?? '', (terme) =>
        router.get(
            routes.info.index,
            { q: terme, rubrique: filtres.rubrique ?? '' },
            { preserveState: true, replace: true },
        ),
    );

    const rubriqueActive = rubriques.find((rubrique) => rubrique.slug === filtres.rubrique) ?? null;

    return (
        <InfoLayout titre="LA MAJESTUEUSE Info" rubriques={rubriques} active={filtres.rubrique ?? null}>
            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight text-ink-900 dark:text-white sm:text-4xl">
                        {rubriqueActive ? rubriqueActive.nom : 'LA MAJESTUEUSE Info'}
                    </h1>
                    <p className="mt-1.5 max-w-2xl text-ink-600 dark:text-ink-300">
                        {rubriqueActive?.description ?? "L'actualité du groupe : l'institut universitaire, l'institut de formation professionnelle et l'école bilingue."}
                    </p>
                </div>

                <label className="relative w-full sm:w-72">
                    <span className="sr-only">Rechercher un article</span>
                    <Icon name="search" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                    <input
                        type="search"
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="Rechercher un article"
                        className="w-full rounded-xl border border-ink-200 bg-white py-2.5 pl-9 pr-3 text-sm text-ink-800 outline-none transition focus:border-brand-500 dark:border-white/10 dark:bg-ink-900 dark:text-ink-100"
                    />
                </label>
            </div>

            {/* La une : un seul article, en grand, et seulement sur l'accueil. */}
            {laUne && (
                <Link
                    href={routes.info.article(laUne.slug)}
                    className="group mb-10 block overflow-hidden rounded-3xl bg-ink-900 text-white shadow-sm transition hover:shadow-xl dark:bg-ink-800"
                >
                    <div className="grid gap-0 md:grid-cols-2">
                        <div className="aspect-[16/10] overflow-hidden md:aspect-auto md:h-full md:min-h-[22rem]">
                            {laUne.imageUrl ? (
                                <img
                                    src={laUne.imageUrl}
                                    alt=""
                                    className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                />
                            ) : (
                                <span className="flex h-full items-center justify-center bg-white/5 text-white/40">
                                    <Icon name="newspaper" className="h-12 w-12" />
                                </span>
                            )}
                        </div>

                        <div className="flex flex-col justify-center gap-4 p-7 sm:p-10">
                            <span className="inline-flex w-fit items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-white/80">
                                <Icon name="pin" className="h-3.5 w-3.5" />À la une
                            </span>
                            <h2 className="text-2xl font-semibold leading-tight sm:text-3xl">{laUne.titre}</h2>
                            <p className="line-clamp-3 text-white/75">{laUne.chapeau}</p>
                            <Reperes article={laUne} clair />
                            <span className="inline-flex items-center gap-1.5 text-sm font-medium text-white">
                                Lire l'article
                                <Icon name="arrow-right" className="h-4 w-4 transition group-hover:translate-x-0.5" />
                            </span>
                        </div>
                    </div>
                </Link>
            )}

            {articles.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-ink-200 bg-white p-14 text-center dark:border-white/10 dark:bg-ink-900">
                    <Icon name="newspaper" className="mx-auto h-10 w-10 text-ink-300 dark:text-ink-600" />
                    <p className="mt-3 font-medium text-ink-700 dark:text-ink-200">Aucun article ne correspond.</p>
                    <p className="mt-1 text-sm text-ink-500 dark:text-ink-400">
                        {filtres.q ? 'Essayez un autre mot.' : 'Les publications paraîtront ici.'}
                    </p>
                </div>
            ) : (
                <>
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {articles.data.map((article) => (
                            <Carte key={article.id} article={article} />
                        ))}
                    </div>

                    <Pagination page={articles} />
                </>
            )}
        </InfoLayout>
    );
}
