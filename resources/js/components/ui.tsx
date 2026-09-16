import type { InputHTMLAttributes, PropsWithChildren, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import Icon from '@/components/icon';
import { cn } from '@/lib/utils';

export function Card({ className, children }: PropsWithChildren<{ className?: string }>) {
    return <div className={cn('card', className)}>{children}</div>;
}

export function Input({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
    return <input className={cn('field-input', className)} {...props} />;
}

export function Textarea({ className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea className={cn('field-input', className)} {...props} />;
}

export function Select({ className, children, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select className={cn('field-input', className)} {...props}>
            {children}
        </select>
    );
}

/** Message d'erreur de validation sous un champ. */

export function Alert({
    tone = 'info',
    icon = 'alert',
    children,
}: PropsWithChildren<{ tone?: 'info' | 'success' | 'danger' | 'warning'; icon?: string }>) {
    const tones = {
        info: 'border-brand-200 bg-brand-50 text-brand-800 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-200',
        success:
            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-200',
        danger: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300',
        warning:
            'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
    };

    return (
        <div className={cn('flex items-start gap-3 rounded-xl border px-4 py-3 text-sm', tones[tone])}>
            <Icon name={icon} className="mt-0.5 h-4 w-4 shrink-0" />
            <div className="flex-1">{children}</div>
        </div>
    );
}

/** Liste des erreurs de validation, en tete de formulaire. */
export function ErrorSummary({ errors, title }: { errors: Record<string, string>; title: string }) {
    const messages = Object.values(errors);

    if (messages.length === 0) return null;

    return (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300">
            <p className="flex items-center gap-2 font-semibold">
                <Icon name="alert" className="h-4 w-4" />
                {title}
            </p>
            <ul className="mt-1.5 list-inside list-disc space-y-0.5 pl-1">
                {messages.map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
        </div>
    );
}
