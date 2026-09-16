import { cn } from '@/lib/utils';

const sizes = {
    sm: 'h-9 w-9 text-[13px]',
    md: 'h-11 w-11 text-base',
    lg: 'h-14 w-14 text-xl',
};

export default function Logo({ size = 'md', className }: { size?: keyof typeof sizes; className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 via-brand-700 to-brand-900 font-semibold tracking-tight text-white shadow-lg shadow-brand-900/25 ring-1 ring-inset ring-white/15',
                sizes[size],
                className,
            )}
        >
            <span className="bg-linear-to-b from-white to-gold-300 bg-clip-text text-transparent">LM</span>
        </span>
    );
}
