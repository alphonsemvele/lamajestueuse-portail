import { Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Spinner from '@/components/spinner';
import Label from '@/components/label';
import MultiSelect from '@/components/multi-select';
import PhotoField from '@/components/photo-field';
import { Card, Input, Select } from '@/components/ui';
import AdminLayout from '@/layouts/admin-layout';
import { routes, useT } from '@/lib/utils';
import type { Application, PortalUser } from '@/types';

interface Props {
    user: PortalUser | null;
    applications: Application[];
    assigned: Record<number, string[]>;
    postes: Record<number, string | null>;
}

interface UserFormData {
    name: string;
    lastname: string;
    email: string;
    matricule: string;
    phone: string;
    poste: string;
    entite: string;
    role: string;
    status: string;
    locale: string;
    password: string;
    password_confirmation: string;
    avatar: string;
    avatar_file: File | null;
    remove_avatar: boolean;
    applications: number[];
    roles: Record<number, string[]>;
}

export default function UserForm({ user, applications, assigned, postes }: Props) {
    const t = useT();
    const editing = user !== null;

    const { data, setData, post, processing, errors } = useForm<UserFormData>({
        name: user?.name ?? '',
        lastname: user?.lastname ?? '',
        email: user?.email ?? '',
        matricule: user?.matricule ?? '',
        phone: user?.phone ?? '',
        poste: user?.poste ?? '',
        entite: user?.entite ?? '',
        role: user?.role ?? 'employee',
        status: user?.status ?? 'active',
        locale: user?.locale ?? 'fr',
        password: '',
        password_confirmation: '',
        avatar: '',
        avatar_file: null,
        remove_avatar: false,
        applications: Object.keys(assigned).map(Number),
        roles: Object.fromEntries(Object.entries(assigned).map(([id, roles]) => [Number(id), roles ?? []])),
    });

    const selected = data.applications;

    const toggle = (id: number) =>
        setData('applications', selected.includes(id) ? selected.filter((value) => value !== id) : [...selected, id]);

    // Attribuer un rôle dans une application y donne aussi l'accès.
    const setRoles = (id: number, roles: string[]) =>
        setData((current) => ({
            ...current,
            roles: { ...current.roles, [id]: roles },
            applications: roles.length > 0 && !current.applications.includes(id) ? [...current.applications, id] : current.applications,
        }));

    const [photoPreview, setPhotoPreview] = useState<string | null>(user?.avatarUrl ?? null);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Une photo peut accompagner l'envoi : Inertia doit passer en FormData,
        // et une modification transite alors par POST avec _method.
        const options = { forceFormData: true } as const;

        if (editing) {
            router.post(routes.admin.user(user.id), { ...data, _method: 'put' }, options);
        } else {
            post(routes.admin.users, options);
        }
    };

    type TextKey = 'name' | 'lastname' | 'email' | 'matricule' | 'phone' | 'poste' | 'entite';

    const field = (key: TextKey, label: string, required = false, extra: Record<string, unknown> = {}) => (
        <div>
            <Label htmlFor={key} required={required}>
                {label}
            </Label>
            <Input id={key} className="mt-2" value={data[key] ?? ''} onChange={(e) => setData(key, e.target.value)} {...extra} />
            {errors[key] && <p className="mt-1.5 text-xs text-red-600">{errors[key]}</p>}
        </div>
    );

    return (
        <AdminLayout
            title={editing ? t('Modifier un employé') : t('Nouvel employé')}
            heading={editing ? user.fullName : t('Nouvel employé')}
            subheading={t('Identité, rôle dans le portail et applications accessibles.')}
        >
            <form onSubmit={submit} className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="space-y-6">
                    <Card className="p-6">
                        <h2 className="text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">{t('Identité')}</h2>

                        <div className="mt-5">
                            <Label>{t('Photo de profil')}</Label>
                            <div className="mt-2">
                                <PhotoField
                                    preview={photoPreview}
                                    initials={user?.initials}
                                    error={errors.avatar_file}
                                    onPick={(file) => {
                                        setData((current) => ({ ...current, avatar_file: file, remove_avatar: false }));
                                        setPhotoPreview(URL.createObjectURL(file));
                                    }}
                                    onDrop={() => {
                                        setData((current) => ({ ...current, avatar_file: null, remove_avatar: true, avatar: '' }));
                                        setPhotoPreview(null);
                                    }}
                                />
                            </div>
                        </div>

                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            {field('name', t('Prénom'), true, { maxLength: 80, required: true })}
                            {field('lastname', t('Nom de famille'), false, { maxLength: 80 })}
                            {field('email', t('Adresse professionnelle'), false, { type: 'email', maxLength: 150 })}
                            {field('matricule', t('Matricule'), false, { maxLength: 40, className: 'mt-2 font-mono text-[13px]' })}
                            {field('phone', t('Téléphone'), false, { maxLength: 40 })}
                            {field('poste', t('Poste'), false, { maxLength: 120 })}
                            <div className="sm:col-span-2">
                                {field('entite', t('Entité de rattachement'), false, { maxLength: 120, placeholder: t('IUM, IFPM, GSBM…') })}
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <h2 className="text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">{t('Accès aux applications')}</h2>
                        <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">
                            {t('Le portail décide quelles applications s’affichent. Les rôles choisis sont transmis à l’application au moment de la connexion : ils y déterminent les menus et les pages accessibles.')}
                        </p>

                        <div className="mt-5 space-y-2.5">
                            {applications.map((application) => {
                                const options = application.roleCatalogue.map((role) => ({ value: role.code, label: role.libelle, description: role.description }));
                                const error = errors[`roles.${application.id}` as keyof typeof errors];

                                return (
                                    <div
                                        key={application.id}
                                        className="flex flex-wrap items-center gap-3 rounded-xl border border-ink-200 px-4 py-3 transition hover:border-ink-300 dark:border-white/10"
                                    >
                                        <label className="flex min-w-[200px] flex-1 cursor-pointer items-center gap-3">
                                            <input
                                                type="checkbox"
                                                checked={selected.includes(application.id)}
                                                onChange={() => toggle(application.id)}
                                                className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                            />
                                            <span
                                                className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg"
                                                style={{ color: application.color, background: `${application.color}14` }}
                                            >
                                                {application.logoUrl ? (
                                                    <img src={application.logoUrl} alt="" className="h-full w-full object-contain p-0.5" />
                                                ) : (
                                                    <Icon name={application.icon} className="h-4 w-4" />
                                                )}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-sm font-medium text-ink-900 dark:text-white">{application.name}</span>
                                                {postes[application.id] ? (
                                                    <span className="block text-[11px] text-ink-500 dark:text-ink-400">
                                                        {t('Poste :')} {postes[application.id]}
                                                    </span>
                                                ) : (
                                                    <span className="block truncate font-mono text-[11px] text-ink-400">{application.host}</span>
                                                )}
                                            </span>
                                        </label>
                                        <div className="w-full sm:w-64">
                                            {options.length > 0 ? (
                                                <MultiSelect
                                                    options={options}
                                                    value={data.roles[application.id] ?? []}
                                                    onChange={(roles) => setRoles(application.id, roles)}
                                                    placeholder={t('aucun rôle')}
                                                />
                                            ) : (
                                                <Input
                                                    className="py-2 text-[13px]"
                                                    placeholder={t('rôles, séparés par des virgules')}
                                                    maxLength={300}
                                                    value={(data.roles[application.id] ?? []).join(', ')}
                                                    onChange={(e) =>
                                                        setRoles(
                                                            application.id,
                                                            e.target.value.split(',').map((role) => role.trimStart()).filter((role, index, all) => role !== '' || index === all.length - 1),
                                                        )
                                                    }
                                                />
                                            )}
                                            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </Card>
                </div>

                <div className="space-y-4 xl:sticky xl:top-24 xl:self-start">
                    <Card className="p-5">
                        <h2 className="text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">{t('Portail')}</h2>
                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="role" required>
                                    {t('Rôle dans le portail')}
                                </Label>
                                <Select id="role" className="mt-2" value={data.role} onChange={(e) => setData('role', e.target.value)}>
                                    <option value="employee">{t('Employé')}</option>
                                    <option value="manager">{t('Responsable')}</option>
                                    <option value="admin">{t('Administrateur')}</option>
                                </Select>
                            </div>
                            <div>
                                <Label htmlFor="status" required>
                                    {t('Statut du compte')}
                                </Label>
                                <Select id="status" className="mt-2" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                    <option value="active">{t('Actif')}</option>
                                    <option value="pending">{t('En attente')}</option>
                                    <option value="suspended">{t('Suspendu')}</option>
                                </Select>
                                <p className="mt-1.5 text-xs text-ink-400">{t('Un compte suspendu perd l’accès à toutes les applications.')}</p>
                            </div>
                            <div>
                                <Label htmlFor="locale" required>
                                    {t('Langue')}
                                </Label>
                                <Select id="locale" className="mt-2" value={data.locale} onChange={(e) => setData('locale', e.target.value)}>
                                    <option value="fr">{t('Français')}</option>
                                    <option value="en">{t('Anglais')}</option>
                                </Select>
                            </div>
                        </div>
                    </Card>

                    <Card className="p-5">
                        <div className="flex items-baseline gap-2">
                            <h2 className="text-sm font-semibold uppercase tracking-[0.09em] text-ink-500">{t('Mot de passe')}</h2>
                            {editing ? (
                                <span className="text-xs text-ink-400">({t('optionnel')})</span>
                            ) : (
                                <span className="text-red-500" aria-hidden="true">*</span>
                            )}
                        </div>
                        <p className="mt-1.5 text-xs text-ink-400">
                            {editing ? t('Laissez vide pour conserver le mot de passe actuel.') : t('8 caractères minimum.')}
                        </p>
                        <div className="mt-4 space-y-3">
                            <Input
                                type="password"
                                autoComplete="new-password"
                                placeholder={t('Mot de passe')}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            <Input
                                type="password"
                                autoComplete="new-password"
                                placeholder={t('Confirmation')}
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                            />
                            {errors.password && <p className="text-xs text-red-600">{errors.password}</p>}
                        </div>
                    </Card>

                    <Card className="space-y-2.5 p-5">
                        <button type="submit" disabled={processing} className="btn-primary w-full">
                            {processing ? <Spinner /> : <Icon name="check" className="h-4 w-4" />}
                            {processing ? t('Enregistrement…') : editing ? t('Enregistrer') : t('Créer le compte')}
                        </button>
                        <Link href={routes.admin.users} className="btn-ghost w-full">
                            {t('Annuler')}
                        </Link>
                    </Card>
                </div>
            </form>
        </AdminLayout>
    );
}
