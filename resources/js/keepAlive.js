const SESSION_CHECK_INTERVAL_MS = 3 * 60_000;

let intervalId = null;
let requestRunning = false;
let redirecting = false;

const sessionStatusUrl = () => window.asset('system/session-status');

export const redirectAfterSessionExpiry = () => {
    if (redirecting) return;

    redirecting = true;
    window.__zbbSessionExpired = true;
    window.dispatchEvent(new CustomEvent('zbb:session-expired'));
    window.alert('Ihre Sitzung ist abgelaufen. Nicht bestätigte Änderungen konnten nicht gespeichert werden.');
    window.location.replace(window.asset(''));
};

export const checkAuthenticatedSession = async ({ redirect = true } = {}) => {
    if (!navigator.onLine) return false;

    try {
        const response = await fetch(`${sessionStatusUrl()}?t=${Date.now()}`, {
            method: 'GET',
            credentials: 'same-origin',
            redirect: 'manual',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if ([401, 419].includes(response.status) || response.type === 'opaqueredirect') {
            if (redirect) redirectAfterSessionExpiry();
            return false;
        }

        return response.ok;
    } catch {
        return false;
    }
};

export const pingBackend = async () => {
    if (requestRunning || document.hidden) return;

    requestRunning = true;
    try {
        await checkAuthenticatedSession();
    } finally {
        requestRunning = false;
    }
};

export const startBackendKeepAlive = () => {
    if (intervalId !== null) return;

    pingBackend();
    intervalId = window.setInterval(pingBackend, SESSION_CHECK_INTERVAL_MS);

    window.addEventListener('focus', pingBackend);
    window.addEventListener('online', pingBackend);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) pingBackend();
    });
};
