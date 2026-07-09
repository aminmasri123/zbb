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
import { toLocalDateString } from '@/utils/dateFormat.js';

const props = defineProps({
    visible: Boolean,
    vehicles: Array,
    drivers: Array,
    selectedVehicle: Object,
    selectedVehicleId: Number,
});

const emit = defineEmits(["close", "update:visible"]);

// Lokaler v-model Mirror
const localVisible = ref(props.visible);

// Fahrzeuge gefiltert nach ausgewähltem Fahrer
const filteredVehicles = computed(() => {
    if (!form.value.person_id) {
        return props.selectedVehicle ? [props.selectedVehicle] : [];
    }

    const driver = props.drivers.find(d => d.id === form.value.person_id);

    return driver?.dienstwagen ?? [];
});









// Sichtbarkeit synchron halten
watch(() => props.visible, (val) => {
    localVisible.value = val;

    if (val) {
        applySelectedVehicleDefaults();
    }
});

// Formular
const form = ref({
    dienstwagen_id: "",
    person_id: "",
	date: "",
    startort: "",
	start_km: "",
	end_km: "",
	zweck: "",
	ziel: "",
    fahrtart: "dienstlich",
    geschaeftspartner: "",
    bemerkung: "",
});

const fahrtarten = [
    { label: "Dienstlich", value: "dienstlich" },
    { label: "Privat", value: "privat" },
    { label: "Arbeitsweg", value: "arbeitsweg" },
];

// Distanz
const distance = computed(() => {
    const s = Number(form.value.start_km);
    const e = Number(form.value.end_km);
    return e > s ? e - s : 0;
});

watch(() => form.value.person_id, () => {
    form.value.dienstwagen_id = "";

    if (props.selectedVehicleId && filteredVehicles.value.some(v => Number(v.id) === Number(props.selectedVehicleId))) {
        form.value.dienstwagen_id = Number(props.selectedVehicleId);
    }
});

function emptyForm() {
    return {
        dienstwagen_id: "",
        person_id: props.drivers.length === 1 ? props.drivers[0].id : "",
        date: "",
        startort: "",
        start_km: props.selectedVehicle?.kilometerstand ?? "",
        end_km: "",
        zweck: "",
        ziel: "",
        fahrtart: "dienstlich",
        geschaeftspartner: "",
        bemerkung: "",
    };
}

function applySelectedVehicleDefaults() {
    if (!props.selectedVehicleId) {
        return;
    }

    if (!form.value.person_id && props.drivers.length === 1) {
        form.value.person_id = props.drivers[0].id;
    }

    if (!form.value.start_km && props.selectedVehicle?.kilometerstand !== undefined) {
        form.value.start_km = props.selectedVehicle.kilometerstand;
    }

    if (filteredVehicles.value.some(v => Number(v.id) === Number(props.selectedVehicleId))) {
        form.value.dienstwagen_id = Number(props.selectedVehicleId);
    }
}

function submit() {
    const payload = {
        ...form.value,
        date: toLocalDateString(form.value.date),
        redirect_dienstwagen_id: props.selectedVehicleId || null,
    };

    router.post(route("dienstwagen.fahrtenbuch.store"), payload, {
        preserveScroll: true,
        preserveState: true,

        onError: (errors) => {
            // Wenn Laravel-Validierung fehlschlägt
            if (errors.start_km) {
                Swal.fire({
                    icon: "error",
                    title: "Ungültiger Start-KM!",
                    text: errors.start_km,
                });
            }
            emit("close");
            if (errors.general) {
                Swal.fire({
                    icon: "error",
                    title: "Fehler!",
                    text: errors.general,
                });
            }

            // Formular bleibt offen
        },

        onSuccess: () => {
            Swal.fire({
                icon: "success",
                title: "Fahrt gespeichert!",
                timer: 1500,
                showConfirmButton: false,
            });

            // Felder zurücksetzen
            form.value = emptyForm();
            applySelectedVehicleDefaults();

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
        header="Neue Fahrt erfassen"
        :style="{ width: '50rem' }"
    >
        <form @submit.prevent="submit" class="p-6 space-y-6">

            <!-- Datum + Fahrer -->
            <div class="grid grid-cols-2 gap-6">
                <FloatLabel variant="on">
                    <DatePicker
                        v-model="form.date"
                        dateFormat="dd.mm.yy"
                        showIcon
                        modelValueType="string"

                        class="w-full"
                        inputClass="w-full"
                    />
                    <label>Datum *</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <Select
                        v-model="form.person_id"
                        :options="drivers"
                        :optionLabel="(v) => `${v.nachname} ${v.vorname}`"
                        optionValue="id"
                        class="w-full"
                    />
                    <label>Fahrer *</label>
                </FloatLabel>
            </div>

            <!-- Fahrzeug -->
            <div>
                <FloatLabel variant="on">
                    <!-- <Select
                        v-model="form.dienstwagen_id"
                        :options="vehicles"
                        :optionLabel="(v) => `${v.kennzeichen} – ${v.marke} ${v.modell}`"
                        optionValue="id"
                        class="w-full"
                    /> -->

                    <Select
                        v-model="form.dienstwagen_id"
                        :options="filteredVehicles"
                        :optionLabel="(v) => `${v.kennzeichen} – ${v.marke} ${v.modell}`"
                        optionValue="id"
                        class="w-full"
                        :disabled="!form.person_id"
                    />

                    <label>Fahrzeug *</label>
                </FloatLabel>
            </div>

            <!-- KM Start / KM Ende -->
            <div class="grid grid-cols-2 gap-6">
                <FloatLabel variant="on">
                    <InputText
                        v-model="form.startort"
                        class="w-full"
                    />
                    <label>Startort</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <Select
                        v-model="form.fahrtart"
                        :options="fahrtarten"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                    <label>Fahrtart *</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <InputText
                        v-model="form.start_km"
                        type="number"
                        class="w-full"
                    />
                    <label>KM Start *</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <InputText
                        v-model="form.end_km"
                        type="number"
                        class="w-full"
                    />
                    <label>KM Ende *</label>
                </FloatLabel>
            </div>

            <!-- Distanz -->
            <div class="text-sm">
                Distanz: <strong>{{ distance }} km</strong>
            </div>

            <!-- Ziel -->
            <div class="grid grid-cols-2 gap-6">
                <FloatLabel variant="on">
                    <InputText
                        v-model="form.ziel"
                        class="w-full"
                    />
                    <label>Ziel *</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <InputText
                        v-model="form.geschaeftspartner"
                        class="w-full"
                    />
                    <label>Geschäftspartner / Kontakt</label>
                </FloatLabel>
            </div>

            <!-- Zweck -->
            <div>
                <FloatLabel variant="on">
                    <Textarea
                        v-model="form.zweck"
                        class="w-full"
                        rows="3"
                    />
                    <label>Zweck *</label>
                </FloatLabel>
            </div>

            <div>
                <FloatLabel variant="on">
                    <Textarea
                        v-model="form.bemerkung"
                        class="w-full"
                        rows="2"
                    />
                    <label>Bemerkung / Umweg</label>
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
