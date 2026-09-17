// Round in cents, matching PurchaseWorkflow, including gross prices and quantities.
export const cents = value => Math.round((Number(value) || 0) * 100 + 1e-7)
export function priceAmounts(price, quantity, tax, mode = 'netto') {
    const value = cents(price) * (Number(quantity) || 0)
    const rate = cents(tax)
    const net = mode === 'brutto' ? Math.round(value * 10000 / (10000 + rate)) : value
    const gross = mode === 'brutto' ? value : value + Math.round(value * rate / 10000)
    return { net: net / 100, tax: (gross - net) / 100, gross: gross / 100 }
}
export function purchaseTotals(lines, shipping = 0, shippingTax = 19, mode = 'netto') {
    const delivery = priceAmounts(shipping, 1, shippingTax, mode)
    let net = cents(delivery.net), gross = cents(delivery.gross)
    for (const line of lines) {
        const amount = priceAmounts(line.einzelpreis, line.stueck, line.mwst, mode)
        net += cents(amount.net)
        gross += cents(amount.gross)
    }
    return { net: net / 100, tax: (gross - net) / 100, gross: gross / 100 }
}
export function inputPrice(item, mode = 'netto') {
    return mode === 'brutto'
        ? Number(item.einzelpreis_brutto ?? priceAmounts(item.einzelpreis, 1, item.mwst).gross)
        : Number(item.einzelpreis)
}
