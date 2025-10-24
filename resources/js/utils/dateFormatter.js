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

