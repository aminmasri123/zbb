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

function toYMD(d) {
    if (!d) return null;

    if (d instanceof Date) {
        return d.toISOString().slice(0, 10);  // => YYYY-MM-DD
    }

    if (typeof d === "string" && d.includes("T")) {
        return d.slice(0, 10);                // ISO kürzen
    }

    return d;
}
