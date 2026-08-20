const participantId = (participant) => participant?.person_id || participant?.id || null

const dayKind = (day) => {
  if (day?.type === 'preparation') return 'preparation'
  if (day?.type === 'feedback') return 'feedback'

  return 'pa_day'
}

const signatureDayKind = (dayId) => {
  const normalized = String(dayId || '').toLocaleLowerCase('de-DE')

  if (normalized.includes('vorbereitung') || normalized.includes('preparation')) return 'preparation'
  if (normalized.includes('feedback')) return 'feedback'

  return 'pa_day'
}

/**
 * Resolves an already stored PA signature even when a regenerated schedule uses
 * another internal day id for the same date. Existing keys stay untouched so
 * their audit/version history continues under the original subject.
 */
export const resolvePaSignatureKey = ({ day, participant, signatures, preferredDayId }) => {
  const personId = participantId(participant)
  const directKey = `${preferredDayId || day?.id}:${personId}`

  if (!personId || !day) return directKey
  if (signatures?.[directKey]) return directKey

  const suffix = `:${personId}`
  const date = String(day.date || '')
  const kind = dayKind(day)
  const matchingKey = Object.keys(signatures || {})
    .filter((key) => Boolean(signatures[key]) && key.endsWith(suffix))
    .find((key) => {
      const storedDayId = key.slice(0, -suffix.length)

      return Boolean(date)
        && storedDayId.includes(date)
        && signatureDayKind(storedDayId) === kind
    })

  return matchingKey || directKey
}
