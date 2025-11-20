<script setup>
import { router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

import Dialog from 'primevue/dialog';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Swal from 'sweetalert2';
import { formatDate } from '@/utils/dateFormat.js';

const props = defineProps({
    visible: Boolean,
    vehicles: Array,   // nicht mehr genutzt (optional)
    drivers: Array,    // enthält Fahrer + deren dienstwagen
    entry: Object,
});

const emit = defineEmits(["close", "update:visible"]);

/* -----------------------------------------------------
   Sichtbarkeit synchronisieren
----------------------------------------------------- */
const localVisible = ref(props.visible);

watch(() => props.visible, (v) => {
    localVisible.value = v;
});

/* -----------------------------------------------------
   Formular (wird durch props.entry geladen)
----------------------------------------------------- */
const form = ref({
    dienstwagen_id: "",
    person_id: "",
    date: "",
    start_km: "",
    end_km: "",
    ziel: "",
    zweck: "",
});

/* -----------------------------------------------------
   Initialisierungs-Flag verhindert Reset beim Öffnen
----------------------------------------------------- */
const initializing = ref(true);

/* -----------------------------------------------------
   Formular mit Entry-Daten laden
----------------------------------------------------- */
watch(
    () => props.entry,
    (val) => {
        if (!val) return;

        initializing.value = true;

        form.value = {
            dienstwagen_id: val.dienstwagen_id,
            person_id: val.person_id,
            date: val.date,
            start_km: val.start_km,
            end_km: val.end_km,
            ziel: val.ziel,
            zweck: val.zweck,
        };

        // nach einem Tick zurück auf "Nutzer darf ändern"
        setTimeout(() => (initializing.value = false), 0);
    },
    { immediate: true }
);

/* -----------------------------------------------------
   Fahrzeuge dynamisch filtern nach ausgewähltem Fahrer
----------------------------------------------------- */
const filteredVehicles = computed(() => {
    if (!form.value.person_id) return [];

    const driver = props.drivers.find(d => d.id === form.value.person_id);

    return driver?.dienstwagen ?? [];
});

/* -----------------------------------------------------
   Wenn Fahrer geändert wird → Fahrzeug zurücksetzen
----------------------------------------------------- */
watch(() => form.value.person_id, () => {
    if (initializing.value) return;   // NICHT beim Öffnen resetten
    form.value.dienstwagen_id = "";
});

/* -----------------------------------------------------
   Distanz-Berechnung
----------------------------------------------------- */
const distance = computed(() => {
    const s = Number(form.value.start_km);
    const e = Number(form.value.end_km);
    return e > s ? e - s : 0;
});

/* -----------------------------------------------------
   Update absenden
----------------------------------------------------- */
function submit() {
    if (form.value.date instanceof Date) {
        form.value.date = formatDate(form.value.date);
    }

    router.put(route("dienstwagen.fahrtenbuch.update", props.entry.id), form.value, {
        preserveScroll: true,
        preserveState: true,

        onError: (errors) => {
            if (errors.start_km) {
                Swal.fire({
                    icon: "error",
                    title: "Ungültiger Start-KM!",
                    text: errors.start_km,
                });
            }

            if (errors.general) {
                Swal.fire({
                    icon: "error",
                    title: "Fehler",
                    text: errors.general,
                });
            }
        },

        onSuccess: () => {
            Swal.fire({
                icon: "success",
                title: "Fahrt erfolgreich aktualisiert!",
                timer: 1500,
                showConfirmButton: false,
            });

            emit("update:visible", false);
            emit("close");
        },
    });
}
</script>


<template>
    <Dialog
        v-model:visible="localVisible"
        @update:visible="emit('update:visible', $event)"
        modal
        header="Fahrt bearbeiten"
        :style="{ width: '50rem' }"
    >
        <form @submit.prevent="submit" class="p-6 space-y-6">

            <!-- Fahrer + Fahrzeug -->
            <div class="grid grid-cols-2 gap-6">

                <!-- Fahrzeug -->
                <FloatLabel variant="on">
                    <Select
                        v-model="form.dienstwagen_id"
                        :options="filteredVehicles"
                        :optionLabel="v => `${v.kennzeichen} – ${v.marke} ${v.modell}`"
                        optionValue="id"
                        class="w-full"
                        :disabled="!form.person_id"
                    />
                    <label>Fahrzeug *</label>
                </FloatLabel>

                <!-- Fahrer -->
                <FloatLabel variant="on">
                    <Select
                        v-model="form.person_id"
                        :options="drivers"
                        :optionLabel="v => `${v.nachname} ${v.vorname}`"
                        optionValue="id"
                        class="w-full"
                    />
                    <label>Fahrer *</label>
                </FloatLabel>

            </div>

            <!-- Datum -->
            <div>
                <FloatLabel variant="on">
                    <DatePicker
                        v-model="form.date"
                        dateFormat="yy-mm-dd"
                        showIcon
                        class="w-full"
                    />
                    <label>Datum *</label>
                </FloatLabel>
            </div>

            <!-- KM Start / Ende -->
            <div class="grid grid-cols-2 gap-6">
                <FloatLabel variant="on">
                    <InputText v-model="form.start_km" type="number" class="w-full" />
                    <label>KM Start *</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <InputText v-model="form.end_km" type="number" class="w-full" />
                    <label>KM Ende *</label>
                </FloatLabel>
            </div>

            <!-- Distanz -->
            <div class="text-sm">
                Distanz: <strong>{{ distance }} km</strong>
            </div>

            <!-- Ziel -->
            <div>
                <FloatLabel variant="on">
                    <InputText v-model="form.ziel" class="w-full" />
                    <label>Ziel *</label>
                </FloatLabel>
            </div>

            <!-- Zweck -->
            <div>
                <FloatLabel variant="on">
                    <Textarea v-model="form.zweck" rows="3" class="w-full" />
                    <label>Zweck *</label>
                </FloatLabel>
            </div>

            <!-- Buttons -->
            <div class="flex justify-end gap-4 pt-4">
                <button
                    type="button"
                    class="border px-4 py-2 rounded-lg hover:bg-gray-100"
                    @click="emit('update:visible', false)"
                >
                    Abbrechen
                </button>

                <button
                    type="submit"
                    class="bg-zbb text-white px-5 py-2 rounded-lg hover:bg-orange-600"
                >
                    Speichern
                </button>
            </div>

        </form>
    </Dialog>
</template>


