import { cn } from '@/lib/utils';

interface Props {
    url?: string | null;
    initials: string;
    className?: string;
}

/** Photo de profil, avec repli sur les initiales. */
export default function Avatar({ url, initials, className }: Props) {
    const base = 'flex shrink-0 items-center justify-center overflow-hidden rounded-full';

    if (url) {
        return (
            <span className={cn(base, 'ring-1 ring-ink-900/10 dark:ring-white/15', className)}>
                <img src={url} alt="" className="h-full w-full object-cover" />
            </span>
        );
    }

    return (
        <span className={cn(base, 'bg-linear-to-br from-brand-600 to-brand-800 font-semibold text-white', className)}>
            {initials}
        </span>
    );
}
