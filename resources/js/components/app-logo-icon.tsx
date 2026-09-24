import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type Props = ImgHTMLAttributes<HTMLImageElement> & {
    lockup?: boolean;
};

export default function AppLogoIcon({
    className,
    alt = '',
    lockup = false,
    ...props
}: Props) {
    return (
        <img
            {...props}
            src={lockup ? '/brand/logo.png' : '/brand/mark.png'}
            alt={alt}
            draggable={false}
            className={cn('object-contain', className)}
        />
    );
}
