    export function formatDate(value) {
        if (!value) return '-'

        const date = new Date(value)
        if (isNaN(date)) return '-'

        return date.toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        })
    }

    export function formatDateTime(value) {
        if (!value) return '-'

        const date = new Date(value)
        if (isNaN(date)) return '-'

        return date.toLocaleString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
    }

    export function normalizeToDate(d) {
        if (!d) return null;

        // Wenn Date → zurückgeben
        if (d instanceof Date) return d;

        // Wenn kein String → abbrechen
        if (typeof d !== "string") return null;

        // yyyy-mm-dd
        if (/^\d{4}-\d{2}-\d{2}$/.test(d)) {
            return new Date(`${d}T12:00:00`);
        }

        // dd.mm.yyyy
        if (/^\d{2}\.\d{2}\.\d{4}$/.test(d)) {
            const [dd, mm, yyyy] = d.split(".");
            return new Date(`${yyyy}-${mm}-${dd}T12:00:00`);
        }

        // Fallback
        const parsed = new Date(d);
        if (isNaN(parsed)) return null;

        return new Date(
            parsed.getFullYear(),
            parsed.getMonth(),
            parsed.getDate(),
            12, 0, 0
        );
    }


    export function toLocalDateString(d) {
    if (!d) return null;

    // Wenn schon korrektes Format YYYY-MM-DD → direkt zurückgeben
    if (typeof d === "string" && /^\d{4}-\d{2}-\d{2}$/.test(d)) {
        return d;
    }

    // Falls deutsches Format dd.mm.yyyy → korrekt umwandeln
    if (typeof d === "string" && /^\d{2}\.\d{2}\.\d{4}$/.test(d)) {
        const [tag, monat, jahr] = d.split(".");
        return `${jahr}-${monat}-${tag}`;
    }

    // Falls Date-Objekt → konvertieren
    if (d instanceof Date && !isNaN(d)) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, "0");
        const day = String(d.getDate()).padStart(2, "0");
        return `${y}-${m}-${day}`;
    }

    // Wenn alles andere schiefgeht
    return null;
}
