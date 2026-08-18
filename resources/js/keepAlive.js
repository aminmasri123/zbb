const KEEPALIVE_INTERVAL_MS = 45_000;
const RETRY_INTERVAL_MS = 5_000;

let intervalId = null;
let retryId = null;
let requestRunning = false;

const keepaliveUrl = () => window.asset('system/keepalive');

export const pingBackend = async () => {
    if (requestRunning || !navigator.onLine) {
        return;
    }

    requestRunning = true;

    try {
        const response = await fetch(`${keepaliveUrl()}?t=${Date.now()}`, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Keepalive antwortete mit HTTP ${response.status}`);
        }

        if (retryId !== null) {
            window.clearInterval(retryId);
            retryId = null;
        }
    } catch (error) {
        // Bei einem kurzen PHP-FPM-Neustart alle fünf Sekunden erneut prüfen.
        // Es erscheint absichtlich keine Meldung, damit die Arbeit nicht gestört wird.
        if (retryId === null) {
            retryId = window.setInterval(pingBackend, RETRY_INTERVAL_MS);
        }
    } finally {
        requestRunning = false;
    }
};

export const startBackendKeepAlive = () => {
    if (intervalId !== null) {
        return;
    }

    pingBackend();
    intervalId = window.setInterval(pingBackend, KEEPALIVE_INTERVAL_MS);

    window.addEventListener('focus', pingBackend);
    window.addEventListener('online', pingBackend);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pingBackend();
        }
    });
};
