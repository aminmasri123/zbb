import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { attendanceDayAllowed } from './attendanceCalendar.mjs'

const calendarCache = new Map()
export function useAttendanceCalendar(dateValues, options = {}) {
  const holidays = ref({})
  const error = ref('')
  const loading = ref(false)
  const years = computed(() => [...new Set(dateValues().filter(Boolean).map(value => String(value).slice(0, 4)))].sort())
  let generation = 0
  const load = async () => {
    const current = ++generation
    loading.value = true
    error.value = ''
    try {
      await Promise.all(years.value.map(async year => {
        if (!calendarCache.has(year)) {
          const request = axios.get(route('attendance.calendar'), { params: { year } })
            .then(response => response.data.holidays)
            .catch(cause => { calendarCache.delete(year); throw cause })
          calendarCache.set(year, request)
        }
        const result = await calendarCache.get(year)
        holidays.value = { ...holidays.value, [year]: result }
      }))
    } catch {
      if (current === generation) error.value = 'Feiertagskalender konnte nicht geladen werden. Bitte erneut laden.'
    } finally {
      if (current === generation) loading.value = false
    }
  }
  watch(years, load, { immediate: true })
  const allows = (date, overrides = options, confirmedDates = []) => attendanceDayAllowed(date, holidays.value, overrides, confirmedDates)
  return { allows, loading, error, reload: load }
}
