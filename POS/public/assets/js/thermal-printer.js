class ThermalPrinterReceipt {
    constructor(options = {}) {
        this.columns = options.columns || 32;
        this.encoder = new TextEncoder();
        this.bytes = [];
    }

    initialize() {
        this.raw([0x1b, 0x40]);
        return this;
    }

    align(position = 'left') {
        const value = position === 'center' ? 1 : (position === 'right' ? 2 : 0);
        this.raw([0x1b, 0x61, value]);
        return this;
    }

    bold(enabled = true) {
        this.raw([0x1b, 0x45, enabled ? 1 : 0]);
        return this;
    }

    size(width = 1, height = 1) {
        const widthValue = Math.max(1, Math.min(width, 8)) - 1;
        const heightValue = Math.max(1, Math.min(height, 8)) - 1;
        this.raw([0x1d, 0x21, (widthValue << 4) | heightValue]);
        return this;
    }

    text(value = '') {
        const mojibakePeso = String.fromCharCode(0x00E2, 0x201A, 0x00B1);
        const doubleMojibakePeso = String.fromCharCode(0x00C3, 0x00A2, 0x00E2, 0x20AC, 0x0161, 0x00C2, 0x00B1);
        const clean = String(value)
            .replaceAll(doubleMojibakePeso, 'PHP ')
            .replaceAll(mojibakePeso, 'PHP ')
            .replace(/₱/g, 'PHP ');
        this.raw(Array.from(this.encoder.encode(clean)));
        return this;
    }

    line(value = '') {
        this.text(value);
        this.raw([0x0a]);
        return this;
    }

    rule(char = '-') {
        return this.line(char.repeat(this.columns));
    }

    feed(lines = 1) {
        for (let i = 0; i < lines; i++) {
            this.raw([0x0a]);
        }
        return this;
    }

    cut() {
        this.feed(3);
        this.raw([0x1d, 0x56, 0x42, 0x00]);
        return this;
    }

    drawer() {
        this.raw([0x1b, 0x70, 0x00, 0x19, 0xfa]);
        return this;
    }

    pair(left, right) {
        const leftText = String(left);
        const rightText = String(right);
        const space = Math.max(1, this.columns - leftText.length - rightText.length);
        return this.line(leftText + ' '.repeat(space) + rightText);
    }

    item(name, quantity, unitPrice, total, type) {
        const cleanName = String(name || 'Item');
        const typeText = type ? ` (${type})` : '';
        this.line(this.truncate(cleanName + typeText, this.columns));
        this.pair(`${quantity} x ${this.money(unitPrice)}`, this.money(total));
        return this;
    }

    truncate(value, maxLength) {
        const text = String(value);
        return text.length > maxLength ? text.substring(0, maxLength - 1) + '.' : text;
    }

    money(value) {
        return `PHP ${Number(value || 0).toFixed(2)}`;
    }

    raw(values) {
        this.bytes.push(...values);
        return this;
    }

    toUint8Array() {
        return new Uint8Array(this.bytes);
    }
}

function buildThermalReceipt(orderData) {
    const receipt = new ThermalPrinterReceipt({ columns: 32 }).initialize();
    const items = orderData.items || [];
    const date = orderData.date || new Date().toLocaleString();

    receipt
        .align('center')
        .bold(true)
        .size(2, 1)
        .line(orderData.storeName || "Kakai's Kutkutin POS")
        .size(1, 1)
        .bold(false)
        .line('Official Receipt')
        .rule()
        .align('left')
        .pair('Sale #', orderData.orderNumber || 'N/A')
        .pair('Date', date)
        .pair('Cashier', orderData.cashier || 'Cashier')
        .pair('Payment', orderData.paymentMethod || 'Cash')
        .rule();

    items.forEach(item => {
        receipt.item(
            item.name,
            item.quantity,
            item.price,
            Number(item.price || 0) * Number(item.quantity || 0),
            item.product_type || item.type || ''
        );
    });

    receipt
        .rule()
        .pair('Subtotal', receipt.money(orderData.subtotal))
        .pair('Discount', '-' + receipt.money(orderData.discount))
        .pair('VATable', receipt.money(orderData.netSales))
        .pair('VAT 12%', receipt.money(orderData.tax))
        .bold(true)
        .pair('TOTAL', receipt.money(orderData.total))
        .bold(false)
        .pair('Paid', receipt.money(orderData.amountPaid))
        .pair('Change', receipt.money(orderData.change))
        .rule()
        .align('center')
        .line('Thank you, come again.')
        .cut();

    return receipt.toUint8Array();
}

window.ThermalPrinterReceipt = ThermalPrinterReceipt;
window.buildThermalReceipt = buildThermalReceipt;
