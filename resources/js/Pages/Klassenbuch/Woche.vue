<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  klassenbuch: { type: Object, required: true },
  woche: { type: Object, required: true },
  wochentage: { type: Array, default: () => [] },
  teilnehmer: { type: Array, default: () => [] },
  canReview: { type: Boolean, default: false },
})

const entryForm = useForm({
  id: null,
  datum: props.wochentage[0]?.datum || '',
  stunde: '',
  fach: props.klassenbuch.typ?.name || '',
  thema: '',
  azubi_nummern: '',
  signum: '',
  bemerkung: '',
})

const reviewForm = useForm({
  entscheidung: 'ok',
  kommentar: '',
  intern: false,
})

const commentForm = useForm({
  text: '',
  typ: 'kommentar',
  intern: false,
})

const editingCommentId = ref(null)
const commentEditForm = useForm({
  text: '',
})

const canEditEntries = computed(() => ['offen', 'korrektur'].includes(props.woche.status))
const isSubmitted = computed(() => props.woche.status === 'eingereicht')
const isLocked = computed(() => props.woche.status === 'gesperrt')

const visibleComments = computed(() =>
  props.woche.kommentare || []
)

function dateLabel(value) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('de-DE')
}

function timeLabel(value) {
  if (!value) return ''
  return new Date(value).toLocaleString('de-DE')
}

function dateInput(value) {
  if (!value) return ''
  return String(value).slice(0, 10)
}

function statusClass(status) {
  return {
    offen: 'bg-white text-zinc-700 border-zinc-300',
    eingereicht: 'bg-sky-50 text-sky-700 border-sky-200',
    korrektur: 'bg-amber-50 text-amber-700 border-amber-200',
    gesperrt: 'bg-zinc-100 text-zinc-700 border-zinc-300',
  }[status] || 'bg-white text-zinc-700 border-zinc-300'
}

function resetEntryForm() {
  entryForm.id = null
  entryForm.datum = props.wochentage[0]?.datum || ''
  entryForm.stunde = ''
  entryForm.fach = props.klassenbuch.typ?.name || ''
  entryForm.thema = ''
  entryForm.azubi_nummern = ''
  entryForm.signum = ''
  entryForm.bemerkung = ''
  entryForm.clearErrors()
}

function editEintrag(eintrag) {
  entryForm.id = eintrag.id
  entryForm.datum = dateInput(eintrag.datum)
  entryForm.stunde = eintrag.stunde || ''
  entryForm.fach = eintrag.fach || ''
  entryForm.thema = eintrag.thema || ''
  entryForm.azubi_nummern = eintrag.azubi_nummern || ''
  entryForm.signum = eintrag.signum || ''
  entryForm.bemerkung = eintrag.bemerkung || ''
}

function saveEintrag() {
  entryForm.post(route('klassenbuch.eintrag.store', [props.klassenbuch.id, props.woche.id]), {
    preserveScroll: true,
    onSuccess: () => resetEntryForm(),
  })
}

function deleteEintrag(eintrag) {
  router.delete(route('klassenbuch.eintrag.destroy', [props.klassenbuch.id, props.woche.id, eintrag.id]), {
    preserveScroll: true,
  })
}

function submitWoche() {
  router.post(route('klassenbuch.woche.submit', [props.klassenbuch.id, props.woche.id]), {}, {
    preserveScroll: true,
  })
}

function reviewWoche(decision) {
  reviewForm.entscheidung = decision
  reviewForm.post(route('klassenbuch.woche.review', [props.klassenbuch.id, props.woche.id]), {
    preserveScroll: true,
    onSuccess: () => {
      reviewForm.entscheidung = 'ok'
      reviewForm.kommentar = ''
      reviewForm.intern = false
    },
  })
}

function saveKommentar() {
  commentForm.post(route('klassenbuch.kommentar.store', [props.klassenbuch.id, props.woche.id]), {
    preserveScroll: true,
    onSuccess: () => {
      commentForm.text = ''
      commentForm.typ = 'kommentar'
      commentForm.intern = false
    },
  })
}

function editKommentar(kommentar) {
  editingCommentId.value = kommentar.id
  commentEditForm.text = kommentar.text
  commentEditForm.clearErrors()
}

function cancelKommentarEdit() {
  editingCommentId.value = null
  commentEditForm.text = ''
  commentEditForm.clearErrors()
}

function updateKommentar(kommentar) {
  commentEditForm.put(route('klassenbuch.kommentar.update', [props.klassenbuch.id, props.woche.id, kommentar.id]), {
    preserveScroll: true,
    onSuccess: () => cancelKommentarEdit(),
  })
}
</script>

<template>
  <Head :title="`${klassenbuch.titel} - KW ${woche.kalenderwoche}`" />

  <AppLayout :title="`${klassenbuch.titel} - KW ${woche.kalenderwoche}`">
    <template #header>KW {{ woche.kalenderwoche }} - {{ klassenbuch.titel }}</template>

    <div class="mx-auto max-w-7xl space-y-6 pb-10">
      <section class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <Link :href="route('klassenbuch.show', klassenbuch.id)" class="inline-flex items-center gap-2 text-sm font-medium text-zbb hover:underline">
              <i class="las la-arrow-left"></i>
              Zurueck zum Klassenbuch
            </Link>
            <div class="mt-4 flex flex-wrap items-center gap-3">
              <h1 class="text-2xl font-semibold text-zinc-900">Kalenderwoche {{ woche.kalenderwoche }}</h1>
              <span class="rounded border px-3 py-1 text-xs font-semibold" :class="statusClass(woche.status)">
                {{ woche.status }}
              </span>
            </div>
            <p class="mt-2 text-sm text-zinc-600">
              {{ dateLabel(woche.start_datum) }} - {{ dateLabel(woche.end_datum) }} /
              {{ klassenbuch.gruppe?.bereich?.name }} / {{ klassenbuch.typ?.name }}
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              v-if="canEditEntries"
              type="button"
              class="inline-flex h-10 items-center justify-center gap-2 rounded bg-zbb px-4 text-sm font-semibold text-white hover:bg-zbb/90"
              @click="submitWoche"
            >
              <i class="las la-paper-plane"></i>
              Woche vollstaendig bestaetigen
            </button>
            <span v-else-if="isSubmitted" class="rounded border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-semibold text-sky-700">
              Wartet auf Prüfung
            </span>
            <span v-else-if="isLocked" class="rounded border border-zinc-300 bg-zinc-100 px-4 py-2 text-sm font-semibold text-zinc-700">
              Gesperrt
            </span>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <main class="space-y-6">
          <form v-if="canEditEntries" class="rounded border border-zinc-200 bg-white p-5 shadow-sm" @submit.prevent="saveEintrag">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-zinc-900">
                {{ entryForm.id ? 'Eintrag bearbeiten' : 'Eintrag erfassen' }}
              </h2>
              <button v-if="entryForm.id" type="button" class="text-sm font-medium text-zinc-500 hover:text-zbb" @click="resetEntryForm">
                Neu
              </button>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[140px_110px_1fr_120px]">
              <label class="block">
                <span class="text-sm font-medium text-zinc-700">Datum</span>
                <select v-model="entryForm.datum" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb">
                  <option v-for="tag in wochentage" :key="tag.datum" :value="tag.datum">
                    {{ tag.kurz }} {{ tag.label }}
                  </option>
                </select>
                <span v-if="entryForm.errors.datum" class="mt-1 block text-xs text-red-600">{{ entryForm.errors.datum }}</span>
              </label>

              <label class="block">
                <span class="text-sm font-medium text-zinc-700">Stunde</span>
                <input v-model="entryForm.stunde" type="number" min="1" max="12" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" />
              </label>

              <label class="block">
                <span class="text-sm font-medium text-zinc-700">Fach / Bereich</span>
                <input v-model="entryForm.fach" type="text" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" />
              </label>

              <label class="block">
                <span class="text-sm font-medium text-zinc-700">Signum</span>
                <input v-model="entryForm.signum" type="text" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" />
              </label>
            </div>

            <label class="mt-4 block">
              <span class="text-sm font-medium text-zinc-700">Tätigkeit / Unterrichtsgegenstand</span>
              <textarea v-model="entryForm.thema" rows="4" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb"></textarea>
              <span v-if="entryForm.errors.thema" class="mt-1 block text-xs text-red-600">{{ entryForm.errors.thema }}</span>
            </label>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
              <label class="block">
                <span class="text-sm font-medium text-zinc-700">Azubi-Nummern</span>
                <input v-model="entryForm.azubi_nummern" type="text" placeholder="z. B. 1, 3, 9" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" />
              </label>
              <label class="block">
                <span class="text-sm font-medium text-zinc-700">Bemerkung</span>
                <input v-model="entryForm.bemerkung" type="text" class="mt-1 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" />
              </label>
            </div>

            <button
              type="submit"
              :disabled="entryForm.processing"
              class="mt-4 inline-flex h-10 items-center justify-center gap-2 rounded bg-zbb px-4 text-sm font-semibold text-white hover:bg-zbb/90 disabled:bg-zinc-300"
            >
              <i class="las la-save"></i>
              Speichern
            </button>
          </form>

          <section class="rounded border border-zinc-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4">
              <h2 class="text-lg font-semibold text-zinc-900">Einträge</h2>
              <span class="text-sm text-zinc-500">{{ woche.eintraege?.length || 0 }}</span>
            </div>

            <div v-if="woche.eintraege?.length" class="overflow-x-auto">
              <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500">
                  <tr>
                    <th class="px-4 py-3">Datum</th>
                    <th class="px-4 py-3">Std.</th>
                    <th class="px-4 py-3">Fach</th>
                    <th class="px-4 py-3">Thema</th>
                    <th class="px-4 py-3">Azubis</th>
                    <th class="px-4 py-3">Signum</th>
                    <th class="px-4 py-3 text-right">Aktion</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                  <tr v-for="eintrag in woche.eintraege" :key="eintrag.id" class="align-top hover:bg-zinc-50">
                    <td class="whitespace-nowrap px-4 py-3 text-zinc-700">{{ dateLabel(eintrag.datum) }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ eintrag.stunde || '-' }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ eintrag.fach || '-' }}</td>
                    <td class="px-4 py-3">
                      <p class="font-medium text-zinc-900">{{ eintrag.thema }}</p>
                      <p v-if="eintrag.bemerkung" class="mt-1 text-xs text-zinc-500">{{ eintrag.bemerkung }}</p>
                    </td>
                    <td class="px-4 py-3 text-zinc-700">{{ eintrag.azubi_nummern || '-' }}</td>
                    <td class="px-4 py-3 text-zinc-700">{{ eintrag.signum || '-' }}</td>
                    <td class="px-4 py-3 text-right">
                      <div v-if="canEditEntries" class="inline-flex gap-2">
                        <button type="button" class="rounded border border-zinc-300 px-2 py-1 text-xs text-zinc-700 hover:border-zbb hover:text-zbb" @click="editEintrag(eintrag)">
                          Bearbeiten
                        </button>
                        <button type="button" class="rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="deleteEintrag(eintrag)">
                          Entfernen
                        </button>
                      </div>
                      <span v-else class="text-xs text-zinc-400">gesperrt</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="px-5 py-12 text-center text-zinc-500">
              <i class="las la-clipboard-list text-5xl text-zinc-300"></i>
              <p class="mt-2 font-medium">Noch keine Einträge</p>
              <p class="text-sm">Erfasse oben den ersten Wochenbericht-Eintrag.</p>
            </div>
          </section>

          <section class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Kommentare und Notizen</h2>

            <form class="mt-4 space-y-3" @submit.prevent="saveKommentar">
              <div class="grid gap-3 sm:grid-cols-[160px_1fr]">
                <select v-model="commentForm.typ" class="rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb">
                  <option value="kommentar">Kommentar</option>
                  <option value="notiz">Notiz</option>
                  <option value="korrektur">Korrektur</option>
                </select>
                <label v-if="canReview" class="inline-flex items-center gap-2 text-sm text-zinc-600">
                  <input v-model="commentForm.intern" type="checkbox" class="rounded border-zinc-300 text-zbb focus:ring-zbb" />
                  Interne Notiz nur fuer Leitung/Assistenz
                </label>
              </div>
              <textarea v-model="commentForm.text" rows="3" class="w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" placeholder="Kommentar schreiben"></textarea>
              <button type="submit" :disabled="commentForm.processing" class="inline-flex h-9 items-center gap-2 rounded border border-zbb px-3 text-sm font-semibold text-zbb hover:bg-zbb hover:text-white">
                <i class="las la-comment"></i>
                Speichern
              </button>
            </form>

            <div v-if="visibleComments.length" class="mt-5 space-y-3">
              <div
                v-for="kommentar in visibleComments"
                :key="kommentar.id"
                class="rounded border p-3"
                :class="kommentar.intern ? 'border-violet-200 bg-violet-50' : kommentar.typ === 'korrektur' ? 'border-amber-200 bg-amber-50' : 'border-zinc-200 bg-zinc-50'"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <span class="text-sm font-semibold text-zinc-800">
                    {{ kommentar.user?.person?.vorname }} {{ kommentar.user?.person?.nachname }}
                    <span v-if="!kommentar.user?.person" class="text-zinc-500">System</span>
                  </span>
                  <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-500">
                      {{ kommentar.typ }} <span v-if="kommentar.intern">/ intern</span> / {{ timeLabel(kommentar.created_at) }}
                    </span>
                    <button
                      v-if="canReview || kommentar.user_id === $page.props.auth.user.id"
                      type="button"
                      class="rounded border border-zinc-300 px-2 py-1 text-xs text-zinc-600 hover:border-zbb hover:text-zbb"
                      @click="editKommentar(kommentar)"
                    >
                      Bearbeiten
                    </button>
                  </div>
                </div>
                <form v-if="editingCommentId === kommentar.id" class="mt-3 space-y-2" @submit.prevent="updateKommentar(kommentar)">
                  <textarea v-model="commentEditForm.text" rows="3" class="w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb"></textarea>
                  <div class="flex gap-2">
                    <button type="submit" class="rounded bg-zbb px-3 py-1.5 text-xs font-semibold text-white hover:bg-zbb/90">Speichern</button>
                    <button type="button" class="rounded border border-zinc-300 px-3 py-1.5 text-xs text-zinc-600 hover:bg-zinc-50" @click="cancelKommentarEdit">Abbrechen</button>
                  </div>
                </form>
                <p v-else class="mt-2 whitespace-pre-line text-sm text-zinc-700">{{ kommentar.text }}</p>
                <p v-if="kommentar.edited_at" class="mt-2 text-xs text-zinc-500">bearbeitet: {{ timeLabel(kommentar.edited_at) }}</p>
              </div>
            </div>
            <p v-else class="mt-4 text-sm text-zinc-500">Noch keine Kommentare vorhanden.</p>
          </section>
        </main>

        <aside class="space-y-6">
          <section v-if="canReview && isSubmitted" class="rounded border border-sky-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Prüfung</h2>
            <p class="mt-2 text-sm text-zinc-600">
              OK sperrt diese Kalenderwoche endgueltig. Korrektur oeffnet sie wieder fuer die Bearbeitung.
            </p>
            <textarea v-model="reviewForm.kommentar" rows="4" class="mt-4 w-full rounded border-zinc-300 text-sm focus:border-zbb focus:ring-zbb" placeholder="Rueckmeldung oder interne Notiz"></textarea>
            <label class="mt-3 inline-flex items-center gap-2 text-sm text-zinc-600">
              <input v-model="reviewForm.intern" type="checkbox" class="rounded border-zinc-300 text-zbb focus:ring-zbb" />
              Kommentar intern speichern
            </label>
            <div class="mt-4 grid grid-cols-2 gap-2">
              <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded bg-emerald-600 px-3 text-sm font-semibold text-white hover:bg-emerald-700" @click="reviewWoche('ok')">
                <i class="las la-check"></i>
                OK, sperren
              </button>
              <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded bg-amber-500 px-3 text-sm font-semibold text-white hover:bg-amber-600" @click="reviewWoche('korrektur')">
                <i class="las la-undo"></i>
                Korrektur
              </button>
            </div>
            <span v-if="reviewForm.errors.kommentar" class="mt-2 block text-xs text-red-600">{{ reviewForm.errors.kommentar }}</span>
          </section>

          <section class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Freigabe</h2>
            <div class="mt-4 space-y-3 text-sm">
              <div class="flex items-start gap-3">
                <span class="mt-1 h-3 w-3 rounded-full" :class="woche.submitted_at ? 'bg-sky-500' : 'bg-zinc-300'"></span>
                <div>
                  <p class="font-medium text-zinc-900">Ausbilder/Lehrkraft bestaetigt</p>
                  <p class="text-zinc-500">{{ woche.submitted_at ? timeLabel(woche.submitted_at) : 'noch offen' }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <span class="mt-1 h-3 w-3 rounded-full" :class="woche.reviewed_at ? 'bg-emerald-500' : 'bg-zinc-300'"></span>
                <div>
                  <p class="font-medium text-zinc-900">Leitung/Assistenz prueft</p>
                  <p class="text-zinc-500">{{ woche.reviewed_at ? timeLabel(woche.reviewed_at) : 'wartet' }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <span class="mt-1 h-3 w-3 rounded-full" :class="woche.locked_at ? 'bg-zinc-700' : 'bg-zinc-300'"></span>
                <div>
                  <p class="font-medium text-zinc-900">Woche gesperrt</p>
                  <p class="text-zinc-500">{{ woche.locked_at ? timeLabel(woche.locked_at) : 'nicht gesperrt' }}</p>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-zinc-900">Azubi-Liste</h2>
              <span class="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600">{{ teilnehmer.length }}</span>
            </div>

            <div v-if="teilnehmer.length" class="mt-4 max-h-[520px] overflow-y-auto rounded border border-zinc-200">
              <button
                v-for="person in teilnehmer"
                :key="person.id"
                type="button"
                class="grid w-full grid-cols-[44px_1fr] border-b border-zinc-100 text-left last:border-b-0 hover:bg-zbb/5"
                @click="entryForm.azubi_nummern = entryForm.azubi_nummern ? `${entryForm.azubi_nummern}, ${person.nr}` : String(person.nr)"
              >
                <span class="bg-zinc-50 px-3 py-2 text-sm font-semibold text-zinc-600">{{ person.nr }}</span>
                <span class="px-3 py-2 text-sm text-zinc-800">{{ person.name }}</span>
              </button>
            </div>

            <p v-else class="mt-4 rounded border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-500">
              Keine Teilnehmer in der Gruppe.
            </p>
          </section>
        </aside>
      </section>
    </div>
  </AppLayout>
</template>
