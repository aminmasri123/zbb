<script setup>
import { computed, ref, watch } from "vue";
import Swal from "sweetalert2";
import axios from "axios";
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import FloatLabel from 'primevue/floatlabel';
import {
    arbeitsbereichRoomsForProject,
    bueroRoomsForProject,
    emptyRoomAssignmentFields,
    normalizeDefaultRoom,
    resetRoomAssignments,
    roomLabel,
    selectedRoomsForRow,
} from '@/utils/projectRoomAssignments';

const props = defineProps({
    visible: Boolean,
    userId: Number,
    projekte: Array,     // enthält standorte: [{id, name}]
    standorte: Array,
    bestehendeProjekte: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "saved", "removed"]);
const removingProjectId = ref(null);

// → Array aller Zuweisungen
const zuweisungen = ref([
    {
        projekt_id: null,
        standort_id: [],
        bereich_ids: [],
        default_bereich_id: null,
        ...emptyRoomAssignmentFields(),
    }
]);

const resetZuweisungen = () => {
    zuweisungen.value = [
        {
            projekt_id: null,
            standort_id: [],
            bereich_ids: [],
            default_bereich_id: null,
            ...emptyRoomAssignmentFields(),
        },
    ];
};

const bestehendeProjektListe = computed(() => {
    const seen = new Set();

    return (props.bestehendeProjekte || []).filter((projekt) => {
        const id = Number(projekt.id);

        if (!id || seen.has(id)) {
            return false;
        }

        seen.add(id);
        return true;
    });
});

watch(() => props.visible, (visible) => {
    if (visible) {
        resetZuweisungen();
    }
});

// 🔹 neue leere Zeile hinzufügen
const addRow = (projektId = null) => {
    zuweisungen.value.push({
        projekt_id: projektId,
        standort_id: [],
        bereich_ids: [],
        default_bereich_id: null,
        ...emptyRoomAssignmentFields(),
    });
};

// 🔹 Zeile entfernen
const removeRow = (index) => {
    zuweisungen.value.splice(index, 1);
};

const bereicheForProjekt = (projektId) => {
    return props.projekte.find((projekt) => Number(projekt.id) === Number(projektId))?.bereiche || [];
};

const selectedBereicheForRow = (row) => {
    const selectedIds = (row.bereich_ids || []).map((id) => Number(id));

    return bereicheForProjekt(row.projekt_id).filter((bereich) => selectedIds.includes(Number(bereich.id)));
};

const resetProjektBereiche = (row) => {
    row.bereich_ids = [];
    row.default_bereich_id = null;
};

const resetProjektAssignments = (row) => {
    resetProjektBereiche(row);
    resetRoomAssignments(row);
};

const normalizeDefaultBereich = (row) => {
    const selectedIds = (row.bereich_ids || []).map((id) => Number(id));

    if (selectedIds.length === 1) {
        row.default_bereich_id = selectedIds[0];
        return;
    }

    if (!selectedIds.includes(Number(row.default_bereich_id))) {
        row.default_bereich_id = null;
    }
};

const selectedBueroRaeumeForRow = (row) =>
    selectedRoomsForRow(props.projekte, row, 'buero_raum_ids', bueroRoomsForProject);

const selectedArbeitsbereichRaeumeForRow = (row) =>
    selectedRoomsForRow(props.projekte, row, 'arbeitsbereich_raum_ids', arbeitsbereichRoomsForProject);

const removeExistingProjekt = (projekt) => {
    Swal.fire({
        title: 'Projekt entfernen?',
        text: `${projekt.name} wird für diesen Mitarbeiter entfernt.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Entfernen',
        cancelButtonText: 'Abbrechen',
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        removingProjectId.value = Number(projekt.id);

        axios.delete(route('projekthaspersonen.destroy', projekt.id), {
            data: {
                user_id: props.userId,
                projekt_id: projekt.id,
            },
        })
            .then(() => {
                Swal.fire('Entfernt!', 'Projekt wurde vom Mitarbeiter entfernt.', 'success');
                emit('removed', {
                    user_id: props.userId,
                    projekt_id: Number(projekt.id),
                });
            })
            .catch((err) => {
                console.error(err);
                Swal.fire('Fehler', 'Projekt konnte nicht entfernt werden.', 'error');
            })
            .finally(() => {
                removingProjectId.value = null;
            });
    });
};



// 🔥 Speichern
const save = () => {
    // Validierung
    for (const row of zuweisungen.value) {
        if (!row.projekt_id || !Array.isArray(row.standort_id) || !row.standort_id.length) {
            Swal.fire("Fehler", "Bitte Projekt und Standort auswählen.", "error");
            return;
        }
    }

    const payload = {
        user_id: props.userId,
        zuweisungen: zuweisungen.value.map((row) => ({
            projekt_id: row.projekt_id,
            standort_id: row.standort_id,
            bereich_ids: row.bereich_ids || [],
            default_bereich_id: row.default_bereich_id || null,
            buero_raum_ids: row.buero_raum_ids || [],
            default_buero_raum_id: row.default_buero_raum_id || null,
            arbeitsbereich_raum_ids: row.arbeitsbereich_raum_ids || [],
            default_arbeitsbereich_raum_id: row.default_arbeitsbereich_raum_id || null,
        })),
    };

    axios.post(route("projekthaspersonen.store"), {
        user_id: payload.user_id,
        zuweisungen: payload.zuweisungen
    })
    .then(() => {
        Swal.fire("Gespeichert!", "Zuweisungen erfolgreich erstellt.", "success");
        emit("saved", payload);
        emit("close");
    })
    .catch(err => {
        console.error(err);
        Swal.fire("Fehler", "Speichern fehlgeschlagen.", "error");
    });
};
</script>
<template>
<div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">

    <div class="bg-white p-6 rounded-lg w-[600px] shadow-xl">

        <h2 class="text-xl font-bold mb-4">Projekte zuweisen</h2>

        <div class="max-h-[400px] overflow-y-auto">
            <div class="mb-4 rounded border bg-gray-50 p-3">
                <h3 class="mb-2 text-sm font-semibold text-gray-700">Bereits zugewiesene Projekte</h3>

                <div v-if="bestehendeProjektListe.length" class="flex flex-wrap gap-2">
                    <button
                        v-for="projekt in bestehendeProjektListe"
                        :key="projekt.id"
                        type="button"
                        @click="removeExistingProjekt(projekt)"
                        :disabled="removingProjectId === Number(projekt.id)"
                        class="rounded bg-white px-2 py-1 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 disabled:opacity-50"
                        title="Projekt entfernen"
                    >
                        {{ projekt.name }} <span class="ml-1 text-red-500">x</span>
                    </button>
                </div>

                <p v-else class="text-sm text-gray-500">
                    Noch keine Projekte zugewiesen.
                </p>
            </div>

            <div v-for="(row, i) in zuweisungen" :key="i"
                 class="border p-3 mb-3 rounded">

                <div class="flex gap-2">
                    <FloatLabel variant="on">
                        <Select v-model="row.projekt_id" @change="resetProjektAssignments(row)" inputId="id" optionValue="id"  :options="projekte" optionLabel="name" class="w-full"  />

                        <label for="abteilungsleiter">Projekt wählen</label>
                    </FloatLabel>

                    <!-- Standort -->
                    <MultiSelect
                        v-model="row.standort_id"
                        inputId="id"
                        optionLabel="name"
                        :options="props.standorte"
                        optionValue="id"
                        filter
                        placeholder="Standorte wählen*"
                        :maxSelectedLabels="3"
                        class="w-full">
                    </MultiSelect>
                    <!-- Entfernen -->
                    <button v-if="zuweisungen.length > 1"
                        @click="removeRow(i)"
                        class="text-red-500 ml-2">
                        X
                    </button>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Bereiche</label>
                        <MultiSelect
                            v-model="row.bereich_ids"
                            :options="bereicheForProjekt(row.projekt_id)"
                            optionLabel="name"
                            optionValue="id"
                            display="chip"
                            filter
                            placeholder="Bereiche wÃ¤hlen"
                            :disabled="!row.projekt_id"
                            class="w-full"
                            @change="normalizeDefaultBereich(row)"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Standardbereich</label>
                        <select
                            v-model="row.default_bereich_id"
                            class="w-full rounded border p-2 text-sm disabled:bg-gray-100"
                            :disabled="selectedBereicheForRow(row).length === 0"
                        >
                            <option :value="null">Kein Standard</option>
                            <option
                                v-for="bereich in selectedBereicheForRow(row)"
                                :key="bereich.id"
                                :value="bereich.id"
                            >
                                {{ bereich.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Büros</label>
                        <MultiSelect
                            v-model="row.buero_raum_ids"
                            :options="bueroRoomsForProject(props.projekte, row.projekt_id)"
                            :optionLabel="roomLabel"
                            optionValue="id"
                            display="chip"
                            filter
                            placeholder="Büros wählen"
                            :disabled="!row.projekt_id"
                            class="w-full"
                            @change="normalizeDefaultRoom(row, 'buero_raum_ids', 'default_buero_raum_id')"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Standardbüro</label>
                        <select
                            v-model="row.default_buero_raum_id"
                            class="w-full rounded border p-2 text-sm disabled:bg-gray-100"
                            :disabled="selectedBueroRaeumeForRow(row).length === 0"
                        >
                            <option :value="null">Kein Standard</option>
                            <option
                                v-for="raum in selectedBueroRaeumeForRow(row)"
                                :key="raum.id"
                                :value="raum.id"
                            >
                                {{ roomLabel(raum) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Arbeitsbereiche</label>
                        <MultiSelect
                            v-model="row.arbeitsbereich_raum_ids"
                            :options="arbeitsbereichRoomsForProject(props.projekte, row.projekt_id)"
                            :optionLabel="roomLabel"
                            optionValue="id"
                            display="chip"
                            filter
                            placeholder="Arbeitsbereiche wählen"
                            :disabled="!row.projekt_id"
                            class="w-full"
                            @change="normalizeDefaultRoom(row, 'arbeitsbereich_raum_ids', 'default_arbeitsbereich_raum_id')"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-600">Standard-Arbeitsbereich</label>
                        <select
                            v-model="row.default_arbeitsbereich_raum_id"
                            class="w-full rounded border p-2 text-sm disabled:bg-gray-100"
                            :disabled="selectedArbeitsbereichRaeumeForRow(row).length === 0"
                        >
                            <option :value="null">Kein Standard</option>
                            <option
                                v-for="raum in selectedArbeitsbereichRaeumeForRow(row)"
                                :key="raum.id"
                                :value="raum.id"
                            >
                                {{ roomLabel(raum) }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- + Hinzufügen -->
                <button
                    @click="addRow(row.projekt_id)"
                    class="mt-2 text-zbb"
                >
                    + weitere Zuweisung hinzufügen
                </button>
            </div>

        </div>

        <div class="flex justify-between mt-4">
            <button @click="emit('close')" class="px-4 py-2 border rounded">
                Abbrechen
            </button>
            <button @click="save" class="px-4 py-2 bg-zbb text-white rounded">
                Speichern
            </button>
        </div>

    </div>
</div>
</template>
