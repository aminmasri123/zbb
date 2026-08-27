<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    placeholders: { type: Object, default: () => ({}) },
});

const forms = reactive(Object.fromEntries(props.templates.map((template) => [template.key, {
    subject: template.subject,
    body: template.body,
    attachment: null,
    remove_attachment: false,
}])));
const saving = ref(null);

const selectAttachment = (template, event) => {
    forms[template.key].attachment = event.target.files?.[0] || null;
    if (forms[template.key].attachment) forms[template.key].remove_attachment = false;
};

const save = (template) => {
    saving.value = template.key;
    router.post(route('internship-email-templates.update', template.key), forms[template.key], {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            forms[template.key].attachment = null;
            forms[template.key].remove_attachment = false;
        },
        onFinish: () => { saving.value = null; },
    });
};

const formatBytes = (bytes) => {
    if (!bytes) return '';
    return bytes >= 1024 * 1024
        ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        : `${Math.ceil(bytes / 1024)} KB`;
};
</script>

<template>
    <AppLayout title="Praktikums-E-Mails">
        <Head title="Praktikums-E-Mails" />

        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Praktikums-E-Mails</h2>
                <p class="mt-1 text-sm text-gray-500">Standardtexte und optionale Anhänge für Outlook konfigurieren.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                    <h3 class="font-semibold">So funktioniert der Anhang</h3>
                    <p class="mt-1">Outlook wird über einen E-Mail-Link geöffnet. Aus Sicherheitsgründen kann ein Browser den Anhang nicht automatisch einsetzen. Der konfigurierte Anhang wird deshalb im E-Mail-Fenster der Anwendung zum Herunterladen angeboten und anschließend manuell in Outlook eingefügt.</p>
                </section>

                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Verfügbare Platzhalter</h3>
                    <div class="mt-3 grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                        <div v-for="(description, placeholder) in placeholders" :key="placeholder" class="rounded-lg border bg-gray-50 p-3">
                            <code class="text-xs font-semibold text-zbb">{{ placeholder }}</code>
                            <p class="mt-1 text-xs text-gray-600">{{ description }}</p>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-3">
                    <article v-for="template in templates" :key="template.key" class="rounded-xl bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">{{ template.label }}</h3>

                        <label class="mt-4 block text-sm font-medium text-gray-700">
                            Betreff
                            <input v-model="forms[template.key].subject" maxlength="255" class="mt-1 w-full rounded border-gray-300 text-sm" />
                        </label>

                        <label class="mt-4 block text-sm font-medium text-gray-700">
                            E-Mail-Text
                            <textarea v-model="forms[template.key].body" maxlength="10000" rows="15" class="mt-1 w-full rounded border-gray-300 text-sm"></textarea>
                        </label>

                        <div class="mt-4 rounded-lg border bg-gray-50 p-4">
                            <p class="text-sm font-medium text-gray-700">Anhang</p>
                            <div v-if="template.attachment_original_name" class="mt-2 text-sm">
                                <a :href="template.attachment_download_url" class="font-medium text-zbb underline">{{ template.attachment_original_name }}</a>
                                <span class="ml-1 text-xs text-gray-500">{{ formatBytes(template.attachment_size) }}</span>
                                <label class="mt-2 flex items-center gap-2 text-xs text-red-700">
                                    <input v-model="forms[template.key].remove_attachment" type="checkbox" />
                                    Vorhandenen Anhang entfernen
                                </label>
                            </div>
                            <p v-else class="mt-1 text-xs text-gray-500">Kein Anhang hinterlegt.</p>
                            <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="mt-3 block w-full text-xs" @change="selectAttachment(template, $event)" />
                            <p class="mt-1 text-xs text-gray-500">PDF, Word, Excel oder Bild, maximal 10 MB.</p>
                        </div>

                        <button type="button" class="mt-5 w-full rounded bg-zbb px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="saving === template.key" @click="save(template)">
                            {{ saving === template.key ? 'Speichert …' : 'Vorlage speichern' }}
                        </button>
                    </article>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
