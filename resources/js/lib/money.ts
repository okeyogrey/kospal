const EXPONENTS: Record<string, number> = {
    KES: 2,
    BIF: 0,
    USD: 2,
};

const CURRENCY_LOCALES: Record<string, string> = {
    KES: 'en-KE',
    BIF: 'fr-BI',
    USD: 'en-US',
};

export function moneyExponent(currency: string): number {
    return EXPONENTS[currency.toUpperCase()] ?? 2;
}

export function formatMoney(
    minor: number,
    currency: string,
    locale?: string,
): string {
    const code = currency.toUpperCase();
    const exponent = moneyExponent(code);
    const amount = minor / 10 ** exponent;
    const resolvedLocale = locale ?? CURRENCY_LOCALES[code] ?? 'en';

    try {
        return new Intl.NumberFormat(resolvedLocale, {
            style: 'currency',
            currency: code,
            minimumFractionDigits: exponent,
            maximumFractionDigits: exponent,
        }).format(amount);
    } catch {
        return `${code} ${amount.toFixed(exponent)}`;
    }
}

export function fromMinor(minor: number, currency: string): string {
    const exponent = moneyExponent(currency);
    const amount = minor / 10 ** exponent;

    return exponent === 0 ? String(Math.trunc(amount)) : amount.toFixed(exponent);
}

export function toMinor(amount: string | number, currency: string): number {
    const exponent = moneyExponent(currency);
    const raw = typeof amount === 'number' ? String(amount) : amount.trim();

    if (raw === '' || Number.isNaN(Number(raw))) {
        return 0;
    }

    const [majorPart = '0', fractionPart = ''] = raw.replace(',', '.').split('.');
    const sign = raw.startsWith('-') ? -1 : 1;
    const major = Math.abs(Number.parseInt(majorPart || '0', 10) || 0);
    const fraction = (fractionPart + '0'.repeat(exponent))
        .slice(0, exponent)
        .padEnd(exponent, '0');

    return sign * (major * 10 ** exponent + Number.parseInt(fraction || '0', 10));
}
