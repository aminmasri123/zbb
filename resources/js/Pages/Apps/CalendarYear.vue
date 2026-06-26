<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
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

const monthNames = ['Januar', 'Februar', 'Maerz', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
const currentYear = ref(props.year);
const calendarItems = ref(props.items || []);
const loadingYear = ref(false);
const savingEvent = ref(false);
const fullscreen = ref(false);
const selectedCalendar = ref('all');
const showModal = ref(false);
const editingEvent = ref(null);
const editingDay = ref(null);
const dragEvent = ref(null);
const selectingDays = ref(false);
const selectionStart = ref(null);
const selectionEnd = ref(null);
const selectionMoved = ref(false);

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

const filteredEvents = computed(() => {
    if (selectedCalendar.value === 'all') return calendarItems.value;
    return calendarItems.value.filter((event) => String(event.calendar_id || '') === String(selectedCalendar.value));
});

const months = computed(() => monthNames.map((name, index) => ({
    name,
    index,
    days: Array.from({ length: daysInMonth(currentYear.value, index) }, (_, dayIndex) => buildDay(currentYear.value, index, dayIndex + 1)),
})));

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
    if (!value) return '';
    return String(value).slice(0, 16);
}

function dayEvents(day) {
    return filteredEvents.value
        .filter((event) => eventTouchesDay(event, day.iso))
        .sort((a, b) => String(a.starts_at).localeCompare(String(b.starts_at)));
}

function eventTouchesDay(event, iso) {
    const start = String(event.starts_at).slice(0, 10);
    const end = String(event.ends_at || event.starts_at).slice(0, 10);

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
    eventForm.reset();
    eventForm.clearErrors();
    eventForm.all_day = true;
    eventForm.include_weekends = false;
    eventForm.excluded_dates = [];
    eventForm.background_color = '#ff7a00';
    eventForm.text_color = '#ffffff';
    eventForm.visibility = 'private';
    eventForm.starts_at = `${startIso}T08:00`;
    eventForm.ends_at = `${endIso}T16:00`;
    showModal.value = true;
}

function openEdit(event, day = null) {
    editingEvent.value = event;
    editingDay.value = day?.iso || null;
    eventForm.clearErrors();
    eventForm.title = event.title || '';
    eventForm.calendar_id = event.calendar_id || '';
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
        await axios({
            method,
            url,
            data,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (options.closeModal !== false) {
            showModal.value = false;
        }

        await loadYear(currentYear.value);
    } catch (error) {
        if (error.response?.status === 422 && error.response.data?.errors) {
            eventForm.setError(error.response.data.errors);
        } else {
            console.error(error);
            alert('Die Kalenderaenderung konnte nicht gespeichert werden.');
        }
    } finally {
        savingEvent.value = false;
    }
}

function saveEvent() {
    if (editingEvent.value) {
        calendarRequest('put', route('apps.calendar.update', editingEvent.value.id), eventFormPayload());
    } else {
        calendarRequest('post', route('apps.calendar.store'), eventFormPayload());
    }
}

function deleteEvent() {
    if (!editingEvent.value || !confirm('Termin wirklich loeschen?')) return;
    calendarRequest('delete', route('apps.calendar.destroy', editingEvent.value.id));
}

function canRemoveSingleDay() {
    if (!editingEvent.value || !editingDay.value) return false;

    const start = String(editingEvent.value.starts_at).slice(0, 10);
    const end = String(editingEvent.value.ends_at || editingEvent.value.starts_at).slice(0, 10);

    return start !== end && editingDay.value >= start && editingDay.value <= end;
}

function removeSingleDay() {
    if (!canRemoveSingleDay()) return;

    const excludedDates = Array.from(new Set([...(editingEvent.value.excluded_dates || []), editingDay.value])).sort();

    calendarRequest('put', route('apps.calendar.update', editingEvent.value.id), {
        title: editingEvent.value.title,
        calendar_id: editingEvent.value.calendar_id || '',
        description: editingEvent.value.description || '',
        starts_at: toDateTimeInput(editingEvent.value.starts_at),
        ends_at: toDateTimeInput(editingEvent.value.ends_at || editingEvent.value.starts_at),
        all_day: Boolean(editingEvent.value.all_day),
        include_weekends: Boolean(editingEvent.value.include_weekends),
        excluded_dates: excludedDates,
        location: editingEvent.value.location || '',
        background_color: editingEvent.value.background_color || editingEvent.value.calendar?.background_color || '#ff7a00',
        text_color: editingEvent.value.text_color || editingEvent.value.calendar?.text_color || '#ffffff',
        visibility: editingEvent.value.visibility || 'private',
        project_id: editingEvent.value.project_id || '',
        team_id: editingEvent.value.team_id || '',
    });
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

function startDrag(event) {
    dragEvent.value = event;
}

function dropOnDay(day) {
    if (!dragEvent.value) return;

    const event = dragEvent.value;
    const start = new Date(String(event.starts_at).slice(0, 10));
    const end = new Date(String(event.ends_at || event.starts_at).slice(0, 10));
    const duration = Math.max(0, Math.round((end - start) / 86400000));
    const nextStart = new Date(day.iso);
    const nextEnd = new Date(nextStart);
    nextEnd.setDate(nextEnd.getDate() + duration);

    calendarRequest('put', route('apps.calendar.update', event.id), {
        title: event.title,
        calendar_id: event.calendar_id || '',
        description: event.description || '',
        starts_at: `${toDateInput(nextStart)}T${String(event.starts_at).slice(11, 16) || '08:00'}`,
        ends_at: `${toDateInput(nextEnd)}T${String(event.ends_at || event.starts_at).slice(11, 16) || '16:00'}`,
        all_day: Boolean(event.all_day),
        include_weekends: Boolean(event.include_weekends),
        excluded_dates: event.excluded_dates || [],
        location: event.location || '',
        background_color: event.background_color || event.calendar?.background_color || '#ff7a00',
        text_color: event.text_color || event.calendar?.text_color || '#ffffff',
        visibility: event.visibility || 'private',
        project_id: event.project_id || '',
        team_id: event.team_id || '',
    }, { closeModal: false });

    dragEvent.value = null;
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
        alert('Das Jahr konnte nicht geladen werden.');
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

    const el = document.querySelector('#year-calendar-board');
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
                    <select v-model="selectedCalendar" class="h-9 rounded border-gray-300 text-sm font-normal">
                        <option value="all">Alle Kalender</option>
                        <option v-for="calendar in calendars" :key="calendar.id" :value="calendar.id">{{ calendar.name }}</option>
                    </select>
                    <button class="inline-flex h-9 items-center rounded border bg-white px-3 text-sm font-normal disabled:opacity-50" :disabled="loadingYear" @click="loadYear(currentYear - 1)">&lt;&lt;</button>
                    <span class="px-2 text-2xl font-semibold">{{ currentYear }}</span>
                    <button class="inline-flex h-9 items-center rounded border bg-white px-3 text-sm font-normal disabled:opacity-50" :disabled="loadingYear" @click="loadYear(currentYear + 1)">&gt;&gt;</button>
                    <button class="h-9 rounded bg-orange-500 px-4 text-sm font-semibold text-white" @click="openCreate()">+ Event anlegen</button>
                    <button class="h-9 rounded bg-orange-500 px-3 text-sm font-semibold text-white" @click="toggleFullscreen">
                        <i class="la la-expand"></i>
                    </button>
                </div>
            </div>
        </template>

        <div :class="fullscreen ? 'fixed inset-0 z-[60] overflow-hidden bg-white p-2 text-gray-900' : 'min-h-screen bg-gray-50 py-0'">
            <div id="year-calendar-board" :class="fullscreen ? 'mx-auto flex h-full w-full max-w-none flex-col bg-white text-gray-900' : 'mx-auto w-full max-w-none'">
                <div v-if="fullscreen" class="mb-2 flex shrink-0 flex-wrap items-center justify-between gap-3 bg-white text-gray-900">
                    <h1 class="text-xl font-semibold text-gray-900">Kalender</h1>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <select v-model="selectedCalendar" class="h-9 rounded border-gray-300 text-sm">
                            <option value="all">Alle Kalender</option>
                            <option v-for="calendar in calendars" :key="calendar.id" :value="calendar.id">{{ calendar.name }}</option>
                        </select>
                        <button class="inline-flex h-9 items-center rounded border bg-white px-3 text-sm disabled:opacity-50" :disabled="loadingYear" @click="loadYear(currentYear - 1)">&lt;&lt;</button>
                        <span class="px-2 text-2xl font-semibold">{{ currentYear }}</span>
                        <button class="inline-flex h-9 items-center rounded border bg-white px-3 text-sm disabled:opacity-50" :disabled="loadingYear" @click="loadYear(currentYear + 1)">&gt;&gt;</button>
                        <button class="h-9 rounded bg-orange-500 px-4 text-sm font-semibold text-white" @click="openCreate()">+ Event anlegen</button>
                        <button class="h-9 rounded bg-orange-500 px-3 text-sm font-semibold text-white" @click="toggleFullscreen">
                            <i class="la la-expand"></i>
                        </button>
                    </div>
                </div>

                <div :class="fullscreen ? 'min-h-0 flex-1 space-y-3' : 'mb-4 space-y-3'">
                    <div :class="fullscreen ? 'flex h-full flex-col overflow-hidden rounded border border-gray-200 bg-white p-3 text-gray-900 shadow-sm' : 'overflow-hidden rounded border border-gray-200 bg-white p-3 shadow-sm'">
                        <div class="mb-2 flex flex-wrap gap-2">
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
                                        @dragstart="startDrag(event)"
                                        @click.stop="openEdit(event, day)"
                                    >
                                        {{ event.title }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <form class="w-full max-w-2xl rounded bg-white p-5 shadow-xl" @submit.prevent="saveEvent">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">{{ editingEvent ? 'Event bearbeiten' : 'Event anlegen' }}</h2>
                    <button type="button" class="text-xl" :disabled="savingEvent" @click="showModal = false">&times;</button>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <input v-model="eventForm.title" class="rounded border-gray-300 text-sm md:col-span-2" placeholder="Bezeichnung" />
                    <select v-model="eventForm.calendar_id" class="rounded border-gray-300 text-sm">
                        <option value="">Mein Kalender</option>
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
                    <select v-if="eventForm.visibility === 'project'" v-model="eventForm.project_id" class="rounded border-gray-300 text-sm md:col-span-2">
                        <option value="">Aktuelles Projekt</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select>
                </div>
                <div class="mt-5 flex justify-between">
                    <div class="flex flex-wrap gap-2">
                        <button v-if="canRemoveSingleDay()" type="button" class="rounded border border-orange-200 px-4 py-2 text-sm font-semibold text-orange-600 disabled:opacity-50" :disabled="savingEvent" @click="removeSingleDay">Nur diesen Tag entfernen</button>
                        <button v-if="editingEvent" type="button" class="rounded border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 disabled:opacity-50" :disabled="savingEvent" @click="deleteEvent">Ganzes Event loeschen</button>
                    </div>
                    <button class="rounded bg-orange-500 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="savingEvent">{{ savingEvent ? 'Speichert ...' : 'Speichern' }}</button>
                </div>
            </form>
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
