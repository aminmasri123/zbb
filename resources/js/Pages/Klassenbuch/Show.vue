<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  klassenbuch: { type: Object, required: true },
  wochen: { type: Array, default: () => [] },
  teilnehmer: { type: Array, default: () => [] },
  canReview: { type: Boolean, default: false },
})

const statusCounts = computed(() => {
  const counts = { offen: 0, eingereicht: 0, korrektur: 0, gesperrt: 0 }
  props.wochen.forEach((woche) => {
    counts[woche.status] = (counts[woche.status] || 0) + 1
  })
  return counts
})

const nextWeek = computed(() =>
  props.wochen.find((woche) => ['offen', 'korrektur', 'eingereicht'].includes(woche.status)) || props.wochen[0]
)

function dateLabel(value) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('de-DE')
}

function statusClass(status) {
  return {
    offen: 'bg-white text-zinc-700 border-zinc-300',
    eingereicht: 'bg-sky-50 text-sky-700 border-sky-200',
    korrektur: 'bg-amber-50 text-amber-700 border-amber-200',
    gesperrt: 'bg-zinc-100 text-zinc-700 border-zinc-300',
  }[status] || 'bg-white text-zinc-700 border-zinc-300'
}
</script>

<template>
  <Head :title="klassenbuch.titel" />

  <AppLayout :title="klassenbuch.titel">
    <template #header>{{ klassenbuch.titel }}</template>

    <div class="mx-auto max-w-7xl space-y-6 pb-10">
      <section class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <Link :href="route('klassenbuch.index')" class="inline-flex items-center gap-2 text-sm font-medium text-zbb hover:underline">
              <i class="las la-arrow-left"></i>
              Zurück zur Übersicht
            </Link>
            <div class="mt-4 flex flex-wrap items-center gap-3">
              <h1 class="text-2xl font-semibold text-zinc-900">{{ klassenbuch.titel }}</h1>
              <span class="rounded border border-zbb/20 bg-zbb/10 px-3 py-1 text-xs font-semibold text-zbb">
                {{ klassenbuch.typ?.name }}
              </span>
            </div>
            <p class="mt-2 text-sm text-zinc-600">
              {{ klassenbuch.gruppe?.projekt?.name }} / {{ klassenbuch.gruppe?.bereich?.name }}
            </p>
          </div>

          <Link
            v-if="nextWeek"
            :href="route('klassenbuch.woche.show', [klassenbuch.id, nextWeek.id])"
            class="inline-flex h-10 items-center justify-center gap-2 rounded bg-zbb px-4 text-sm font-semibold text-white hover:bg-zbb/90"
          >
            <i class="las la-calendar-week"></i>
            Nächste Woche bearbeiten
          </Link>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-semibold text-zinc-900">Deckblatt</h2>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded border border-zinc-200 bg-zinc-50 p-3">
              <span class="text-xs uppercase text-zinc-500">Projekt</span>
              <p class="font-medium text-zinc-900">{{ klassenbuch.gruppe?.projekt?.name }}</p>
            </div>
            <div class="rounded border border-zinc-200 bg-zinc-50 p-3">
              <span class="text-xs uppercase text-zinc-500">Abteilung</span>
              <p class="font-medium text-zinc-900">{{ klassenbuch.gruppe?.projekt?.abteilung?.name || '-' }}</p>
            </div>
            <div class="rounded border border-zinc-200 bg-zinc-50 p-3">
              <span class="text-xs uppercase text-zinc-500">Bereich / Gewerk</span>
              <p class="font-medium text-zinc-900">{{ klassenbuch.gruppe?.bereich?.name || '-' }}</p>
            </div>
            <div class="rounded border border-zinc-200 bg-zinc-50 p-3">
              <span class="text-xs uppercase text-zinc-500">Zeitraum</span>
              <p class="font-medium text-zinc-900">
                {{ dateLabel(klassenbuch.gruppe?.anfangsdatum) }} - {{ dateLabel(klassenbuch.gruppe?.enddatum) }}
              </p>
            </div>
            <div class="rounded border border-zinc-200 bg-zinc-50 p-3">
              <span class="text-xs uppercase text-zinc-500">Betreuer</span>
              <p class="font-medium text-zinc-900">
                {{ klassenbuch.gruppe?.betreuer?.vorname }} {{ klassenbuch.gruppe?.betreuer?.nachname }}
              </p>
            </div>
            <div class="rounded border border-zinc-200 bg-zinc-50 p-3">
              <span class="text-xs uppercase text-zinc-500">Schuljahr / Lehrjahr</span>
              <p class="font-medium text-zinc-900">
                {{ klassenbuch.schuljahr || '-' }} <span v-if="klassenbuch.lehrjahr">/ {{ klassenbuch.lehrjahr }}</span>
              </p>
            </div>
          </div>
        </div>

        <div class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-semibold text-zinc-900">Status</h2>
          <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded border border-zinc-200 p-3">
              <span class="text-xs text-zinc-500">Offen</span>
              <strong class="block text-2xl text-zinc-900">{{ statusCounts.offen }}</strong>
            </div>
            <div class="rounded border border-sky-200 bg-sky-50 p-3">
              <span class="text-xs text-sky-700">In Prüfung</span>
              <strong class="block text-2xl text-sky-900">{{ statusCounts.eingereicht }}</strong>
            </div>
            <div class="rounded border border-amber-200 bg-amber-50 p-3">
              <span class="text-xs text-amber-700">Korrektur</span>
              <strong class="block text-2xl text-amber-900">{{ statusCounts.korrektur }}</strong>
            </div>
            <div class="rounded border border-zinc-300 bg-zinc-100 p-3">
              <span class="text-xs text-zinc-600">Gesperrt</span>
              <strong class="block text-2xl text-zinc-900">{{ statusCounts.gesperrt }}</strong>
            </div>
          </div>

          <p class="mt-4 rounded border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-600">
            Eine Woche wird nach der Prüfung gesperrt. Korrektur öffnet genau diese Woche wieder für den Ausbilder
            oder die Lehrkraft.
          </p>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="rounded border border-zinc-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4">
            <h2 class="text-lg font-semibold text-zinc-900">Wochenberichte</h2>
            <span class="text-sm text-zinc-500">{{ wochen.length }} Kalenderwochen</span>
          </div>

          <div class="grid gap-3 p-5 md:grid-cols-2 2xl:grid-cols-3">
            <Link
              v-for="woche in wochen"
              :key="woche.id"
              :href="route('klassenbuch.woche.show', [klassenbuch.id, woche.id])"
              class="rounded border p-4 transition hover:-translate-y-0.5 hover:shadow-md"
              :class="statusClass(woche.status)"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold">KW {{ woche.kalenderwoche }} / {{ woche.jahr }}</p>
                  <p class="mt-1 text-xs opacity-80">{{ dateLabel(woche.start_datum) }} - {{ dateLabel(woche.end_datum) }}</p>
                </div>
                <i v-if="woche.status === 'gesperrt'" class="las la-lock text-xl"></i>
                <i v-else-if="woche.status === 'eingereicht'" class="las la-paper-plane text-xl"></i>
                <i v-else-if="woche.status === 'korrektur'" class="las la-exclamation-circle text-xl"></i>
                <i v-else class="las la-pen text-xl"></i>
              </div>
              <div class="mt-4 flex items-center justify-between text-xs">
                <span>{{ woche.eintraege_count }} Einträge</span>
                <span>{{ woche.kommentare_count }} Kommentare</span>
              </div>
            </Link>
          </div>
        </div>

        <aside class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900">Azubi-Liste</h2>
            <span class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600">{{ teilnehmer.length }}</span>
          </div>

          <div v-if="teilnehmer.length" class="mt-4 max-h-[520px] overflow-y-auto rounded border border-zinc-200">
            <div
              v-for="person in teilnehmer"
              :key="person.id"
              class="grid grid-cols-[44px_1fr] border-b border-zinc-100 last:border-b-0"
            >
              <span class="bg-zinc-50 px-3 py-2 text-sm font-semibold text-zinc-600">{{ person.nr }}</span>
              <span class="px-3 py-2 text-sm text-zinc-800">{{ person.name }}</span>
            </div>
          </div>

          <p v-else class="mt-4 rounded border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-500">
            In dieser Gruppe sind noch keine Teilnehmer hinterlegt.
          </p>
        </aside>
      </section>
    </div>
  </AppLayout>
</template>
