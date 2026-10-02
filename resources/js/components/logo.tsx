import { cn } from '@/lib/utils';

const hauteurs = {
    sm: 'h-9',
    md: 'h-11',
    lg: 'h-14',
};

/**
 * Le logo du groupe.
 *
 * Deux formes : la marque seule — le livre, la toque et le sigle — pour les
 * en-têtes et les petits emplacements, et le logo complet avec la raison
 * sociale pour les pages d'accueil et de connexion.
 *
 * Les deux sont détourés sur fond transparent : ils se posent aussi bien sur
 * un fond clair que sombre, sans pastille ni cadre.
 */
export default function Logo({
    size = 'md',
    complet = false,
    className,
}: {
    size?: keyof typeof hauteurs;
    /** Avec la raison sociale sous la marque. */
    complet?: boolean;
    className?: string;
}) {
    return (
        <img
            src={complet ? '/images/logo-la-majestueuse.png' : '/images/marque-la-majestueuse.png'}
            alt="La Majestueuse"
            className={cn('w-auto shrink-0 object-contain', hauteurs[size], className)}
        />
    );
}
