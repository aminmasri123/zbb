import { ref } from 'vue';

const SESSION_CHECK_INTERVAL_MS = 3 * 60_000;
const ACTIVITY_THROTTLE_MS = 60_000;
const EXPIRY_WARNING_SECONDS = 5 * 60;

let sessionCheckTimer = null;
let countdownTimer = null;
let requestRunning = false;
let redirecting = false;
let expiresAtMs = null;
let lastActivitySentAt = 0;
let warningShown = false;

export const sessionRemainingSeconds = ref(null);
export const sessionLifetimeSeconds = ref(30 * 60);

const sessionStatusUrl = () => window.asset('system/session-status');
const sessionActivityUrl = () => window.asset('system/session-activity');

const applySessionPayload = (payload = {}) => {
    sessionLifetimeSeconds.value = Number(payload.lifetime_seconds || sessionLifetimeSeconds.value);
    expiresAtMs = Number(payload.expires_at || 0) * 1000 || null;

    if (expiresAtMs) {
        sessionRemainingSeconds.value = Math.max(0, Math.ceil((expiresAtMs - Date.now()) / 1000));
    }

    if ((sessionRemainingSeconds.value ?? 0) > EXPIRY_WARNING_SECONDS) warningShown = false;
};

export const redirectAfterSessionExpiry = () => {
    if (redirecting) return;

    redirecting = true;
    window.__zbbSessionExpired = true;
    window.dispatchEvent(new CustomEvent('zbb:session-expired'));
    window.alert('Ihre Sitzung ist wegen Inaktivität abgelaufen. Nicht bestätigte Änderungen konnten nicht gespeichert werden.');
    window.location.replace(window.asset(''));
};

const updateCountdown = () => {
    if (!expiresAtMs || redirecting) return;

    const remaining = Math.max(0, Math.ceil((expiresAtMs - Date.now()) / 1000));
    sessionRemainingSeconds.value = remaining;

    if (remaining <= 0) {
        redirectAfterSessionExpiry();
        return;
    }

    if (remaining <= EXPIRY_WARNING_SECONDS && !warningShown) {
        warningShown = true;
        window.alert('Ihre Sitzung läuft in 5 Minuten ab. Bewegen Sie die Maus, klicken Sie oder drücken Sie eine Taste, wenn Sie weiterarbeiten.');
    }
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

        if (!response.ok) return false;

        applySessionPayload(await response.json());
        return true;
    } catch {
        return false;
    }
};

export const recordAuthenticatedActivity = async () => {
    if (redirecting || !navigator.onLine) return;

    const now = Date.now();
    if ((now - lastActivitySentAt) < ACTIVITY_THROTTLE_MS) return;
    lastActivitySentAt = now;

    try {
        const response = await window.axios.post(sessionActivityUrl(), {}, {
            headers: { Accept: 'application/json' },
        });
        applySessionPayload(response.data);
    } catch {
        // 401/419 behandelt der globale Axios-Interceptor. Kurze Netzfehler
        // verlängern die Sitzung bewusst nicht.
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

const activityEvents = ['pointerdown', 'keydown', 'touchstart', 'scroll'];

export const startBackendKeepAlive = () => {
    if (sessionCheckTimer !== null) return;

    pingBackend();
    sessionCheckTimer = window.setInterval(pingBackend, SESSION_CHECK_INTERVAL_MS);
    countdownTimer = window.setInterval(updateCountdown, 1000);

    activityEvents.forEach((eventName) => {
        window.addEventListener(eventName, recordAuthenticatedActivity, { passive: true });
    });

    window.addEventListener('focus', pingBackend);
    window.addEventListener('online', pingBackend);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) pingBackend();
    });
};
