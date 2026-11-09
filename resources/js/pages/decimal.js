/**
 * Minimal exact decimal helpers using BigInt (display previews only).
 */
function parse(value) {
    const [whole, frac = ''] = String(value).trim().split('.');
    const negative = whole.startsWith('-');
    return { digits: BigInt((whole.replace('-', '') || '0') + frac), scale: frac.length, negative };
}

function toString(digits, scale, negative) {
    let s = digits.toString().padStart(scale + 1, '0');
    if (scale) s = `${s.slice(0, -scale)}.${s.slice(-scale)}`;
    return (negative && digits !== 0n ? '-' : '') + s;
}

function round(value, scale) {
    const { digits, scale: s, negative } = parse(value);
    if (s <= scale) return toString(digits * 10n ** BigInt(scale - s), scale, negative);
    const factor = 10n ** BigInt(s - scale);
    let q = digits / factor;
    if ((digits % factor) * 2n >= factor) q += 1n;
    return toString(q, scale, negative);
}

export const Decimal = {
    product(values, scale = 2) {
        let digits = 1n;
        let totalScale = 0;
        let negative = false;
        values.forEach((v) => {
            const p = parse(v || '0');
            digits *= p.digits;
            totalScale += p.scale;
            negative = negative !== p.negative;
        });
        return round(toString(digits, totalScale, negative), scale);
    },
    sum(values, scale = 2) {
        const maxScale = Math.max(0, ...values.map((v) => parse(v || '0').scale));
        let total = 0n;
        values.forEach((v) => {
            const p = parse(v || '0');
            const d = p.digits * 10n ** BigInt(maxScale - p.scale);
            total += p.negative ? -d : d;
        });
        const negative = total < 0n;
        return round(toString(negative ? -total : total, maxScale, negative), scale);
    },
    format(value) {
        const [whole, frac = '00'] = round(value, 2).split('.');
        const negative = whole.startsWith('-');
        const w = whole.replace('-', '');
        const last3 = w.slice(-3);
        const rest = w.slice(0, -3);
        const grouped = rest ? `${rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',')},${last3}` : last3;
        return `${negative ? '-' : ''}${grouped}${frac === '00' ? '' : `.${frac}`}`;
    },
};
