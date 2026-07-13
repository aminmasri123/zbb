// resources/js/utils/arbeitszeit.js

export function timeToMinutes(time) {
    if (!time) return null;
    const [h, m] = time.split(":").map(Number);
    return h * 60 + m;
}

export function istAnwesend(gruppe) {
    return gruppe?.status?.status?.trim().toLocaleLowerCase('de-DE') === 'anwesend';
}

export function berechneAbweichungMinuten(gruppe) {
    const geplantStart = gruppe?.zeitgeplant?.startzeit;
    const geplantEnde  = gruppe?.zeitgeplant?.endzeit;
    const istStart     = gruppe?.zeittatsaechlich?.startzeit;
    const istEnde      = gruppe?.zeittatsaechlich?.endzeit;

    if (!geplantStart || !geplantEnde) return null;

    const soll = timeToMinutes(geplantEnde) - timeToMinutes(geplantStart);

    // Abwesenheitsstatus (z. B. krank, entschuldigt oder unentschuldigt)
    // entsprechen keiner tatsächlich geleisteten Arbeitszeit. Beim Anlegen
    // eines Gruppentages werden technisch zunächst Sollzeiten als Istzeiten
    // hinterlegt; diese dürfen die Auswertung nicht als Anwesenheit zählen.
    if (gruppe?.status && !istAnwesend(gruppe)) {
        return -soll;
    }

    if (!istStart || !istEnde) return null;

    const ist  = timeToMinutes(istEnde) - timeToMinutes(istStart);

    return ist - soll;
}

export function formatMinutes(min) {
    if (min === null) return "-";
    const sign = min < 0 ? "-" : min > 0 ? "+" : '';
    min = Math.abs(min);
    const h = Math.floor(min / 60);
    const m = min % 60;
    return `${sign}${h.toString().padStart(2, "0")}:${m.toString().padStart(2, "0")}`;
}

export function abweichungsIcon(min) {
    if (min === null) return "❓";
    if (min < 0) return "🔻";
    if (min > 0) return "🔺 ";
    return "✅";
}

export function abweichungsClass(min) {
    if (min === null) return "text-gray-400";
    if (min < 0) return "text-red-600";
    if (min > 0) return "text-green-600";
    return "text-gray-600";
}
