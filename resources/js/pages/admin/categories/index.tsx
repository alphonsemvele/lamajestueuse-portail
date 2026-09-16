import { router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Label from '@/components/label';
import { Card, Input } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { routes, useChoice, useT } from '@/lib/utils';
import type { Category } from '@/types';

/** Ligne editable : une rangee sur grand ecran, une carte empilee sur mobile. */
function Row({ category }: { category: Category }) {
    const t = useT();
    const choice = useChoice();
    const [name, setName] = useState(category.name);
    const [color, setColor] = useState(category.color);
    const [order, setOrder] = useState(String(category.sortOrder ?? 0));

    const save = (event: FormEvent) => {
        event.preventDefault();
        router.put(routes.admin.category(category.id), { name, color, sort_order: order }, { preserveScroll: true });
    };

    return (
        <div className="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:flex-wrap sm:items-center sm:px-5">
            <form onSubmit={save} className="flex w-full min-w-0 items-center gap-2 sm:w-auto sm:flex-1 sm:gap-2.5">
                <input
                    type="color"
                    aria-label={t('Couleur')}
                    value={color}
                    onChange={(e) => setColor(e.target.value)}
                    className="h-10 w-11 shrink-0 cursor-pointer rounded-lg border border-ink-200 bg-white p-1 dark:border-white/10 dark:bg-white/5"
                />
                <Input aria-label={t('Nom')} className="min-w-0 flex-1 py-2 text-[13px]" value={name} onChange={(e) => setName(e.target.value)} maxLength={80} />
                <Input
                    aria-label={t('Ordre')}
                    type="number"
                    min={0}
                    max={999}
                    className="w-20 shrink-0 py-2 text-[13px]"
                    value={order}
                    onChange={(e) => setOrder(e.target.value)}
                />
                <button
                    type="submit"
                    title={t('Enregistrer')}
                    className="shrink-0 rounded-lg p-2 text-ink-400 transition hover:bg-ink-100 hover:text-brand-600 dark:hover:bg-white/10"
                >
                    <Icon name="check" className="h-4 w-4" />
                </button>
            </form>

            <div className="flex shrink-0 items-center justify-between gap-2 sm:justify-start">
                <span className="badge bg-ink-100 text-ink-600 dark:bg-white/8 dark:text-ink-300">
                    {choice(':count application|:count applications', category.applicationsCount ?? 0)}
                </span>
                <button
                    type="button"
                    title={t('Supprimer')}
                    onClick={() => {
                        if (confirm(t('Supprimer cette catégorie ?'))) router.delete(routes.admin.category(category.id), { preserveScroll: true });
                    }}
                    className="rounded-lg p-2 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                >
                    <Icon name="trash" className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}

export default function CategoriesIndex({ categories }: { categories: Category[] }) {
    const t = useT();
    const { data, setData, post, processing, reset } = useForm({ name: '', color: '#2563eb', sort_order: '0' });

    const add = (event: FormEvent) => {
        event.preventDefault();
        post(routes.admin.categories, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <AdminLayout title={t('Catégories')} subheading={t('Regroupent les applications sur le tableau de bord des employés.')}>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <Card className="divide-y divide-ink-100 dark:divide-white/10">
                    {categories.length === 0 && <p className="px-5 py-14 text-center text-sm text-ink-400">{t('Aucune catégorie.')}</p>}
                    {categories.map((category) => (
                        <Row key={category.id} category={category} />
                    ))}
                </Card>

                <Card className="h-fit p-5">
                    <form onSubmit={add}>
                        <h2 className="text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">{t('Ajouter une catégorie')}</h2>
                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="new-name" required>{t('Nom')}</Label>
                                <Input
                                    id="new-name"
                                    className="mt-2"
                                    placeholder={t('Ex. Finance')}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    maxLength={80}
                                    required
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="new-color">{t('Couleur')}</Label>
                                    <input
                                        id="new-color"
                                        type="color"
                                        value={data.color}
                                        onChange={(e) => setData('color', e.target.value)}
                                        className="mt-2 h-11 w-full cursor-pointer rounded-xl border border-ink-200 bg-white p-1 dark:border-white/10 dark:bg-white/5"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="new-order">{t('Ordre')}</Label>
                                    <Input
                                        id="new-order"
                                        type="number"
                                        min={0}
                                        max={999}
                                        className="mt-2"
                                        value={data.sort_order}
                                        onChange={(e) => setData('sort_order', e.target.value)}
                                    />
                                </div>
                            </div>
                            <button type="submit" disabled={processing} className="btn-primary w-full">
                                <Icon name="plus" className="h-4 w-4" />
                                {t('Ajouter')}
                            </button>
                        </div>
                    </form>
                </Card>
            </div>
        </AdminLayout>
    );
}
