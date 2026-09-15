// Calendar dates are interpreted locally; never convert them through UTC.
export function attendanceDayAllowed(value, holidaysByYear, options = {}, confirmedDates = []) {
  if (!value) return false
  const key = String(value).slice(0, 10)
  const date = new Date(key + 'T12:00:00')
  if (Number.isNaN(date.getTime())) return false
  if (confirmedDates.includes(key)) return true
  if (date.getDay() === 6 && !options.includeSaturday) return false
  if (date.getDay() === 0 && !options.includeSunday) return false
  const holidays = holidaysByYear[key.slice(0, 4)]
  // Fail closed while the authoritative calendar is loading.
  if (!holidays) return false
  return !holidays[key] || Boolean(options.includeHolidays)
}
