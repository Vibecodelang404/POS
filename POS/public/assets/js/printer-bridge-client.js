class PrinterBridgeClient {
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || localStorage.getItem('posPrinterBridgeUrl') || 'http://127.0.0.1:9123';
        this.timeoutMs = options.timeoutMs || 15000;
    }

    setBaseUrl(baseUrl) {
        this.baseUrl = String(baseUrl || '').replace(/\/+$/, '');
        localStorage.setItem('posPrinterBridgeUrl', this.baseUrl);
    }

    async status() {
        const response = await this.fetchWithTimeout(`${this.baseUrl}/status`, {
            method: 'GET'
        });

        if (!response.ok) {
            throw new Error(`Bridge status failed: HTTP ${response.status}`);
        }

        return response.json();
    }

    async printBytes(bytes) {
        const payload = this.bytesToBase64(bytes);
        const response = await this.fetchWithTimeout(`${this.baseUrl}/print`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ payload })
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.ok === false) {
            throw new Error(data.message || `Print failed: HTTP ${response.status}`);
        }

        return data;
    }

    fetchWithTimeout(url, options) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), this.timeoutMs);

        return fetch(url, {
            ...options,
            signal: controller.signal
        }).catch(error => {
            if (error.name === 'AbortError') {
                throw new Error(`Printer bridge did not respond within ${Math.round(this.timeoutMs / 1000)} seconds`);
            }

            throw error;
        }).finally(() => clearTimeout(timer));
    }

    bytesToBase64(bytes) {
        let binary = '';
        const chunkSize = 0x8000;
        const uint8 = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);

        for (let i = 0; i < uint8.length; i += chunkSize) {
            binary += String.fromCharCode.apply(null, uint8.subarray(i, i + chunkSize));
        }

        return btoa(binary);
    }
}

window.PrinterBridgeClient = PrinterBridgeClient;
