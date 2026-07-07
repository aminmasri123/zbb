<script setup>
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  gruppen: { type: Array, default: () => [] },
  klassenbuecher: { type: Array, default: () => [] },
  pruefungen: { type: Array, default: () => [] },
  typen: { type: Array, default: () => [] },
  selectedGruppeId: { type: [Number, String], default: null },
  canReview: { type: Boolean, default: false },
})

const form = useForm({
  gruppe_id: props.selectedGruppeId || props.gruppen[0]?.id || '',
  klassenbuch_typ_id: props.typen[0]?.id || '',
  titel: '',
  schuljahr: '',
  lehrjahr: '',
})

const selectedGruppe = computed(() =>
  props.gruppen.find((gruppe) => Number(gruppe.id) === Number(form.gruppe_id))
)

const selectedTyp = computed(() =>
  props.typen.find((typ) => Number(typ.id) === Number(form.klassenbuch_typ_id))
)

watch([selectedGruppe, selectedTyp], () => {
  if (!selectedGruppe.value || !selectedTyp.value || form.titel) return

  const bereich = selectedGruppe.value.bereich?.name || `Gruppe ${selectedGruppe.value.id}`
  form.titel = `${selectedTyp.value.name} - ${bereich}`
})

const stats = computed(() => {
  const base = {
    gesamt: props.klassenbuecher.length,
    pruefung: 0,
    korrektur: 0,
    gesperrt: 0,
  }

  props.klassenbuecher.forEach((buch) => {
    base.pruefung += Number(buch.pruefung_wochen_count || 0)
    base.gesperrt += Number(buch.gesperrte_wochen_count || 0)
    base.korrektur += Number(buch.korrektur_wochen_count || 0)
  })

  return base
})

function createKlassenbuch() {
  form.post(route('klassenbuch.store'), {
    preserveScroll: true,
  })
}

function dateLabel(value) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('de-DE')
}

function statusClass(status) {
  return {
    offen: 'bg-slate-100 text-slate-700 border-slate-200',
    aktiv: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    eingereicht: 'bg-sky-50 text-sky-700 border-sky-200',
    korrektur: 'bg-amber-50 text-amber-700 border-amber-200',
    gesperrt: 'bg-zinc-100 text-zinc-700 border-zinc-300',
  }[status] || 'bg-slate-100 text-slate-700 border-slate-200'
}
</script>

<template>
  <Head title="Klassenbücher" />

  <AppLayout title="Klassenbücher">
    <template #header>Klassenbücher</template>

    <div class="mx-auto max-w-7xl space-y-6 pb-10">
      <section class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-zbb">Digitales Klassenbuch</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-900">Aus Gruppen werden prüfbare Klassenbücher</h1>
            <p class="mt-2 max-w-3xl text-sm text-zinc-600">
              Gruppen bleiben die organisatorische Basis. Das Klassenbuch dokumentiert Wochenberichte, Kommentare,
              Freigaben und Sperrungen getrennt nach Fachpraxis, Unterricht oder weiteren Typen.
            </p>
          </div>

          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-zinc-200 bg-zinc-50 px-4 py-3">
              <span class="text-xs text-zinc-500">Bücher</span>
              <strong class="block text-xl text-zinc-900">{{ stats.gesamt }}</strong>
            </div>
            <div class="rounded border border-sky-200 bg-sky-50 px-4 py-3">
              <span class="text-xs text-sky-700">Prüfung</span>
              <strong class="block text-xl text-sky-900">{{ stats.pruefung }}</strong>
            </div>
            <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3">
              <span class="text-xs text-amber-700">Korrektur</span>
              <strong class="block text-xl text-amber-900">{{ stats.korrektur }}</strong>
            </div>
            <div class="rounded border border-zinc-300 bg-zinc-100 px-4 py-3">
              <span class="text-xs text-zinc-600">Gesperrt</span>
              <strong class="block text-xl text-zinc-900">{{ stats.gesperrt }}</strong>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[minmax(320px,420px)_1fr]">
        <form class="rounded border border-zinc-200 bg-white p-5 shadow-sm" @submit.prevent="createKlassenbuch">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900">Klassenbuch anlegen</h2>
            <i class="las la-book-open text-2xl text-zbb"></i>
          </div>

          <div class="mt-5 space-y-4">
            <label class="block">
              <span class="text-sm font-medium text-zinc-700">Gruppe</span>
              <select v-model="form.gruppe_id" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="" disabled>Gruppe auswählen</option>
                <option v-for="gruppe in gruppen" :key="gruppe.id" :value="gruppe.id">
                  {{ gruppe.bereich?.name || `Gruppe ${gruppe.id}` }} - {{ dateLabel(gruppe.anfangsdatum) }}
                </option>
              </select>
              <span v-if="form.errors.gruppe_id" class="mt-1 block text-xs text-red-600">{{ form.errors.gruppe_id }}</span>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-zinc-700">Art</span>
              <select v-model="form.klassenbuch_typ_id" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="" disabled>Art auswählen</option>
                <option v-for="typ in typen" :key="typ.id" :value="typ.id">{{ typ.name }}</option>
              </select>
              <span v-if="form.errors.klassenbuch_typ_id" class="mt-1 block text-xs text-red-600">{{ form.errors.klassenbuch_typ_id }}</span>
            </label>

            <div class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="text-sm font-medium text-zinc-700">Schuljahr</span>
                <input v-model="form.schuljahr" type="text" placeholder="2026/2027" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" />
              </label>
              <label class="block">
                <span class="text-sm font-medium text-zinc-700">Lehrjahr</span>
                <input v-model="form.lehrjahr" type="number" min="1" max="6" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" />
              </label>
            </div>

            <label class="block">
              <span class="text-sm font-medium text-zinc-700">Titel</span>
              <input v-model="form.titel" type="text" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" />
              <span v-if="form.errors.titel" class="mt-1 block text-xs text-red-600">{{ form.errors.titel }}</span>
            </label>

            <div v-if="selectedGruppe" class="rounded border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-600">
              <div class="flex items-center gap-2">
                <i class="las la-users text-lg text-zbb"></i>
                <span>{{ selectedGruppe.teilnehmer_count || 0 }} Teilnehmenden</span>
              </div>
              <div class="mt-1 flex items-center gap-2">
                <i class="las la-user-tie text-lg text-zbb"></i>
                <span>{{ selectedGruppe.betreuer?.vorname }} {{ selectedGruppe.betreuer?.nachname }}</span>
              </div>
            </div>
          </div>

          <button
            type="submit"
            :disabled="form.processing || !form.gruppe_id || !form.klassenbuch_typ_id"
            class="mt-5 inline-flex h-10 w-full items-center justify-center gap-2 rounded bg-zbb px-4 text-sm font-semibold text-white hover:bg-zbb/90 disabled:cursor-not-allowed disabled:bg-zinc-300"
          >
            <i class="las la-plus"></i>
            Klassenbuch erstellen
          </button>
        </form>

        <div class="space-y-6">
          <section v-if="canReview" class="rounded border border-sky-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-zinc-900">Wartet auf Prüfung</h2>
              <span class="rounded border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">{{ pruefungen.length }}</span>
            </div>
            <div v-if="pruefungen.length" class="mt-4 divide-y divide-zinc-100">
              <Link
                v-for="woche in pruefungen"
                :key="woche.id"
                :href="route('klassenbuch.woche.show', [woche.klassenbuch.id, woche.id])"
                class="flex items-center justify-between gap-4 py-3 hover:bg-sky-50"
              >
                <div>
                  <p class="font-medium text-zinc-900">
                    KW {{ woche.kalenderwoche }} - {{ woche.klassenbuch?.gruppe?.bereich?.name }}
                  </p>
                  <p class="text-xs text-zinc-500">{{ woche.klassenbuch?.titel }}</p>
                </div>
                <i class="las la-arrow-right text-xl text-sky-600"></i>
              </Link>
            </div>
            <p v-else class="mt-4 text-sm text-zinc-500">Aktuell wartet keine Woche auf Prüfung.</p>
          </section>

          <section class="rounded border border-zinc-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4">
              <h2 class="text-lg font-semibold text-zinc-900">Vorhandene Klassenbücher</h2>
              <span class="text-sm text-zinc-500">{{ klassenbuecher.length }} Einträge</span>
            </div>

            <div v-if="klassenbuecher.length" class="divide-y divide-zinc-100">
              <Link
                v-for="buch in klassenbuecher"
                :key="buch.id"
                :href="route('klassenbuch.show', buch.id)"
                class="grid gap-4 px-5 py-4 transition hover:bg-zinc-50 lg:grid-cols-[1fr_auto]"
              >
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-zinc-900">{{ buch.titel }}</h3>
                    <span class="rounded border px-2 py-0.5 text-xs font-semibold" :class="statusClass(buch.status)">
                      {{ buch.status }}
                    </span>
                  </div>
                  <p class="mt-1 text-sm text-zinc-600">
                    {{ buch.gruppe?.projekt?.name }} / {{ buch.gruppe?.bereich?.name }} / {{ buch.typ?.name }}
                  </p>
                  <p class="mt-1 text-xs text-zinc-500">
                    {{ buch.schuljahr || 'ohne Schuljahr' }} <span v-if="buch.lehrjahr">- Lehrjahr {{ buch.lehrjahr }}</span>
                  </p>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center">
                  <div class="rounded border border-zinc-200 px-3 py-2">
                    <span class="block text-xs text-zinc-500">Offen</span>
                    <strong class="text-zinc-900">{{ buch.offene_wochen_count }}</strong>
                  </div>
                  <div class="rounded border border-sky-200 px-3 py-2">
                    <span class="block text-xs text-sky-600">Prüfung</span>
                    <strong class="text-sky-800">{{ buch.pruefung_wochen_count }}</strong>
                  </div>
                  <div class="rounded border border-zinc-300 px-3 py-2">
                    <span class="block text-xs text-zinc-500">Gesperrt</span>
                    <strong class="text-zinc-900">{{ buch.gesperrte_wochen_count }}</strong>
                  </div>
                </div>
              </Link>
            </div>

            <div v-else class="px-5 py-12 text-center text-zinc-500">
              <i class="las la-book text-5xl text-zinc-300"></i>
              <p class="mt-2 font-medium">Noch keine Klassenbücher vorhanden</p>
              <p class="text-sm">Wähle links eine Gruppe und eine Art aus.</p>
            </div>
          </section>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
