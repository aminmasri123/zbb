<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({ partnerId: Number, schuljahr: String, schoolName: String });
const emit = defineEmits(['close']);
const datum = ref(new Date().toISOString().slice(0, 10));
const loading = ref(false);
const error = ref('');

const exportLetter = async () => {
    error.value = '';
    loading.value = true;
    try {
        const response = await axios.post(
            route('partner.bop-usb-stick-letter.export', props.partnerId),
            { schuljahr: props.schuljahr, datum: datum.value },
            { responseType: 'blob' }
        );
        const disposition = response.headers['content-disposition'] || '';
        const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
        const fallback = `USB-Stick-Brief-${props.schoolName}.docx`;
        const filename = encoded ? decodeURIComponent(encoded) : fallback;
        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        emit('close');
    } catch (exception) {
        error.value = exception.response?.status === 422
            ? 'Bitte geben Sie ein gültiges Datum ein.'
            : 'Der USB-Stick-Brief konnte nicht exportiert werden.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h2 class="text-lg font-bold">USB-Stick-Brief exportieren</h2>
            <p class="mt-1 text-sm text-gray-600">{{ schoolName }} · Schuljahr {{ schuljahr }}</p>
            <label class="mt-5 block text-sm font-medium text-gray-700" for="usb-stick-brief-datum">Datum</label>
            <input id="usb-stick-brief-datum" v-model="datum" type="date" required class="mt-1 w-full rounded-md border-gray-300" />
            <p class="mt-2 text-xs text-gray-500">Kennwort: BOP@{{ String(schuljahr).match(/\d+/g)?.map(v => v.slice(-2)).slice(0, 2).join('-') }}.{{ schoolName }}</p>
            <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="rounded border px-4 py-2 text-sm" :disabled="loading" @click="emit('close')">Abbrechen</button>
                <button type="button" class="rounded bg-zbb px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="loading || !datum" @click="exportLetter">
                    {{ loading ? 'Wird erstellt …' : 'Word exportieren' }}
                </button>
            </div>
        </div>
    </div>
</template>
