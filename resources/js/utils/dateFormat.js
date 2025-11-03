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
