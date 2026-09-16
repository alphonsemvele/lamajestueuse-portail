import type { PropsWithChildren } from 'react';
import { cn, useT } from '@/lib/utils';

/**
 * Libellé de champ. Convention unique dans tout le portail :
 * un astérisque rouge pour l'obligatoire, la mention « optionnel » sinon.
 */
export default function Label({
    htmlFor,
    required = false,
    className,
    children,
}: PropsWithChildren<{ htmlFor?: string; required?: boolean; className?: string }>) {
    const t = useT();

    return (
        <label htmlFor={htmlFor} className={cn('field-label', className)}>
            {children}
            {required ? (
                <span className="ml-0.5 text-red-500" aria-hidden="true">
                    *
                </span>
            ) : (
                <span className="ml-1.5 font-normal normal-case tracking-normal text-ink-400">({t('optionnel')})</span>
            )}
        </label>
    );
}
