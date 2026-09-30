import { type InputHTMLAttributes, useId, useState } from 'react';
import Icon from '@/components/icon';
import { cn, useT } from '@/lib/utils';

type Props = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>;

/**
 * Champ de mot de passe avec l'œil qui le dévoile.
 *
 * Un mot de passe qu'on ne peut pas relire se saisit deux fois de travers ;
 * l'œil vaut surtout quand on en attribue un à quelqu'un d'autre et qu'il
 * faut le lui dicter.
 */
export default function ChampMotDePasse({ className, ...props }: Props) {
    const t = useT();
    const [devoile, setDevoile] = useState(false);
    const identifiantParDefaut = useId();

    return (
        <div className="relative">
            <input
                {...props}
                id={props.id ?? identifiantParDefaut}
                type={devoile ? 'text' : 'password'}
                className={cn('field-input pr-11', className)}
            />

            <button
                type="button"
                onClick={() => setDevoile((montre) => !montre)}
                aria-label={devoile ? t('Masquer le mot de passe') : t('Afficher le mot de passe')}
                aria-pressed={devoile}
                title={devoile ? t('Masquer') : t('Afficher')}
                className="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-ink-400 transition hover:text-ink-700 dark:hover:text-white"
            >
                <Icon name={devoile ? 'eye-off' : 'eye'} className="h-4 w-4" />
            </button>
        </div>
    );
}
