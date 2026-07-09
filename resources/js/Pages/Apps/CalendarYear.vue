<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    year: Number,
    items: Array,
    calendars: Array,
    projects: Array,
    visibilityOptions: Array,
    styles: Array,
});

const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
const listSelectionBatchSize = 20;
const currentYear = ref(props.year);
const currentMonth = ref(new Date().getFullYear() === props.year ? new Date().getMonth() : 0);
const viewMode = ref('year');
const calendarItems = ref(props.items || []);
const loadingYear = ref(false);
const savingEvent = ref(false);
const fullscreen = ref(false);
const selectedCalendar = ref('all');
const showModal = ref(false);
const showImportModal = ref(false);
const editingEvent = ref(null);
const editingDay = ref(null);
const dragEvent = ref(null);
const selectingDays = ref(false);
const selectionStart = ref(null);
const selectionEnd = ref(null);
const selectionMoved = ref(false);
const pendingMove = ref(null);
const copyRangeStart = ref('');
const copyRangeEnd = ref('');
const copyRanges = ref([]);
const noticeModal = ref({
    show: false,
    title: '',
    message: '',
});
const confirmModal = ref({
    show: false,
    title: '',
    message: '',
    confirmText: 'Löschen',
    cancelText: 'Abbrechen',
    tone: 'danger',
    resolver: null,
});
const toast = ref({
    show: false,
    message: '',
    type: 'success',
});
const importForm = ref({
    file: null,
    calendar_id: '',
});
const importPreviewEvents = ref([]);
const selectedImportKeys = ref([]);
const importSummary = ref(null);
const importLoading = ref(false);
const importSaving = ref(false);
const selectedListEventIds = ref([]);
let toastTimer = null;

const eventForm = useForm({
    title: '',
    calendar_id: '',
    description: '',
    starts_at: '',
    ends_at: '',
    all_day: true,
    include_weekends: false,
    excluded_dates: [],
    location: '',
    background_color: '#ff7a00',
    text_color: '#ffffff',
    visibility: 'private',
    project_id: '',
    team_id: '',
});

const calendarForm = useForm({
    name: '',
    background_color: '#ff7a00',
    text_color: '#ffffff',
    visibility: 'private',
    project_id: '',
    team_id: '',
});

const styleForm = useForm({
    label: '',
    background_color: '#ff7a00',
    text_color: '#ffffff',
});

const personalCalendar = computed(() => (props.calendars || []).find((calendar) => calendar.name === 'Mein Kalender' && !calendar.project_id) || (props.calendars || [])[0] || null);
const defaultCalendarId = computed(() => personalCalendar.value?.id || '');

const filteredEvents = computed(() => {
    if (selectedCalendar.value === 'all') return calendarItems.value;
    return calendarItems.value.filter((event) => {
        if (String(event.calendar_id || '') === String(selectedCalendar.value)) {
            return true;
        }

        return personalCalendar.value && String(selectedCalendar.value) === String(personalCalendar.value.id) && !event.calendar_id;
    });
});

const months = computed(() => monthNames.map((name, index) => ({
    name,
    index,
    days: Array.from({ length: daysInMonth(currentYear.value, index) }, (_, dayIndex) => buildDay(currentYear.value, index, dayIndex + 1)),
})));

const activeMonth = computed(() => months.value[currentMonth.value] || months.value[0]);
const activeMonthGridDays = computed(() => {
    const firstDay = activeMonth.value.days[0];
    const leadingDays = firstDay ? (firstDay.date.getDay() + 6) % 7 : 0;

    return [
        ...Array.from({ length: leadingDays }, (_, index) => ({ empty: true, key: `empty-${currentMonth.value}-${index}` })),
        ...activeMonth.value.days,
    ];
});

const visibleEventDays = computed(() => {
    const items = [];

    months.value.forEach((month) => {
        month.days.forEach((day) => {
            dayEvents(day).forEach((event) => {
                items.push({
                    day,
                    event,
                    starts_at: event.starts_at,
                    key: `${day.iso}-${event.id}`,
                });
            });
        });
    });

    return items.sort((a, b) => String(a.day.iso + a.starts_at).localeCompare(String(b.day.iso + b.starts_at)));
});

const visibleListEventIds = computed(() => Array.from(new Set(visibleEventDays.value.map((item) => String(eventId(item.event))).filter(Boolean))));
const selectedVisibleListEventIds = computed(() => selectedListEventIds.value.filter((id) => visibleListEventIds.value.includes(String(id))));
const allVisibleListEventsSelected = computed(() => visibleListEventIds.value.length > 0 && selectedVisibleListEventIds.value.length === visibleListEventIds.value.length);
const nextListSelectionCount = computed(() => Math.min(
    listSelectionBatchSize,
    visibleListEventIds.value.filter((id) => !selectedListEventIds.value.map(String).includes(String(id))).length,
));

const exportCalendarUrl = computed(() => {
    const params = { year: currentYear.value };

    if (selectedCalendar.value !== 'all') {
        params.calendar = selectedCalendar.value;
    }

    return route('apps.calendar.export', params);
});

function buildDay(year, month, day) {
    const date = new Date(year, month, day);
    const iso = toDateInput(date);

    return {
        date,
        iso,
        day,
        weekday: weekdays[date.getDay()],
        isWeekend: date.getDay() === 0 || date.getDay() === 6,
        holiday: holidays.value[iso] || null,
    };
}

const holidays = computed(() => germanHolidays(currentYear.value));

function daysInMonth(year, month) {
    return new Date(year, month + 1, 0).getDate();
}

function toDateInput(date) {
    const copy = new Date(date);
    copy.setMinutes(copy.getMinutes() - copy.getTimezoneOffset());
    return copy.toISOString().slice(0, 10);
}

function toDateTimeInput(value) {
    const date = eventDate(value);
    if (!date) return '';

    return `${date}T${eventTime(value)}`;
}

function eventDate(value) {
    return String(value || '').slice(0, 10);
}

function eventTime(value, fallback = '08:00') {
    const match = String(value || '').match(/(?:T|\s)(\d{2}:\d{2})/);
    return match?.[1] || fallback;
}

function localDate(iso) {
    return new Date(`${iso}T00:00:00`);
}

function addDaysIso(iso, days) {
    const date = localDate(iso);
    date.setDate(date.getDate() + days);
    return toDateInput(date);
}

function diffDays(startIso, endIso) {
    return Math.round((localDate(endIso) - localDate(startIso)) / 86400000);
}

function dayEvents(day) {
    return filteredEvents.value
        .filter((event) => eventTouchesDay(event, day.iso))
        .sort((a, b) => String(a.starts_at).localeCompare(String(b.starts_at)));
}

function eventCalendarName(event) {
    return event.calendar?.name || personalCalendar.value?.name || 'Mein Kalender';
}

function eventTimeRange(event) {
    return `${eventTime(event.starts_at)} - ${eventTime(event.ends_at || event.starts_at, '16:00')}`;
}

function changeMonth(delta) {
    const next = currentMonth.value + delta;

    if (next < 0) {
        loadYear(currentYear.value - 1).then(() => {
            currentMonth.value = 11;
        });
        return;
    }

    if (next > 11) {
        loadYear(currentYear.value + 1).then(() => {
            currentMonth.value = 0;
        });
        return;
    }

    currentMonth.value = next;
}

function eventTouchesDay(event, iso) {
    const start = eventDate(event.starts_at);
    const end = eventDate(event.ends_at || event.starts_at);

    if ((event.excluded_dates || []).includes(iso)) {
        return false;
    }

    if (!event.include_weekends && start !== end) {
        const date = new Date(`${iso}T00:00:00`);
        if (date.getDay() === 0 || date.getDay() === 6) {
            return false;
        }
    }

    return start <= iso && end >= iso;
}

function openCreate(day = null) {
    const startIso = day ? day.iso : `${currentYear.value}-01-01`;
    openCreateRange(startIso, startIso);
}

function openCreateRange(startIso, endIso) {
    editingEvent.value = null;
    editingDay.value = null;
    resetCopyRanges();
    eventForm.reset();
    eventForm.clearErrors();
    eventForm.all_day = true;
    eventForm.include_weekends = false;
    eventForm.excluded_dates = [];
    eventForm.background_color = '#ff7a00';
    eventForm.text_color = '#ffffff';
    eventForm.calendar_id = defaultCalendarId.value;
    eventForm.visibility = 'private';
    eventForm.starts_at = `${startIso}T08:00`;
    eventForm.ends_at = `${endIso}T16:00`;
    showModal.value = true;
}

function openEdit(event, day = null) {
    editingEvent.value = event;
    editingDay.value = day?.iso || null;
    resetCopyRanges();
    eventForm.clearErrors();
    eventForm.title = event.title || '';
    eventForm.calendar_id = event.calendar_id || defaultCalendarId.value;
    eventForm.description = event.description || '';
    eventForm.starts_at = toDateTimeInput(event.starts_at);
    eventForm.ends_at = toDateTimeInput(event.ends_at);
    eventForm.all_day = Boolean(event.all_day);
    eventForm.include_weekends = Boolean(event.include_weekends);
    eventForm.excluded_dates = event.excluded_dates || [];
    eventForm.location = event.location || '';
    eventForm.background_color = event.background_color || event.calendar?.background_color || '#ff7a00';
    eventForm.text_color = event.text_color || event.calendar?.text_color || '#ffffff';
    eventForm.visibility = event.visibility || 'private';
    eventForm.project_id = event.project_id || '';
    eventForm.team_id = event.team_id || '';
    showModal.value = true;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function eventId(event) {
    return event?.id || event?.event_id || event?.calendar_event_id || null;
}

function calendarEventRoute(name, event) {
    const id = eventId(event);
    if (!id) {
        showToast('Der Termin konnte nicht eindeutig gefunden werden.', 'error');
        return null;
    }

    const calendarUrl = new URL(route('apps.calendar'), window.location.origin);
    const basePath = calendarUrl.pathname.replace(/\/$/, '');
    const suffixes = {
        'apps.calendar.move': '/move',
        'apps.calendar.copy': '/copy',
    };
    const suffix = suffixes[name] || '';

    return `${basePath}/${encodeURIComponent(id)}${suffix}`;
}

function eventFormPayload() {
    return {
        ...eventForm.data(),
        all_day: Boolean(eventForm.all_day),
        include_weekends: Boolean(eventForm.include_weekends),
        excluded_dates: eventForm.excluded_dates || [],
    };
}

async function calendarRequest(method, url, data = null, options = {}) {
    savingEvent.value = true;
    eventForm.clearErrors();

    try {
        const response = await sendCalendar(method, url, data);

        if (options.closeModal !== false) {
            showModal.value = false;
        }

        if (options.successMessage) {
            showToast(options.successMessage);
        }

        if (options.applyResponse) {
            options.applyResponse(response.data);
        } else if (options.reload !== false) {
            await loadYear(currentYear.value);
        }
    } catch (error) {
        if (error.response?.status === 422 && error.response.data?.errors) {
            eventForm.setError(error.response.data.errors);
            showToast('Bitte pruefe die Eingaben.', 'error');
        } else {
            handleCalendarError(error);
        }
    } finally {
        savingEvent.value = false;
    }
}

async function sendCalendar(method, url, data = null) {
    const token = csrfToken();
    const normalizedMethod = String(method).toLowerCase();
    let payload = data;

    if (['post', 'put', 'patch', 'delete'].includes(normalizedMethod) && token) {
        if (payload instanceof FormData) {
            if (!payload.has('_token')) {
                payload.append('_token', token);
            }
        } else if (payload && typeof payload === 'object') {
            payload = { _token: token, ...payload };
        }
    }

    return axios({
        method,
        url,
        data: payload,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
}

function handleCalendarError(error) {
    console.error(error);
    if (error.response?.status === 419) {
        showToast('Sitzung/CSRF ist abgelaufen. Bitte Seite einmal aktualisieren und erneut versuchen.', 'error');
        return;
    }

    showToast(error.response?.data?.message || error.message || 'Die Kalenderaenderung konnte nicht gespeichert werden.', 'error');
}

function showNotice(title, message) {
    noticeModal.value = {
        show: true,
        title,
        message,
    };
}

function closeNotice() {
    noticeModal.value.show = false;
}

function askConfirmation({ title, message, confirmText = 'Löschen', cancelText = 'Abbrechen', tone = 'danger' }) {
    return new Promise((resolve) => {
        confirmModal.value = {
            show: true,
            title,
            message,
            confirmText,
            cancelText,
            tone,
            resolver: resolve,
        };
    });
}

function resolveConfirmation(confirmed) {
    const resolver = confirmModal.value.resolver;

    confirmModal.value = {
        show: false,
        title: '',
        message: '',
        confirmText: 'Löschen',
        cancelText: 'Abbrechen',
        tone: 'danger',
        resolver: null,
    };

    if (resolver) {
        resolver(confirmed);
    }
}

function showToast(message, type = 'success') {
    toast.value = {
        show: true,
        message,
        type,
    };

    if (toastTimer) {
        window.clearTimeout(toastTimer);
    }

    toastTimer = window.setTimeout(() => {
        toast.value.show = false;
    }, 2600);
}

function calendarSnapshot() {
    return calendarItems.value.map((event) => ({
        ...event,
        excluded_dates: [...(event.excluded_dates || [])],
        calendar: event.calendar ? { ...event.calendar } : event.calendar,
    }));
}

function restoreCalendarSnapshot(snapshot) {
    calendarItems.value = snapshot;
}

function openImportModal() {
    importForm.value = {
        file: null,
        calendar_id: selectedCalendar.value !== 'all' ? selectedCalendar.value : defaultCalendarId.value,
    };
    importPreviewEvents.value = [];
    selectedImportKeys.value = [];
    importSummary.value = null;
    showImportModal.value = true;
}

function closeImportModal() {
    if (importLoading.value || importSaving.value) return;
    showImportModal.value = false;
}

async function previewCalendarImport() {
    if (!importForm.value.file) {
        showToast('Bitte zuerst eine Excel-Datei auswählen.', 'error');
        return;
    }

    importLoading.value = true;

    try {
        const data = new FormData();
        data.append('file', importForm.value.file);
        if (importForm.value.calendar_id) {
            data.append('calendar_id', importForm.value.calendar_id);
        }

        const response = await sendCalendar('post', route('apps.calendar.import.preview'), data);
        importPreviewEvents.value = response.data.events || [];
        selectedImportKeys.value = importPreviewEvents.value.filter((event) => event.selected).map((event) => event.key);
        importSummary.value = response.data.summary || null;
        showToast('Vorschau wurde erstellt.');
    } catch (error) {
        handleCalendarError(error);
    } finally {
        importLoading.value = false;
    }
}

function importKeySelected(key) {
    return selectedImportKeys.value.includes(key);
}

function toggleImportKey(key) {
    selectedImportKeys.value = importKeySelected(key)
        ? selectedImportKeys.value.filter((item) => item !== key)
        : [...selectedImportKeys.value, key];
}

function selectImportKind(kind) {
    const keys = new Set(selectedImportKeys.value);
    importPreviewEvents.value
        .filter((event) => !event.duplicate && (kind === 'all' ? !event.is_weekend && !event.is_holiday : event[kind]))
        .forEach((event) => keys.add(event.key));
    selectedImportKeys.value = Array.from(keys);
}

function clearImportSelection() {
    selectedImportKeys.value = [];
}

async function confirmCalendarImport() {
    const selectedEvents = importPreviewEvents.value.filter((event) => importKeySelected(event.key));

    if (selectedEvents.length === 0) {
        showToast('Bitte mindestens einen Termin auswählen.', 'error');
        return;
    }

    importSaving.value = true;

    try {
        const response = await sendCalendar('post', route('apps.calendar.import.confirm'), {
            calendar_id: importForm.value.calendar_id || null,
            events: selectedEvents,
        });

        showImportModal.value = false;
        showToast(response.data.message || 'Import abgeschlossen.');
        await loadYear(currentYear.value);
    } catch (error) {
        handleCalendarError(error);
    } finally {
        importSaving.value = false;
    }
}

function eventTouchesYearValue(event, year) {
    const start = eventDate(event.starts_at);
    const end = eventDate(event.ends_at || event.starts_at);

    return start <= `${year}-12-31` && end >= `${year}-01-01`;
}

function upsertCalendarEvent(event) {
    if (!event?.id) return;

    if (!eventTouchesYearValue(event, currentYear.value)) {
        removeCalendarEvent(event.id);
        return;
    }

    const id = String(event.id);
    const index = calendarItems.value.findIndex((item) => String(eventId(item)) === id);

    if (index === -1) {
        calendarItems.value = [...calendarItems.value, event].sort((a, b) => String(a.starts_at).localeCompare(String(b.starts_at)));
        return;
    }

    calendarItems.value = calendarItems.value.map((item, itemIndex) => (itemIndex === index ? event : item));
}

function removeCalendarEvent(id) {
    calendarItems.value = calendarItems.value.filter((event) => String(eventId(event)) !== String(id));
    selectedListEventIds.value = selectedListEventIds.value.filter((selectedId) => String(selectedId) !== String(id));
}

function payloadFromEvent(event, overrides = {}) {
    return {
        title: event.title,
        calendar_id: event.calendar_id || '',
        description: event.description || '',
        starts_at: toDateTimeInput(event.starts_at),
        ends_at: toDateTimeInput(event.ends_at || event.starts_at),
        all_day: Boolean(event.all_day),
        include_weekends: Boolean(event.include_weekends),
        excluded_dates: event.excluded_dates || [],
        location: event.location || '',
        background_color: event.background_color || event.calendar?.background_color || '#ff7a00',
        text_color: event.text_color || event.calendar?.text_color || '#ffffff',
        visibility: event.visibility || 'private',
        project_id: event.project_id || '',
        team_id: event.team_id || '',
        ...overrides,
    };
}

function saveEvent() {
    if (editingEvent.value) {
        const url = calendarEventRoute('apps.calendar.update', editingEvent.value);
        if (!url) return;
        calendarRequest('put', url, eventFormPayload(), {
            successMessage: 'Termin wurde gespeichert.',
            applyResponse: (data) => upsertCalendarEvent(data.event),
            reload: false,
        });
    } else {
        calendarRequest('post', route('apps.calendar.store'), eventFormPayload(), {
            successMessage: 'Termin wurde angelegt.',
            applyResponse: (data) => upsertCalendarEvent(data.event),
            reload: false,
        });
    }
}

async function deleteEvent() {
    if (!editingEvent.value) return;

    const confirmed = await askConfirmation({
        title: 'Ganzes Event löschen?',
        message: 'Dieser Termin wird komplett aus dem Kalender entfernt.',
        confirmText: 'Ganzes Event löschen',
    });

    if (!confirmed) return;

    const url = calendarEventRoute('apps.calendar.destroy', editingEvent.value);
    if (!url) return;
    calendarRequest('delete', url, null, {
        successMessage: 'Termin wurde geloescht.',
        applyResponse: (data) => removeCalendarEvent(data.id || eventId(editingEvent.value)),
        reload: false,
    });
}

async function deleteListEvent(event) {
    if (!event) return;

    const confirmed = await askConfirmation({
        title: 'Termin löschen?',
        message: `"${event.title || 'Termin'}" wird aus dem Kalender entfernt.`,
    });

    if (!confirmed) return;

    const url = calendarEventRoute('apps.calendar.destroy', event);
    if (!url) return;

    savingEvent.value = true;

    try {
        const response = await sendCalendar('delete', url);
        removeCalendarEvent(response.data.id || eventId(event));
        showToast(response.data.message || 'Termin wurde geloescht.');
    } catch (error) {
        handleCalendarError(error);
    } finally {
        savingEvent.value = false;
    }
}

function listEventSelected(event) {
    const id = String(eventId(event));

    return selectedListEventIds.value.includes(id);
}

function toggleListEvent(event) {
    const id = String(eventId(event));
    if (!id) return;

    selectedListEventIds.value = listEventSelected(event)
        ? selectedListEventIds.value.filter((selectedId) => String(selectedId) !== id)
        : [...selectedListEventIds.value, id];
}

function toggleAllVisibleListEvents() {
    if (allVisibleListEventsSelected.value) {
        selectedListEventIds.value = selectedListEventIds.value.filter((id) => !visibleListEventIds.value.includes(String(id)));
        return;
    }

    const selectedIds = selectedListEventIds.value.map(String);
    const nextIds = visibleListEventIds.value
        .filter((id) => !selectedIds.includes(String(id)))
        .slice(0, listSelectionBatchSize);

    selectedListEventIds.value = Array.from(new Set([...selectedIds, ...nextIds]));
}

async function deleteSelectedListEvents() {
    const ids = [...selectedVisibleListEventIds.value];
    if (ids.length === 0) return;

    const confirmed = await askConfirmation({
        title: `${ids.length} Termine löschen?`,
        message: 'Alle ausgewaehlten Termine werden aus dem Kalender entfernt.',
        confirmText: 'Ausgewählte löschen',
    });

    if (!confirmed) return;

    savingEvent.value = true;

    try {
        let deleted = 0;

        for (const id of ids) {
            const event = calendarItems.value.find((item) => String(eventId(item)) === String(id));
            if (!event) continue;

            const url = calendarEventRoute('apps.calendar.destroy', event);
            if (!url) continue;

            const response = await sendCalendar('delete', url);
            removeCalendarEvent(response.data.id || id);
            deleted++;
        }

        showToast(`${deleted} Termine wurden geloescht.`);
    } catch (error) {
        handleCalendarError(error);
    } finally {
        savingEvent.value = false;
    }
}

function canDeleteClickedDay() {
    return Boolean(editingEvent.value && editingDay.value);
}

function canRemoveSingleDay() {
    if (!editingEvent.value || !editingDay.value) return false;

    const start = eventDate(editingEvent.value.starts_at);
    const end = eventDate(editingEvent.value.ends_at || editingEvent.value.starts_at);

    return start !== end && editingDay.value >= start && editingDay.value <= end;
}

function removeSingleDay() {
    if (!canRemoveSingleDay()) return;

    const excludedDates = Array.from(new Set([...(editingEvent.value.excluded_dates || []), editingDay.value])).sort();

    const url = calendarEventRoute('apps.calendar.update', editingEvent.value);
    if (!url) return;

    calendarRequest('put', url, payloadFromEvent(editingEvent.value, {
        excluded_dates: excludedDates,
    }), {
        successMessage: 'Dieser Tag wurde entfernt.',
        applyResponse: (data) => upsertCalendarEvent(data.event),
        reload: false,
    });
}

async function deleteClickedDay() {
    if (!canDeleteClickedDay()) return;

    const confirmed = await askConfirmation({
        title: 'Diesen Tag löschen?',
        message: canRemoveSingleDay()
            ? 'Nur der angeklickte Tag wird aus diesem mehrtaegigen Event entfernt.'
            : 'Dieser Termin besteht nur aus diesem Tag und wird komplett entfernt.',
        confirmText: 'Diesen Tag löschen',
    });

    if (!confirmed) return;

    if (canRemoveSingleDay()) {
        removeSingleDay();
        return;
    }

    const url = calendarEventRoute('apps.calendar.destroy', editingEvent.value);
    if (!url) return;

    calendarRequest('delete', url, null, {
        successMessage: 'Dieser Tag wurde geloescht.',
        applyResponse: (data) => removeCalendarEvent(data.id || eventId(editingEvent.value)),
        reload: false,
    });
}

function resetCopyRanges() {
    copyRangeStart.value = '';
    copyRangeEnd.value = '';
    copyRanges.value = [];
}

function addCopyRange() {
    if (!copyRangeStart.value) return;

    const range = {
        start_date: copyRangeStart.value,
        end_date: copyRangeEnd.value || copyRangeStart.value,
    };

    if (range.end_date < range.start_date) {
        showToast('Das Bis-Datum darf nicht vor dem Von-Datum liegen.', 'error');
        return;
    }

    const key = copyRangeKey(range);
    const existing = new Set(copyRanges.value.map(copyRangeKey));
    if (!existing.has(key)) {
        copyRanges.value = [...copyRanges.value, range].sort((a, b) => a.start_date.localeCompare(b.start_date));
    }

    copyRangeStart.value = '';
    copyRangeEnd.value = '';
}

function copyRangeKey(range) {
    return `${range.start_date}_${range.end_date}`;
}

function copyRangeLabel(range) {
    return range.start_date === range.end_date ? range.start_date : `${range.start_date} bis ${range.end_date}`;
}

function removeCopyRange(range) {
    const key = copyRangeKey(range);
    copyRanges.value = copyRanges.value.filter((item) => copyRangeKey(item) !== key);
}

async function copyClickedDay() {
    if (!editingEvent.value || copyRanges.value.length === 0) return;

    savingEvent.value = true;
    eventForm.clearErrors();

    try {
        const url = calendarEventRoute('apps.calendar.copy', editingEvent.value);
        if (!url) return;

        await sendCalendar('post', url, {
            ranges: copyRanges.value,
            include_weekends: Boolean(eventForm.include_weekends),
        });

        const count = copyRanges.value.length;
        resetCopyRanges();
        showToast(count === 1 ? 'Kopie wurde erstellt.' : `${count} Kopien wurden erstellt.`);
        await loadYear(currentYear.value);
    } catch (error) {
        handleCalendarError(error);
    } finally {
        savingEvent.value = false;
    }
}

function createCalendar() {
    calendarForm.post(route('apps.calendar.calendars.store'), {
        preserveScroll: true,
        onSuccess: () => calendarForm.reset(),
    });
}

function createStyle() {
    styleForm.post(route('apps.calendar.styles.store'), {
        preserveScroll: true,
        onSuccess: () => styleForm.reset(),
    });
}

function startDrag(event, day) {
    dragEvent.value = {
        event,
        sourceIso: day?.iso || eventDate(event.starts_at),
    };
}

function dropOnDay(day) {
    if (!dragEvent.value) return;

    const move = {
        ...dragEvent.value,
        targetIso: day.iso,
    };
    dragEvent.value = null;

    const start = eventDate(move.event.starts_at);
    const end = eventDate(move.event.ends_at || move.event.starts_at);

    if (start !== end && move.sourceIso >= start && move.sourceIso <= end) {
        pendingMove.value = move;
        return;
    }

    moveWholeEvent(move.event, move.targetIso);
}

function cancelMove() {
    pendingMove.value = null;
}

async function confirmMove(mode) {
    if (!pendingMove.value) return;

    const move = pendingMove.value;
    pendingMove.value = null;

    if (mode === 'single') {
        await moveSingleOccurrence(move.event, move.sourceIso, move.targetIso);
        return;
    }

    await moveWholeEvent(move.event, move.targetIso);
}

async function moveWholeEvent(event, targetIso) {
    const url = calendarEventRoute('apps.calendar.move', event);
    if (!url) return;
    const snapshot = calendarSnapshot();

    savingEvent.value = true;
    eventForm.clearErrors();
    optimisticMoveGroup(event, targetIso);

    try {
        await sendCalendar('post', url, {
            mode: 'group',
            target_date: targetIso,
        });
        showToast('Termin wurde verschoben.');
        await loadYear(currentYear.value);
    } catch (error) {
        restoreCalendarSnapshot(snapshot);
        handleCalendarError(error);
    } finally {
        savingEvent.value = false;
    }
}

async function moveSingleOccurrence(event, sourceIso, targetIso) {
    savingEvent.value = true;
    eventForm.clearErrors();
    let snapshot = null;

    try {
        const url = calendarEventRoute('apps.calendar.move', event);
        if (!url) return;
        snapshot = calendarSnapshot();

        optimisticMoveSingle(event, sourceIso, targetIso);

        await sendCalendar('post', url, {
            mode: 'single',
            source_date: sourceIso,
            target_date: targetIso,
        });

        showToast('Einzelner Termin wurde verschoben.');
        await loadYear(currentYear.value);
    } catch (error) {
        if (snapshot) {
            restoreCalendarSnapshot(snapshot);
        }

        if (error.response?.status === 422 && error.response.data?.errors) {
            eventForm.setError(error.response.data.errors);
            showToast('Bitte pruefe die Eingaben.', 'error');
        } else {
            handleCalendarError(error);
        }
    } finally {
        savingEvent.value = false;
    }
}

function optimisticMoveGroup(event, targetIso) {
    const id = eventId(event);
    const startIso = eventDate(event.starts_at);
    const endIso = eventDate(event.ends_at || event.starts_at);
    const duration = Math.max(0, diffDays(startIso, endIso));
    const delta = diffDays(startIso, targetIso);
    const nextEndIso = addDaysIso(targetIso, duration);

    calendarItems.value = calendarItems.value.map((item) => {
        if (String(eventId(item)) !== String(id)) return item;

        return {
            ...item,
            starts_at: `${targetIso}T${eventTime(item.starts_at, '08:00')}`,
            ends_at: `${nextEndIso}T${eventTime(item.ends_at || item.starts_at, '16:00')}`,
            excluded_dates: (item.excluded_dates || []).map((date) => addDaysIso(date, delta)),
        };
    });
}

function optimisticMoveSingle(event, sourceIso, targetIso) {
    const id = eventId(event);
    const movedEvent = {
        ...event,
        id: `temp-${Date.now()}`,
        starts_at: `${targetIso}T${eventTime(event.starts_at, '08:00')}`,
        ends_at: `${targetIso}T${eventTime(event.ends_at || event.starts_at, '16:00')}`,
        include_weekends: false,
        excluded_dates: [],
    };

    calendarItems.value = [
        ...calendarItems.value.map((item) => {
            if (String(eventId(item)) !== String(id)) return item;

            return {
                ...item,
                excluded_dates: Array.from(new Set([...(item.excluded_dates || []), sourceIso])).sort(),
            };
        }),
        movedEvent,
    ];
}

async function loadYear(year) {
    if (loadingYear.value) return;

    loadingYear.value = true;

    try {
        const response = await fetch(route('apps.calendar.events', { year }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Kalenderdaten konnten nicht geladen werden.');
        }

        const data = await response.json();
        currentYear.value = data.year;
        calendarItems.value = data.items || [];
        window.history.replaceState({}, '', route('apps.calendar', { year: data.year }));
    } catch (error) {
        console.error(error);
        showToast('Das Jahr konnte nicht geladen werden.', 'error');
    } finally {
        loadingYear.value = false;
    }
}

function selectionBounds() {
    if (!selectionStart.value || !selectionEnd.value) return null;

    return [
        selectionStart.value < selectionEnd.value ? selectionStart.value : selectionEnd.value,
        selectionStart.value < selectionEnd.value ? selectionEnd.value : selectionStart.value,
    ];
}

function isSelectedDay(day) {
    const bounds = selectionBounds();
    return bounds && day.iso >= bounds[0] && day.iso <= bounds[1];
}

function startDaySelection(day, event) {
    if (event.button !== 0) return;

    selectingDays.value = true;
    selectionStart.value = day.iso;
    selectionEnd.value = day.iso;
    selectionMoved.value = false;
}

function extendDaySelection(day) {
    if (!selectingDays.value) return;
    if (day.iso !== selectionStart.value) {
        selectionMoved.value = true;
    }
    selectionEnd.value = day.iso;
}

function finishDaySelection() {
    if (!selectingDays.value) return;

    selectingDays.value = false;
    const bounds = selectionBounds();
    const shouldOpenModal = selectionMoved.value;
    selectionStart.value = null;
    selectionEnd.value = null;
    selectionMoved.value = false;

    if (bounds && shouldOpenModal) {
        openCreateRange(bounds[0], bounds[1]);
    }
}

function eventStyle(event) {
    const backgroundColor = event.background_color || event.calendar?.background_color || '#ff7a00';
    const color = event.text_color || event.calendar?.text_color || '#ffffff';

    return {
        backgroundColor,
        color: color.toLowerCase() === backgroundColor.toLowerCase() ? '#ffffff' : color,
    };
}

function syncFullscreenState() {
    if (!document.fullscreenElement) {
        fullscreen.value = false;
    }
}

async function toggleFullscreen() {
    if (fullscreen.value) {
        fullscreen.value = false;
        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(() => {});
        }
        return;
    }

    fullscreen.value = true;
    await nextTick();

    const el = document.documentElement;
    if (el?.requestFullscreen) {
        el.requestFullscreen().catch(() => {
            fullscreen.value = false;
        });
    }
}

onMounted(() => {
    document.addEventListener('fullscreenchange', syncFullscreenState);
    document.addEventListener('mouseup', finishDaySelection);
});

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', syncFullscreenState);
    document.removeEventListener('mouseup', finishDaySelection);

    if (toastTimer) {
        window.clearTimeout(toastTimer);
    }
});

function easterDate(year) {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31) - 1;
    const day = ((h + l - 7 * m + 114) % 31) + 1;
    return new Date(year, month, day);
}

function addDays(date, days) {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
}

function germanHolidays(year) {
    const easter = easterDate(year);
    const fixed = {
        [`${year}-01-01`]: 'Neujahr',
        [`${year}-05-01`]: 'Tag der Arbeit',
        [`${year}-10-03`]: 'Tag der Deutschen Einheit',
        [`${year}-11-01`]: 'Allerheiligen',
        [`${year}-12-25`]: '1. Weihnachtstag',
        [`${year}-12-26`]: '2. Weihnachtstag',
    };

    fixed[toDateInput(addDays(easter, -2))] = 'Karfreitag';
    fixed[toDateInput(addDays(easter, 1))] = 'Ostermontag';
    fixed[toDateInput(addDays(easter, 39))] = 'Christi Himmelfahrt';
    fixed[toDateInput(addDays(easter, 50))] = 'Pfingstmontag';
    fixed[toDateInput(addDays(easter, 60))] = 'Fronleichnam';

    return fixed;
}
</script>

<template>
    <AppLayout title="Kalender">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>Kalender</span>
                <div v-if="!fullscreen" class="flex flex-wrap items-center justify-end gap-2">
                    <div class="inline-flex h-9 overflow-hidden rounded border border-gray-200 bg-white text-sm">
                        <button
                            v-for="mode in [{ value: 'year', label: 'Jahr' }, { value: 'month', label: 'Monat' }, { value: 'list', label: 'Liste' }]"
                            :key="mode.value"
                            type="button"
                            class="px-3 font-semibold"
                            :class="viewMode === mode.value ? 'bg-orange-500 text-white' : 'text-gray-700 hover:bg-gray-50'"
                            @click="viewMode = mode.value"
                        >
                            {{ mode.label }}
                        </button>
                    </div>
                    <select v-model="selectedCalendar" class="h-9 rounded border-gray-300 text-sm font-normal">
                        <option value="all">Alle Kalender</option>
                        <option v-for="calendar in calendars" :key="calendar.id" :value="calendar.id">{{ calendar.name }}</option>
                    </select>
                    <button class="inline-flex h-9 items-center rounded border bg-white px-3 text-sm font-normal disabled:opacity-50" :disabled="loadingYear" @click="loadYear(currentYear - 1)">&lt;&lt;</button>
                    <span class="px-2 text-2xl font-semibold">{{ currentYear }}</span>
                    <button class="inline-flex h-9 items-center rounded border bg-white px-3 text-sm font-normal disabled:opacity-50" :disabled="loadingYear" @click="loadYear(currentYear + 1)">&gt;&gt;</button>
                    <a :href="exportCalendarUrl" class="inline-flex h-9 items-center rounded border border-green-200 bg-green-50 px-3 text-sm font-semibold text-green-700">
                        <i class="la la-file-excel mr-1"></i>
                        Excel
                    </a>
                    <button class="inline-flex h-9 items-center rounded border border-blue-200 bg-blue-50 px-3 text-sm font-semibold text-blue-700" @click="openImportModal">
                        <i class="la la-file-import mr-1"></i>
                        Import
                    </button>
                    <button class="h-9 rounded bg-orange-500 px-4 text-sm font-semibold text-white" @click="openCreate()">+ Event anlegen</button>
                    <button class="h-9 rounded bg-orange-500 px-3 text-sm font-semibold text-white" @click="toggleFullscreen">
                        <i class="la la-expand"></i>
                    </button>
                </div>
            </div>
        </template>

        <div :class="fullscreen ? 'fixed inset-0 z-[60] overflow-hidden bg-white p-1 text-gray-900' : 'min-h-screen bg-gray-50 py-0'">
            <button
                v-if="fullscreen"
                type="button"
                class="fixed right-2 top-2 z-[70] flex h-8 w-8 items-center justify-center rounded-full bg-white text-xl font-semibold text-gray-900 shadow ring-1 ring-gray-200 hover:bg-gray-50"
                title="Vollbild schließen"
                @click="toggleFullscreen"
            >
                &times;
            </button>
            <div id="year-calendar-board" :class="fullscreen ? 'mx-auto flex h-full w-full max-w-none flex-col bg-white text-gray-900' : 'mx-auto w-full max-w-none'">
                <div v-if="!fullscreen && viewMode === 'month'" class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded border border-gray-200 bg-white px-3 py-2">
                    <div class="flex items-center gap-2">
                        <button class="h-9 rounded border bg-white px-3 text-sm font-semibold" :disabled="loadingYear" @click="changeMonth(-1)">&lt;&lt;</button>
                        <div class="min-w-48 text-center text-lg font-semibold text-gray-900">{{ activeMonth.name }} {{ currentYear }}</div>
                        <button class="h-9 rounded border bg-white px-3 text-sm font-semibold" :disabled="loadingYear" @click="changeMonth(1)">&gt;&gt;</button>
                    </div>
                    <button class="h-9 rounded bg-orange-500 px-4 text-sm font-semibold text-white" @click="openCreate(activeMonth.days[0])">+ Event anlegen</button>
                </div>

                <div :class="fullscreen ? 'min-h-0 flex-1' : 'mb-4 space-y-3'">
                    <div v-if="fullscreen || viewMode === 'year'" :class="fullscreen ? 'flex h-full flex-col overflow-hidden border border-gray-200 bg-white p-1 pr-10 text-gray-900 shadow-sm' : 'overflow-hidden rounded border border-gray-200 bg-white p-3 shadow-sm'">
                        <div :class="fullscreen ? 'mb-1 flex shrink-0 flex-wrap gap-1' : 'mb-2 flex flex-wrap gap-2'">
                            <span v-for="calendar in calendars" :key="calendar.id" class="rounded px-2 py-1 text-xs font-semibold" :style="{ backgroundColor: calendar.background_color, color: calendar.text_color }">
                                {{ calendar.name }}
                            </span>
                        </div>
                        <div
                            :class="fullscreen ? 'grid min-h-0 flex-1 w-full select-none border-l border-t text-[9px] xl:text-[10px]' : 'w-full select-none border-l border-t text-[9px] xl:text-[10px]'"
                            style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr));"
                        >
                            <div v-for="month in months" :key="month.index" :class="fullscreen ? 'flex min-w-0 flex-col border-r' : 'min-w-0 border-r'">
                                <div :class="fullscreen ? 'shrink-0 truncate border-b bg-white px-1 py-2 text-center text-xs font-bold text-gray-950 xl:text-sm' : 'sticky top-0 z-10 truncate border-b bg-white px-1 py-2 text-center text-xs font-bold xl:text-sm'">{{ month.name }}</div>
                                <div
                                    v-for="day in month.days"
                                    :key="day.iso"
                                    :class="[
                                        fullscreen ? 'min-h-0 flex-1 border-b px-1 py-0.5' : 'min-h-[24px] border-b px-1 py-0.5',
                                        day.isWeekend ? 'bg-gray-100' : 'bg-white',
                                        day.holiday ? 'bg-orange-50' : '',
                                        isSelectedDay(day) ? 'ring-2 ring-inset ring-blue-500 bg-blue-50' : '',
                                    ]"
                                    @mousedown.prevent="startDaySelection(day, $event)"
                                    @mouseenter="extendDaySelection(day)"
                                    @dblclick="openCreate(day)"
                                    @dragover.prevent
                                    @drop="dropOnDay(day)"
                                >
                                    <div :class="fullscreen ? 'mb-0.5 flex items-center justify-between gap-1 text-[9px] text-gray-950' : 'mb-0.5 flex items-center justify-between gap-1 text-[9px] text-gray-700'">
                                        <span>{{ day.weekday }}.{{ String(day.day).padStart(2, '0') }}</span>
                                        <span v-if="day.holiday" class="max-w-[70%] truncate rounded bg-orange-500 px-1 text-[8px] font-semibold text-white">{{ day.holiday }}</span>
                                    </div>
                                    <button
                                        v-for="event in dayEvents(day)"
                                        :key="`${day.iso}-${event.id}`"
                                        draggable="true"
                                        class="mb-0.5 block w-full truncate rounded px-1 py-0.5 text-left text-[8px] font-semibold shadow-sm"
                                        :style="eventStyle(event)"
                                        :title="event.title"
                                        @mousedown.stop
                                        @dragstart="startDrag(event, day)"
                                        @click.stop="openEdit(event, day)"
                                    >
                                        {{ event.title }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="viewMode === 'month'" class="overflow-hidden rounded border border-gray-200 bg-white p-3 shadow-sm">
                        <div class="grid grid-cols-7 border-l border-t text-xs font-semibold text-gray-600">
                            <div v-for="weekday in ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']" :key="weekday" class="border-b border-r bg-gray-50 px-2 py-2 text-center">{{ weekday }}</div>
                            <div
                                v-for="day in activeMonthGridDays"
                                :key="day.empty ? day.key : day.iso"
                                :class="[
                                    'min-h-[128px] border-b border-r p-2',
                                    day.empty ? 'bg-gray-50' : day.isWeekend ? 'bg-blue-50/60' : 'bg-white',
                                    !day.empty && day.holiday ? 'bg-orange-50' : '',
                                    !day.empty && isSelectedDay(day) ? 'ring-2 ring-inset ring-blue-500' : '',
                                ]"
                                @mousedown.prevent="!day.empty && startDaySelection(day, $event)"
                                @mouseenter="!day.empty && extendDaySelection(day)"
                                @dblclick="!day.empty && openCreate(day)"
                                @dragover.prevent
                                @drop="!day.empty && dropOnDay(day)"
                            >
                                <template v-if="!day.empty">
                                    <div class="mb-2 flex items-center justify-between gap-2 text-xs text-gray-700">
                                        <span class="font-semibold">{{ day.day }}</span>
                                        <span v-if="day.holiday" class="truncate rounded bg-orange-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ day.holiday }}</span>
                                    </div>
                                    <button
                                        v-for="event in dayEvents(day)"
                                        :key="`${day.iso}-${event.id}`"
                                        draggable="true"
                                        class="mb-1 block w-full truncate rounded px-2 py-1 text-left text-xs font-semibold shadow-sm"
                                        :style="eventStyle(event)"
                                        :title="event.title"
                                        @mousedown.stop
                                        @dragstart="startDrag(event, day)"
                                        @click.stop="openEdit(event, day)"
                                    >
                                        {{ event.title }}
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div v-else class="overflow-hidden rounded border border-gray-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-white px-4 py-3">
                            <div class="text-sm font-semibold text-gray-900">
                                {{ selectedVisibleListEventIds.length }} ausgewaehlt
                                <span v-if="nextListSelectionCount && !allVisibleListEventsSelected" class="ml-2 font-normal text-gray-500">+{{ nextListSelectionCount }} beim naechsten Klick</span>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center rounded border border-red-200 bg-white px-3 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                                :disabled="savingEvent || selectedVisibleListEventIds.length === 0"
                                @click="deleteSelectedListEvents"
                            >
                                <i class="la la-trash mr-1"></i>
                                Ausgewählte löschen
                            </button>
                        </div>
                        <div class="grid grid-cols-[36px_120px_120px_1fr_160px_48px] gap-3 border-b bg-gray-50 px-4 py-2 text-xs font-semibold uppercase text-gray-600">
                            <span>
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                                    :checked="allVisibleListEventsSelected"
                                    :title="allVisibleListEventsSelected ? 'Sichtbare Auswahl aufheben' : `Naechste ${nextListSelectionCount || listSelectionBatchSize} Termine markieren`"
                                    @click.prevent="toggleAllVisibleListEvents"
                                />
                            </span>
                            <span>Datum</span>
                            <span>Zeit</span>
                            <span>Termin</span>
                            <span>Kalender</span>
                            <span></span>
                        </div>
                        <div class="max-h-[70vh] overflow-y-auto">
                            <div
                                v-for="item in visibleEventDays"
                                :key="item.key"
                                class="grid w-full grid-cols-[36px_120px_120px_1fr_160px_48px] items-center gap-3 border-b px-4 py-2 text-left text-sm hover:bg-orange-50"
                                @click="openEdit(item.event, item.day)"
                            >
                                <span>
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                                        :checked="listEventSelected(item.event)"
                                        @click.stop
                                        @change="toggleListEvent(item.event)"
                                    />
                                </span>
                                <span class="font-semibold text-gray-900">{{ item.day.weekday }}. {{ item.day.iso }}</span>
                                <span class="text-gray-600">{{ eventTimeRange(item.event) }}</span>
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: item.event.background_color || item.event.calendar?.background_color || '#ff7a00' }"></span>
                                    <span class="truncate font-semibold text-gray-900">{{ item.event.title }}</span>
                                </span>
                                <span class="truncate text-gray-600">{{ eventCalendarName(item.event) }}</span>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded border border-red-200 bg-white text-red-600 hover:bg-red-50 disabled:opacity-50"
                                    title="Termin löschen"
                                    :disabled="savingEvent"
                                    @click.stop="deleteListEvent(item.event)"
                                >
                                    <i class="la la-trash"></i>
                                </button>
                            </div>
                            <div v-if="visibleEventDays.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                                Keine Termine in dieser Sicht.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4" @click.self="showModal = false">
            <form class="w-full max-w-2xl rounded bg-white p-5 shadow-xl" @submit.prevent="saveEvent">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">{{ editingEvent ? 'Event bearbeiten' : 'Event anlegen' }}</h2>
                    <button type="button" class="text-xl" :disabled="savingEvent" @click="showModal = false">&times;</button>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <input v-model="eventForm.title" class="rounded border-gray-300 text-sm md:col-span-2" placeholder="Bezeichnung" />
                    <select v-model="eventForm.calendar_id" class="rounded border-gray-300 text-sm">
                        <option v-for="calendar in calendars" :key="calendar.id" :value="calendar.id">{{ calendar.name }}</option>
                    </select>
                    <select v-model="eventForm.visibility" class="rounded border-gray-300 text-sm">
                        <option v-for="option in visibilityOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <input v-model="eventForm.starts_at" type="datetime-local" class="rounded border-gray-300 text-sm" />
                    <input v-model="eventForm.ends_at" type="datetime-local" class="rounded border-gray-300 text-sm" />
                    <label class="inline-flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm text-gray-700 md:col-span-2">
                        <input v-model="eventForm.include_weekends" type="checkbox" class="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />
                        Event findet auch am Wochenende statt
                    </label>
                    <input v-model="eventForm.location" class="rounded border-gray-300 text-sm md:col-span-2" placeholder="Ort" />
                    <textarea v-model="eventForm.description" rows="3" class="rounded border-gray-300 text-sm md:col-span-2" placeholder="Beschreibung"></textarea>
                    <div class="grid grid-cols-2 gap-2 md:col-span-2">
                        <label class="text-xs text-gray-600">Hintergrund <input v-model="eventForm.background_color" type="color" class="mt-1 h-10 w-full rounded border" /></label>
                        <label class="text-xs text-gray-600">Schrift <input v-model="eventForm.text_color" type="color" class="mt-1 h-10 w-full rounded border" /></label>
                    </div>
                    <div v-if="editingEvent && editingDay" class="space-y-3 rounded border border-gray-200 bg-gray-50 p-3 md:col-span-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Angeklickter Tag</div>
                                <div class="text-xs text-gray-500">{{ editingDay }}</div>
                            </div>
                            <button type="button" class="rounded border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-600 disabled:opacity-50" :disabled="savingEvent" @click="deleteClickedDay">
                                Diesen Tag löschen
                            </button>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-semibold text-gray-600">Kopie für bestimmte Tage oder Zeiträume erstellen</label>
                            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                                <label class="text-xs font-semibold text-gray-600">
                                    Von
                                    <input v-model="copyRangeStart" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" @keydown.enter.prevent="addCopyRange" />
                                </label>
                                <label class="text-xs font-semibold text-gray-600">
                                    Bis
                                    <input v-model="copyRangeEnd" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" @keydown.enter.prevent="addCopyRange" />
                                </label>
                                <div class="flex items-end">
                                    <button type="button" class="w-full rounded border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700" @click="addCopyRange">Zeitraum hinzufügen</button>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="rounded bg-orange-500 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="savingEvent || copyRanges.length === 0" @click="copyClickedDay">
                                    Kopie erstellen
                                </button>
                                <span class="text-xs text-gray-500">Eine Kopie kann ein einzelner Tag oder mehrere Tage sein.</span>
                            </div>
                            <div v-if="copyRanges.length" class="flex flex-wrap gap-2">
                                <button
                                    v-for="range in copyRanges"
                                    :key="copyRangeKey(range)"
                                    type="button"
                                    class="rounded bg-white px-2 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200"
                                    @click="removeCopyRange(range)"
                                >
                                    {{ copyRangeLabel(range) }} &times;
                                </button>
                            </div>
                        </div>
                    </div>
                    <select v-if="eventForm.visibility === 'project'" v-model="eventForm.project_id" class="rounded border-gray-300 text-sm md:col-span-2">
                        <option value="">Aktuelles Projekt</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select>
                </div>
                <div class="mt-5 flex justify-between">
                    <div class="flex flex-wrap gap-2">
                        <button v-if="editingEvent" type="button" class="rounded border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 disabled:opacity-50" :disabled="savingEvent" @click="deleteEvent">Ganzes Event löschen</button>
                    </div>
                    <button class="rounded bg-orange-500 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="savingEvent">{{ savingEvent ? 'Speichert ...' : 'Speichern' }}</button>
                </div>
            </form>
        </div>

        <DialogModal :show="Boolean(pendingMove)" max-width="md" @close="cancelMove">
            <template #title>
                Termin verschieben
            </template>
            <template #content>
                Soll nur dieser einzelne Tag verschoben werden oder das komplette Event?
            </template>
            <template #footer>
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700" :disabled="savingEvent" @click="cancelMove">Abbrechen</button>
                    <button type="button" class="rounded border border-orange-200 px-4 py-2 text-sm font-semibold text-orange-600" :disabled="savingEvent" @click="confirmMove('single')">Nur diesen Tag</button>
                    <button type="button" class="rounded bg-orange-500 px-4 py-2 text-sm font-semibold text-white" :disabled="savingEvent" @click="confirmMove('group')">Ganzes Event</button>
                </div>
            </template>
        </DialogModal>

        <DialogModal :show="noticeModal.show" max-width="md" @close="closeNotice">
            <template #title>
                {{ noticeModal.title }}
            </template>
            <template #content>
                {{ noticeModal.message }}
            </template>
            <template #footer>
                <button type="button" class="rounded bg-orange-500 px-4 py-2 text-sm font-semibold text-white" @click="closeNotice">OK</button>
            </template>
        </DialogModal>

        <DialogModal :show="confirmModal.show" max-width="md" @close="resolveConfirmation(false)">
            <template #title>
                {{ confirmModal.title }}
            </template>
            <template #content>
                {{ confirmModal.message }}
            </template>
            <template #footer>
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 disabled:opacity-50"
                        :disabled="savingEvent"
                        @click="resolveConfirmation(false)"
                    >
                        {{ confirmModal.cancelText }}
                    </button>
                    <button
                        type="button"
                        class="rounded px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :class="confirmModal.tone === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-orange-500 hover:bg-orange-600'"
                        :disabled="savingEvent"
                        @click="resolveConfirmation(true)"
                    >
                        {{ confirmModal.confirmText }}
                    </button>
                </div>
            </template>
        </DialogModal>

        <div v-if="showImportModal" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4" @click.self="closeImportModal">
            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col rounded bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Kalender importieren</h2>
                        <p class="text-sm text-gray-500">Erst Vorschau pruefen, dann bewusst importieren.</p>
                    </div>
                    <button type="button" class="text-xl" :disabled="importLoading || importSaving" @click="closeImportModal">&times;</button>
                </div>

                <div class="space-y-4 overflow-y-auto p-5">
                    <div class="grid gap-3 md:grid-cols-[1fr_220px_auto]">
                        <label class="text-sm font-semibold text-gray-700">
                            Excel-Datei
                            <input type="file" accept=".xlsx,.xls" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" @change="importForm.file = $event.target.files[0] || null" />
                        </label>
                        <label class="text-sm font-semibold text-gray-700">
                            Zielkalender
                            <select v-model="importForm.calendar_id" class="mt-1 w-full rounded border-gray-300 text-sm">
                                <option v-for="calendar in calendars" :key="calendar.id" :value="calendar.id">{{ calendar.name }}</option>
                            </select>
                        </label>
                        <div class="flex items-end">
                            <button type="button" class="w-full rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="importLoading || !importForm.file" @click="previewCalendarImport">
                                {{ importLoading ? 'Prueft ...' : 'Vorschau erstellen' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="importSummary" class="grid gap-2 text-sm sm:grid-cols-4">
                        <div class="rounded border bg-gray-50 p-3"><strong>{{ importSummary.total }}</strong><br />erkannt</div>
                        <div class="rounded border bg-gray-50 p-3"><strong>{{ selectedImportKeys.length }}</strong><br />ausgewählt</div>
                        <div class="rounded border bg-gray-50 p-3"><strong>{{ importSummary.weekend }}</strong><br />Wochenende</div>
                        <div class="rounded border bg-gray-50 p-3"><strong>{{ importSummary.holiday }}</strong><br />Feiertage</div>
                    </div>

                    <div v-if="importPreviewEvents.length" class="flex flex-wrap gap-2">
                        <button type="button" class="rounded border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700" @click="selectImportKind('all')">Alle erlaubten auswählen</button>
                        <button type="button" class="rounded border border-orange-200 px-3 py-2 text-sm font-semibold text-orange-600" @click="selectImportKind('is_weekend')">Wochenenden auch auswählen</button>
                        <button type="button" class="rounded border border-red-200 px-3 py-2 text-sm font-semibold text-red-600" @click="selectImportKind('is_holiday')">Feiertage auch auswählen</button>
                        <button type="button" class="rounded border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700" @click="clearImportSelection">Auswahl leeren</button>
                    </div>

                    <div v-if="importPreviewEvents.length" class="overflow-hidden rounded border">
                        <div class="max-h-[42vh] overflow-y-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="sticky top-0 bg-gray-100 text-left text-xs font-semibold uppercase text-gray-600">
                                    <tr>
                                        <th class="w-12 px-3 py-2"></th>
                                        <th class="px-3 py-2">Datum</th>
                                        <th class="px-3 py-2">Farbe</th>
                                        <th class="px-3 py-2">Titel</th>
                                        <th class="px-3 py-2">Hinweis</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="event in importPreviewEvents" :key="event.key" :class="event.duplicate ? 'bg-red-50 text-red-900' : event.is_holiday ? 'bg-orange-50' : event.is_weekend ? 'bg-gray-50' : 'bg-white'">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" class="rounded border-gray-300 text-blue-600" :disabled="event.duplicate" :checked="importKeySelected(event.key)" @change="toggleImportKey(event.key)" />
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ event.weekday }} {{ event.date }}</td>
                                        <td class="px-3 py-2">
                                            <span
                                                class="inline-flex h-5 min-w-14 items-center justify-center rounded px-2 text-[11px] font-semibold"
                                                :style="{ backgroundColor: event.background_color || '#f3f4f6', color: event.text_color || '#374151' }"
                                            >
                                                {{ event.background_color || '-' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 font-semibold">{{ event.title }}</td>
                                        <td class="px-3 py-2 text-xs">
                                            <span v-if="event.duplicate" class="rounded bg-red-100 px-2 py-1 font-semibold text-red-700">Duplikat</span>
                                            <span v-else-if="event.is_holiday" class="rounded bg-orange-100 px-2 py-1 font-semibold text-orange-700">Feiertag</span>
                                            <span v-else-if="event.is_weekend" class="rounded bg-gray-200 px-2 py-1 font-semibold text-gray-700">Wochenende</span>
                                            <span v-else class="text-gray-400">OK</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t bg-gray-50 px-5 py-4">
                    <p class="text-xs text-gray-500">Wochenenden und Feiertage werden standardmäßig nicht importiert. Du kannst sie oben bewusst auswählen.</p>
                    <button type="button" class="rounded bg-orange-500 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="importSaving || selectedImportKeys.length === 0" @click="confirmCalendarImport">
                        {{ importSaving ? 'Importiert ...' : 'Auswahl importieren' }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="toast.show"
            class="fixed right-4 top-4 z-[100] rounded border px-4 py-3 text-sm font-semibold shadow-lg"
            :class="toast.type === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'"
            role="status"
        >
            {{ toast.message }}
        </div>
    </AppLayout>
</template>

<style scoped>
#year-calendar-board:fullscreen {
    width: 100vw;
    height: 100vh;
    overflow: hidden;
    background: #ffffff;
    color: #111827;
    padding: 0.5rem;
}

#year-calendar-board:-webkit-full-screen {
    width: 100vw;
    height: 100vh;
    overflow: hidden;
    background: #ffffff;
    color: #111827;
    padding: 0.5rem;
}
</style>
