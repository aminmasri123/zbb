<template>
    <Modal v-show="visible" @close="close">
        <template #header>Berechtigung anlegen</template>

        <template #body>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label for="permission-display-name" class="mb-1 block text-sm font-medium text-gray-700">Anzeigename</label>
                    <input
                        id="permission-display-name"
                        v-model.trim="form.display_name"
                        type="text"
                        maxlength="255"
                        class="w-full rounded border-gray-300"
                        placeholder="Internen Chat verwenden"
                    />
                    <p v-if="errors.display_name" class="mt-1 text-sm text-red-600">{{ errors.display_name[0] }}</p>
                </div>

                <div>
                    <label for="permission-name" class="mb-1 block text-sm font-medium text-gray-700">Technischer Name</label>
                    <input
                        id="permission-name"
                        v-model.trim="form.name"
                        type="text"
                        maxlength="255"
                        class="w-full rounded border-gray-300 font-mono"
                        placeholder="chat.use"
                    />
                    <p class="mt-1 text-xs text-gray-500">Keine Leerzeichen, zum Beispiel <span class="font-mono">bereich.aktion</span>.</p>
                    <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
                </div>

                <div>
                    <label for="permission-category" class="mb-1 block text-sm font-medium text-gray-700">Kategorie</label>
                    <select id="permission-category" v-model="form.berechtigungskategorie_id" class="w-full rounded border-gray-300">
                        <option value="" disabled>Kategorie auswählen</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <p v-if="errors.berechtigungskategorie_id" class="mt-1 text-sm text-red-600">{{ errors.berechtigungskategorie_id[0] }}</p>
                </div>

                <div>
                    <label for="permission-description" class="mb-1 block text-sm font-medium text-gray-700">Beschreibung</label>
                    <textarea
                        id="permission-description"
                        v-model.trim="form.beschreibung"
                        rows="4"
                        maxlength="5000"
                        class="w-full rounded border-gray-300"
                        placeholder="Beschreibt konkret, welche Aktion mit dieser Berechtigung erlaubt wird."
                    ></textarea>
                    <p v-if="errors.beschreibung" class="mt-1 text-sm text-red-600">{{ errors.beschreibung[0] }}</p>
                </div>

                <label class="flex items-start gap-3 rounded border bg-gray-50 p-3">
                    <input v-model="form.assign_to_role" type="checkbox" class="mt-1 rounded border-gray-300 text-zbb focus:ring-zbb" />
                    <span>
                        <span class="block text-sm font-medium text-gray-800">Direkt der Rolle „{{ roleName }}“ zuweisen</span>
                        <span class="block text-xs text-gray-500">Die Administrator-Rolle erhält neue Berechtigungen immer automatisch.</span>
                    </span>
                </label>
            </div>
        </template>

        <template #footer>
            <button
                type="button"
                class="rounded bg-zbb px-4 py-2 text-white transition hover:bg-zbb-dark disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="saving"
                @click="save"
            >
                {{ saving ? 'Speichern ...' : 'Speichern' }}
            </button>
            <button type="button" class="rounded border px-4 py-2 transition hover:bg-gray-100" :disabled="saving" @click="close">
                Abbrechen
            </button>
        </template>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import Modal from '@/Components/ModalForm.vue';

const props = defineProps({
    visible: Boolean,
    categories: { type: Array, default: () => [] },
    roleId: { type: Number, required: true },
    roleName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'added']);
const saving = ref(false);
const errors = ref({});
const emptyForm = () => ({
    name: '',
    display_name: '',
    beschreibung: '',
    berechtigungskategorie_id: '',
    assign_to_role: true,
    role_id: props.roleId,
});
const form = ref(emptyForm());

watch(() => props.roleId, (roleId) => {
    form.value.role_id = roleId;
});

const reset = () => {
    form.value = emptyForm();
    errors.value = {};
};

const close = () => {
    if (saving.value) return;
    reset();
    emit('close');
};

const save = async () => {
    saving.value = true;
    errors.value = {};

    try {
        const response = await axios.post(route('berechtigung.store'), form.value);
        await Swal.fire('Erfolg!', 'Berechtigung erfolgreich angelegt!', 'success');
        emit('added', response.data.permission);
        reset();
        emit('close');
    } catch (error) {
        errors.value = error.response?.data?.errors || {};
        if (!Object.keys(errors.value).length) {
            await Swal.fire('Fehler', error.response?.data?.message || 'Speichern fehlgeschlagen', 'error');
        }
    } finally {
        saving.value = false;
    }
};
</script>
