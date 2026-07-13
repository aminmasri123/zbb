<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, reactive, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import Modal from '@/Components/ModalForm.vue'
import Swal from 'sweetalert2';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';

const props = defineProps({
    anforderung: Object,
    permissions: Object,
    canEditMaterialanforderung: Boolean,
    canConfirmKaufmaenisch: Boolean,
    canConfirmSachlich: Boolean,
    canBestellen: Boolean,
    verlauf: Array,
    kostenstellen: {
        type: Array,
        default: () => [],
    },
})
const anmerkung = ref('')
const selectedStatus = ref(null)
const statusOptions = [
    { label: 'Überarbeiten', value: 'zur_ueberarbeitung' },
    { label: 'Stornieren', value: 'stornieren' },
    { label: 'Geliefert', value: 'geliefert' },
    { label: 'Teilweise geliefert', value: 'teilweise_geliefert' },
    { label: 'Bestellt', value: 'bestellt' }
]
// Bearbeitungsmodus
const editing = ref(false)
const editableStatuses = ['entwurf', 'eingereicht', 'zur_ueberarbeitung']
const canStillEdit = computed(() =>
    props.canEditMaterialanforderung && editableStatuses.includes(props.anforderung.status)
)

if (usePage().url.includes('edit=1') && canStillEdit.value) {
    editing.value = true
}

// Modal für Genehmigungen
const visibleBestellen = ref(false)
const visibleKaufmaennisch = ref(false)
const visibleSachlich = ref(false)
const sendenModal = ref(false)

// Formular für inline editing
const form = reactive({
    kostenstelle: props.anforderung.kostenstelle,
    bemerkungen: props.anforderung.bemerkungen,
    artikeln: props.anforderung.artikeln.map(a => ({
        id: a.id,
        pos: a.pos,
        artikel: a.artikel,
        stueck: Number(a.stueck) || 0,
        art_nr: a.art_nr,
        einzelpreis: Number(a.einzelpreis) || 0,
        mwst: Number(a.mwst) || 19,
        gesamtpreis: Number(a.gesamtpreis) || 0,
        link: a.link
    }))
})

// Gesamtpreis pro Position aktualisieren
const updateGesamtpreis = (p) => {
    const stueck = Number(p.stueck) || 0
    const einzelpreis = Number(p.einzelpreis) || 0
    p.gesamtpreis = parseFloat((stueck * einzelpreis).toFixed(2))
}

// Endsumme inkl. MwSt
const endsumme = computed(() => {
    return form.artikeln.reduce((sum, p) => {
        const gesamt = Number(p.gesamtpreis) || 0
        const mwst = Number(p.mwst) || 0
        return sum + gesamt * (1 + mwst / 100)
    }, 0).toFixed(2)
})

// Artikel hinzufügen
const addArtikel = () => {
    form.artikeln.push({
        id: null,
        pos: form.artikeln.length + 1,
        artikel: '',
        stueck: 1,
        art_nr: '',
        einzelpreis: 0,
        mwst: 19,
        gesamtpreis: 0,
        link: ''
    })
}

// Artikel löschen
const removeArtikel = (index) => {
    form.artikeln.splice(index, 1)
    form.artikeln.forEach((p, i) => p.pos = i + 1)
}
// Materialanforderung senden

const bestellungSenden = () => {
    router.get(route('materialanforderung.genehmigen', {
        id: props.anforderung.id,
        status: 'eingereicht',
        anmerkung: anmerkung.value
    }), {
        onSuccess: () => {
            anmerkung.value = ''
            sendenModal.value = false
            Swal.fire('Erfolg!', 'Materialanforderung erfolgreich gesendet!', 'success')
        }
    })
}
const sachlichGenehmigt = () => {
    router.get(route('materialanforderung.genehmigen', {
        id: props.anforderung.id,
        status: 'sachlich_genehmigt',
        anmerkung: anmerkung.value
    }), {
        onSuccess: () => {
            anmerkung.value = '' // reset
            visibleSachlich.value = false
        }
    })
}

const kaufmaennischGenehmigt = () => {
    router.get(route('materialanforderung.genehmigen', {id: props.anforderung.id, status: 'kaufmaennisch_genehmigt'}))
}
const bestellwesenUpdate = () => {
    if (!selectedStatus.value) {
        Swal.fire('Fehler', 'Bitte Status auswählen!', 'warning')
        return
    }

    router.get(route('materialanforderung.genehmigen', {
        id: props.anforderung.id,
        status: selectedStatus.value,
        anmerkung: anmerkung.value
    }), {
        onSuccess: () => {
            anmerkung.value = ''
            visibleBestellen.value = false
        }
    })
}
// Änderungen speichern
const save = () => {
    // id zum Form-Datenobjekt hinzufügen
    const payload = { ...form, id: props.anforderung.id };

    router.put(route('materialanforderung.update', props.anforderung.id), payload, {
        onSuccess: () => {
            editing.value = false
            Swal.fire('Erfolg!', 'Materialanforderung erfolgreich aktualisiert!', 'success')
        }
    })
}

// Genehmigungsaktionen
const sachlichGenehmigen = () => {
        anmerkung.value = ''

    visibleSachlich.value = true
}
const kaufmaennischGenehmigen = () => {
        anmerkung.value = ''

    visibleKaufmaennisch.value = true
}
const bestellen = () => {
        anmerkung.value = ''

    visibleBestellen.value = true
}
const senden = () => {
        anmerkung.value = ''

    sendenModal.value = true
}

</script>

<template>
<Head title="Materialanforderung" />

<AppLayout>
    <template #header>
        <template v-if="editing">Materialanforderungen bearbeiten</template>
        <div v-else class="flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Materialanforderung #{{ anforderung.id }}</h2>
                <p class="text-sm text-gray-500">Übersicht der Bestellung</p>
            </div>

            <span class="px-4 py-1 text-sm rounded-full bg-blue-100 text-blue-700 font-medium">
                {{ anforderung.status }}
            </span>

            <div class="flex gap-2">
                <!-- Bearbeiten Button -->
                  <button v-if="['entwurf', 'zur_ueberarbeitung'].includes(anforderung.status) && !editing"
                        @click="senden"
                        class="bg-zbb text-white px-4 py-2 rounded">
                    Senden
                </button>
                <button v-if="canStillEdit"
                        @click="editing = !editing"
                        class="bg-green-500 text-white px-4 py-2 rounded">
                        {{ editing ? 'Abbrechen' : 'Bearbeiten' }}
                </button>
                <!-- Genehmigungsbuttons -->
                <button v-if="anforderung.status === 'eingereicht' && canConfirmSachlich"
                        @click="sachlichGenehmigen"
                        class="bg-zbb text-white px-4 py-2 rounded">
                    Sachlich genehmigen
                </button>

                <button v-if="anforderung.status === 'sachlich_genehmigt' && canConfirmKaufmaenisch"
                        @click="kaufmaennischGenehmigen"
                        class="bg-blue-500 text-white px-4 py-2 rounded">
                    Kaufmännisch genehmigen
                </button>


                <template @click="bestellen" v-if="anforderung.status !== 'entwurf'
                    && anforderung.status !== 'zur_ueberarbeitung' && anforderung.status !== 'eingereicht'
                 && canBestellen">
                   <Dropdown
                    v-model="selectedStatus"
                    :options="statusOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Status auswählen"
                    class="w-full"
                    @change="visibleBestellen = true"
                />
                </template>

            </div>
        </div>
    </template>

    <div v-if="editing" class="flex flex-col gap-3 max-w-8xl bg-white shadow-lg p-8 rounded-lg">
        <div class="grid grid-cols-3 gap-4">
            <FloatLabel variant="on" class="w-full">
                <InputText :value="anforderung.projekt.name" disabled class="w-full" placeholder="" />
                <label>Projekt</label>
            </FloatLabel>

            <FloatLabel variant="on" class="w-full">
                <InputText :value="`${anforderung.besteller?.first_name || ''} ${anforderung.besteller?.last_name || ''}`.trim()" disabled class="w-full" placeholder="" />
                <label>Besteller</label>
            </FloatLabel>

            <FloatLabel variant="on" class="w-full">
                <Select
                    v-model="form.kostenstelle"
                    :options="kostenstellen"
                    optionLabel="kostenstelle"
                    optionValue="kostenstelle"
                    placeholder="Bitte auswählen"
                    class="w-full"
                />
                <label>Kostenstelle</label>
            </FloatLabel>
        </div>

        <div class="grid grid-cols-1 mt-2">
            <FloatLabel variant="on" class="w-full">
                <InputText v-model="form.bemerkungen" class="w-full" placeholder="" />
                <label>Bemerkungen</label>
            </FloatLabel>
        </div>

        <hr class="my-2" />
        <h3 class="font-semibold">Artikel</h3>
        <table class="min-w-full border divide-y divide-gray-200">
            <thead class="bg-gray-200">
                <tr>
                    <th>Pos</th>
                    <th>Link</th>
                    <th>Artikel</th>
                    <th>Stück</th>
                    <th>Art.Nr</th>
                    <th>Einzelpreis</th>
                    <th>MwSt %</th>
                    <th>Gesamtpreis</th>
                    <th>*</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(p, index) in form.artikeln" :key="p.id ?? `new-${index}`">
                    <td>{{ p.pos }}</td>
                    <td><InputText v-model="p.link" /></td>
                    <td><InputText v-model="p.artikel" required /></td>
                    <td><InputText type="number" v-model.number="p.stueck" @input="updateGesamtpreis(p)" /></td>
                    <td><InputText v-model="p.art_nr" /></td>
                    <td><InputText type="number" v-model.number="p.einzelpreis" @input="updateGesamtpreis(p)" /></td>
                    <td><InputText type="number" v-model.number="p.mwst" /></td>
                    <td>{{ (Number(p.gesamtpreis) || 0).toFixed(2) }}</td>
                    <td>
                        <Button severity="danger" @click="removeArtikel(index)">
                            <i class="las la-trash"></i>
                        </Button>
                    </td>
                </tr>
            </tbody>
        </table>

        <Button class="p-button-sm p-button-secondary mt-3" @click="addArtikel">+ Artikel hinzufügen</Button>

        <div class="mt-4 font-semibold text-right text-lg">
            Endsumme inkl. MwSt: {{ endsumme }} €
        </div>

        <div class="flex gap-2 mt-4">
            <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">Speichern</button>
            <button @click="editing = false" class="border px-4 py-2 rounded">Abbrechen</button>
        </div>
    </div>

    <div v-else class="space-y-8 mt-4">

        <!-- Projekt / Besteller / Kostenstelle -->
        <div class="grid grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border hover:shadow-md transition">
                <p class="text-gray-500 text-sm">Projekt</p>
                <p class="text-lg font-semibold mt-1">{{ anforderung.projekt.name }}</p>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border hover:shadow-md transition">
                <p class="text-gray-500 text-sm">Besteller</p>
                <p class="text-lg font-semibold mt-1">
                    {{ anforderung.besteller?.first_name }} {{ anforderung.besteller?.last_name }}
                </p>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border hover:shadow-md transition">
                <p class="text-gray-500 text-sm">Kostenstelle</p>
                <template v-if="editing">
                    <input v-model="form.kostenstelle" class="border rounded px-2 py-1 w-full"/>
                </template>
                <template v-else>{{ anforderung.kostenstelle }}</template>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
        <!-- Bemerkungen -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border">
                <h3 class="font-semibold text-gray-800 mb-2">Bemerkung</h3>
                <template v-if="editing">
                    <textarea v-model="form.bemerkungen" class="border rounded w-full px-2 py-1"></textarea>
                </template>
                <template v-else>
                    {{ anforderung.bemerkungen || 'Keine Bemerkung vorhanden.' }}
                </template>
            </div>

            <!-- Verlauf -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border">
                <h3 class="font-semibold text-gray-800 mb-2">Verlauf</h3>
                <ul class="text-gray-600 text-sm space-y-1">
                    <li v-for="v in verlauf" :key="v.id">
                        <span class="font-medium">{{ v.genehmiger.vorname }} {{ v.genehmiger.nachname }}</span> - <b class="text-red-500">{{ v.status }}</b> am {{ new Date(v.created_at).toLocaleString() }} || <b class="text-red-500">Bemmerkung: </b>{{ v.kommentar }}
                    </li>
                </ul>
            </div>
        </div>
        <!-- Artikel -->
        <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Artikel</h3>
            </div>

            <div class="overflow-x-auto p-6">
                <table class="w-full border divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-gray-500 text-sm text-left">
                        <tr>
                            <th>Pos</th><th>Link</th><th>Artikel</th><th>Stück</th>
                            <th>Art.Nr</th><th>Einzelpreis</th><th>MwSt %</th><th>Gesamtpreis</th><th>*</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="(p,index) in form.artikeln" :key="p.id">
                            <td>{{ p.pos }}</td>
                            <td>
                                <template v-if="editing">
                                    <input v-model="p.link" class="border rounded px-2 py-1 w-full"/>
                                </template>
                                <template v-else><a :title="p.link" :href="p.link" target="_blank" rel="noopener noreferrer">{{ p.link.slice(0,20) }}</a> </template>
                            </td>
                            <td>
                                <template v-if="editing">
                                    <input v-model="p.artikel" class="border rounded px-2 py-1 w-full"/>
                                </template>
                                <template v-else>{{ p.artikel }}</template>
                            </td>
                            <td>
                                <template v-if="editing">
                                    <input type="number" v-model.number="p.stueck" @input="updateGesamtpreis(p)" class="border rounded px-2 py-1 w-20"/>
                                </template>
                                <template v-else>{{ p.stueck }}</template>
                            </td>
                            <td>
                                <template v-if="editing">
                                    <input v-model="p.art_nr" class="border rounded px-2 py-1 w-full"/>
                                </template>
                                <template v-else>{{ p.art_nr }}</template>
                            </td>
                            <td>
                                <template v-if="editing">
                                    <input type="number" v-model.number="p.einzelpreis" @input="updateGesamtpreis(p)" class="border rounded px-2 py-1 w-24"/>
                                </template>
                                <template v-else>{{ p.einzelpreis }}</template>
                            </td>
                            <td>
                                <template v-if="editing">
                                    <input type="number" v-model.number="p.mwst" @input="updateGesamtpreis(p)" class="border rounded px-2 py-1 w-20"/>
                                </template>
                                <template v-else>{{ p.mwst }}</template>
                            </td>
                            <td>{{ (Number(p.gesamtpreis) || 0).toFixed(2) }}</td>
                            <td>
                                <template v-if="editing">
                                    <button @click.prevent="removeArtikel(index)" class="text-red-500">✖</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="editing" class="mt-2">
                    <button @click.prevent="addArtikel" class="bg-gray-700 text-white px-3 py-1 rounded">+ Artikel hinzufügen</button>
                </div>

                <div class="mt-4 font-semibold text-right text-lg">
                    Endsumme inkl. MwSt: {{ endsumme }} €
                </div>
            </div>
        </div>

    </div>

    <!-- Modal für Genehmigung -->
    <Modal v-if="visibleSachlich" @close="visibleSachlich = false">
        <template #header>Genehmigung bestätigen</template>
        <template #body>
            <p>Sind Sie sicher, dass Sie diese Aktion ausführen möchten?</p>
            <textarea
                v-model="anmerkung"
                placeholder="Anmerkung hinzufügen..."
                class="w-full border rounded mt-3 px-3 py-2"
            ></textarea>
        </template>
        <template #footer>
            <button @click="sachlichGenehmigt" class="bg-zbb text-white px-4 py-2 rounded">Bestätigen</button>
            <button @click="visibleSachlich=false" class="border px-4 py-2 rounded">Abbrechen</button>
        </template>
    </Modal>

    <!-- Kaufmännische bestätigung -->
    <Modal v-if="visibleKaufmaennisch" @close="visibleKaufmaennisch = false">
        <template #header>Genehmigung bestätigen</template>
        <template #body>
            <p>Sind Sie sicher, dass Sie diese Aktion ausführen möchten?</p>
            <textarea
                v-model="anmerkung"
                placeholder="Anmerkung hinzufügen..."
                class="w-full border rounded mt-3 px-3 py-2">
            </textarea>
        </template>
        <template #footer>
            <button @click="kaufmaennischGenehmigt" class="bg-zbb text-white px-4 py-2 rounded">Bestätigen</button>
            <button @click="visibleKaufmaennisch=false" class="border px-4 py-2 rounded">Abbrechen</button>
        </template>
    </Modal>
    <!-- Bestellwesen update -->
    <Modal v-if="visibleBestellen" @close="visibleBestellen = false">
        <template #header>Genehmigung bestätigen</template>
        <template #body>
            <p>Sind Sie sicher, dass Sie diese Aktion ausführen möchten?</p>
            <textarea
                v-model="anmerkung"
                placeholder="Anmerkung hinzufügen..."
                class="w-full border rounded mt-3 px-3 py-2">
            </textarea>
        </template>
        <template #footer>
            <button @click="bestellwesenUpdate" class="bg-zbb text-white px-4 py-2 rounded">Bestätigen</button>
            <button @click="visibleBestellen=false" class="border px-4 py-2 rounded">Abbrechen</button>
        </template>
    </Modal>
    <Modal v-if="sendenModal" @close="sendenModal = false">
        <template #header>Materialanforderung senden</template>
        <template #body>
            <p><b>Sind Sie sicher, dass Sie diese Aktion ausführen möchten?</b></p>
            <p>Bitte achten Sie darauf, dass eine Bearbeitung der Materialanforderung nach der Versendung nicht mehr möglich ist.</p>
            <textarea
                v-model="anmerkung"
                placeholder="Anmerkung hinzufügen..."
                class="w-full border rounded mt-3 px-3 py-2">
            </textarea>
        </template>
        <template #footer>
            <button @click="bestellungSenden" class="bg-zbb text-white px-2 py-2 rounded">Bestätigen</button>
            <button @click="sendenModal = false" class="border px-2 py-2 rounded">Abbrechen</button>
        </template>
    </Modal>




</AppLayout>
</template>
