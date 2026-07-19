const EXPONENTS: Record<string, number> = {
    KES: 2,
    BIF: 0,
    USD: 2,
};

export function currencyExponent(currency: string): number {
    return EXPONENTS[currency.toUpperCase()] ?? 2;
}

export function minorToMajor(minor: number, currency: string): number {
    const factor = 10 ** currencyExponent(currency);

    return minor / factor;
}

export function formatChartMoney(minor: number, currency: string): string {
    const major = minorToMajor(minor, currency);

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency.toUpperCase(),
        maximumFractionDigits: currencyExponent(currency),
    }).format(major);
}

export function compactChartMoney(minor: number, currency: string): string {
    const major = minorToMajor(minor, currency);

    return new Intl.NumberFormat(undefined, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(major);
}
