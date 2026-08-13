import Swal from 'sweetalert2'

const escapeHtml = (value) => String(value ?? '')
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#039;')

const detailRow = (label, value) => {
  if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) {
    return ''
  }

  const display = Array.isArray(value) ? value.join(', ') : value

  return `<div style="margin-top:4px"><strong>${escapeHtml(label)}:</strong> ${escapeHtml(display)}</div>`
}

export async function confirmRoomOverlap(data) {
  const conflicts = Array.isArray(data?.conflicts) ? data.conflicts : []
  const first = conflicts[0] || {}
  const room = first.room || {}
  const roomLabel = room.location ? `${room.name} (${room.location})` : (room.name || 'Der ausgewählte Raum')

  const conflictCards = conflicts.map((conflict) => {
    const occupied = conflict.occupied_by || {}

    return `
      <div style="margin-top:12px;padding:12px;border:1px solid #f5b041;border-radius:8px;background:#fffaf0;text-align:left">
        <div style="font-weight:700;color:#172033">${escapeHtml(occupied.label || 'Vorhandene Belegung')}</div>
        ${detailRow('Überschneidung', conflict.overlap?.label)}
        ${detailRow('Betreuer / verantwortlich', occupied.supervisor)}
        ${detailRow('Bereich', occupied.area)}
        ${detailRow('Schule / Bezug', occupied.schools)}
        ${detailRow('Teilnehmer', occupied.participant_count)}
        ${detailRow('Gesamte Belegung', occupied.period_label)}
      </div>
    `
  }).join('')

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Raum bereits belegt',
    html: `
      <div style="text-align:left;color:#374151">
        <div>Der Raum <strong>${escapeHtml(roomLabel)}</strong> hat ${conflicts.length === 1 ? 'eine Überschneidung' : `${conflicts.length} Überschneidungen`}:</div>
        ${conflictCards}
        <div style="margin-top:16px;font-weight:700">Waren beide Gruppen tatsächlich gleichzeitig in diesem Raum?</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Ja, zusätzlich im Raum speichern',
    cancelButtonText: 'Anderen Raum wählen',
    confirmButtonColor: '#f28c00',
    cancelButtonColor: '#64748b',
    focusCancel: true,
    reverseButtons: true,
    width: 680,
  })

  return result.isConfirmed
}

export function showRequestError(error, fallbackMessage) {
  const data = error?.response?.data || {}
  const messages = Object.values(data.errors || {})
    .flatMap((value) => Array.isArray(value) ? value : [value])
    .filter(Boolean)
  const uniqueMessages = [...new Set(messages)]
  const displayMessages = uniqueMessages.length > 0
    ? uniqueMessages
    : [data.message || fallbackMessage]

  return Swal.fire({
    icon: 'error',
    title: 'Eingaben prüfen',
    html: `<div style="text-align:left">${displayMessages.map((message) => `<div style="margin-top:6px">• ${escapeHtml(message)}</div>`).join('')}</div>`,
    confirmButtonText: 'OK',
  })
}
