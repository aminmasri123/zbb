// Sichere Datumskonvertierung
export function normalizeDate(value) {

    // Fall 1: Es ist ein echtes JS Date -> korrekt umwandeln
    if (value instanceof Date) {
        return new Date(
            value.getTime() - value.getTimezoneOffset() * 60000
        ).toISOString().slice(0, 10);
    }

    // Fall 2: Es ist ein String im Format "dd.mm.yyyy"
    if (typeof value === "string" && value.includes(".")) {
        const [day, month, year] = value.split(".");
        return `${year}-${month}-${day}`;
    }

    // Fall 3: Es ist schon im richtigen Format
    if (typeof value === "string" && value.match(/^\d{4}-\d{2}-\d{2}$/)) {
        return value;
    }

    // Fallback -> Fehler werfen
    throw new Error("Ungültiges Datum: " + value);
}
