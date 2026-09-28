import { ref } from 'vue';

const SESSION_CHECK_INTERVAL_MS = 3 * 60_000;
const ACTIVITY_THROTTLE_MS = 60_000;
const EXPIRY_WARNING_SECONDS = 5 * 60;
const SESSION_REQUEST_TIMEOUT_MS = 10_000;

let sessionCheckTimer = null;
let countdownTimer = null;
let requestRunning = false;
let redirecting = false;
let expiresAtMs = null;
let lastActivitySentAt = 0;
let activityRequestRunning = false;
let activityRevision = 0;
let expiryCheckRunning = false;

export const sessionRemainingSeconds = ref(null);
export const sessionLifetimeSeconds = ref(30 * 60);
export const sessionWarningVisible = ref(false);

const sessionStatusUrl = () => window.asset('system/session-status');
const sessionActivityUrl = () => window.asset('system/session-activity');

const applySessionPayload = (payload = {}) => {
    sessionLifetimeSeconds.value = Number(payload.lifetime_seconds || sessionLifetimeSeconds.value);
    const serverRemainingSeconds = Math.max(0, Number(payload.remaining_seconds || 0));

    // Der Server liefert die Restdauer bereits fertig berechnet. Daraus wird
    // eine lokale Deadline gebildet, damit unterschiedliche Server-/PC-Uhren
    // oder Zeitzonen den angezeigten Countdown nicht verfälschen.
    sessionRemainingSeconds.value = serverRemainingSeconds;
    expiresAtMs = Date.now() + (serverRemainingSeconds * 1000);

    if ((sessionRemainingSeconds.value ?? 0) > EXPIRY_WARNING_SECONDS) {
        sessionWarningVisible.value = false;
    }
};

export const redirectAfterSessionExpiry = () => {
    if (redirecting) return;

    redirecting = true;
    window.__zbbSessionExpired = true;
    sessionWarningVisible.value = false;
    window.dispatchEvent(new CustomEvent('zbb:session-expired'));
    window.location.replace(window.asset(''));
};

const updateCountdown = () => {
    if (!expiresAtMs || redirecting) return;

    const remaining = Math.max(0, Math.ceil((expiresAtMs - Date.now()) / 1000));
    sessionRemainingSeconds.value = remaining;

    if (remaining <= 0) {
        // Another tab or an in-flight activity request may have renewed the
        // session. Only the server can confirm that it has really expired.
        if (!expiryCheckRunning && !activityRequestRunning) {
            expiryCheckRunning = true;
            checkAuthenticatedSession().then((authenticated) => {
                if (authenticated && !activityRequestRunning && sessionRemainingSeconds.value <= 0) redirectAfterSessionExpiry();
            }).finally(() => { expiryCheckRunning = false; });
        }
        return;
    }

    if (remaining <= EXPIRY_WARNING_SECONDS) sessionWarningVisible.value = true;
};

export const checkAuthenticatedSession = async ({ redirect = true } = {}) => {
    if (!navigator.onLine) return false;

    const revision = activityRevision;
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), SESSION_REQUEST_TIMEOUT_MS);

    try {
        const response = await fetch(`${sessionStatusUrl()}?t=${Date.now()}`, {
            method: 'GET',
            credentials: 'same-origin',
            redirect: 'manual',
            cache: 'no-store',
            signal: controller.signal,
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

        const payload = await response.json();
        if (revision === activityRevision && !activityRequestRunning) applySessionPayload(payload);
        return true;
    } catch {
        return false;
    } finally {
        window.clearTimeout(timeoutId);
    }
};

export const recordAuthenticatedActivity = async ({ force = false } = {}) => {
    if (redirecting || !navigator.onLine || activityRequestRunning) return;

    const now = Date.now();
    if (!force && (now - lastActivitySentAt) < ACTIVITY_THROTTLE_MS) return;
    lastActivitySentAt = now;
    activityRequestRunning = true;
    activityRevision++;

    try {
        const response = await window.axios.post(sessionActivityUrl(), {}, {
            headers: { Accept: 'application/json' },
            timeout: SESSION_REQUEST_TIMEOUT_MS,
        });
        applySessionPayload(response.data);
    } catch {
        lastActivitySentAt = 0;
        // 401/419 behandelt der globale Axios-Interceptor. Kurze Netzfehler
        // verlängern die Sitzung bewusst nicht.
    } finally {
        activityRequestRunning = false;
        activityRevision++;
    }
};

export const continueAuthenticatedSession = async () => {
    await recordAuthenticatedActivity({ force: true });
};

export const endAuthenticatedSession = async () => {
    if (redirecting) return;

    redirecting = true;
    window.__zbbSessionExpired = true;
    sessionWarningVisible.value = false;

    try {
        await window.axios.post(window.route('logout'));
    } catch {
        // Auch wenn der Server bereits abgemeldet hat oder nicht erreichbar ist,
        // wird die geschützte Arbeitsmaske sofort verlassen.
    } finally {
        window.location.replace(window.asset(''));
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

const activityEvents = ['pointerdown', 'keydown', 'touchstart', 'scroll', 'input', 'change'];

const handleUserActivity = (event) => {
    // Keep the warning's explicit yes/no buttons usable. Activity in the
    // working form still counts, including events stopped by UI components.
    if (!event.isTrusted || event.target?.closest?.('[aria-labelledby="session-warning-title"]')) return;
    recordAuthenticatedActivity();
};

export const startBackendKeepAlive = () => {
    if (sessionCheckTimer !== null) return;

    pingBackend();
    sessionCheckTimer = window.setInterval(pingBackend, SESSION_CHECK_INTERVAL_MS);
    countdownTimer = window.setInterval(updateCountdown, 1000);

    activityEvents.forEach((eventName) => {
        window.addEventListener(eventName, handleUserActivity, { passive: true, capture: true });
    });

    window.addEventListener('focus', pingBackend);
    window.addEventListener('online', pingBackend);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) pingBackend();
    });
};
