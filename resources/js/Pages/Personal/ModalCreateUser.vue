<script setup>
import { defineProps, defineEmits } from 'vue';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import MultiSelect from 'primevue/multiselect';
import Divider from 'primevue/divider';
import Modal from '@/Components/ModalForm.vue';   // <---- das fehlte

// Props (Daten von außen übergeben)
const props = defineProps({
  visible: { type: Boolean, default: false },
  newUser: { type: Object, required: true },
  rollen: { type: Array, default: () => [] }
})

// Events an die Eltern-Komponente
const emit = defineEmits(['close', 'add-user'])
</script>

<template>
  <Modal v-if="visible" @close="emit('close')">
    <!-- Header -->
    <template #header>{{$t('Benutzer anlegen')}}</template>

    <!-- Body -->
    <template #body>
      <form @submit.prevent="emit('add-user')">
        <div class="flex flex-col sm:flex-row">
          <div class="mb-4 w-full mx-1">
              <input type="hidden" name="_token" :value="$page.props.csrf_token">

            <FloatLabel variant="on">
              <InputText v-model="newUser.first_name" class="w-full" />
              <label>Vorname</label>
            </FloatLabel>
          </div>
          <div class="mb-4 w-full mx-1">
            <FloatLabel variant="on">
              <InputText v-model="newUser.last_name" class="w-full" />
              <label>Nachname</label>
            </FloatLabel>
          </div>
        </div>
        <div class="mb-4 w-full mx-1 pr-2">
            <FloatLabel variant="on">
              <InputText v-model="newUser.username" class="w-full" />
              <label>Benutzername</label>
            </FloatLabel>
        </div>
        <div class="mb-4 mx-1">
          <FloatLabel variant="on">
            <InputText v-model="newUser.email" class="w-full" />
            <label>E-Mail</label>
          </FloatLabel>
        </div>

        <div class="flex flex-col sm:flex-row">
          <div class="mb-4 w-full mx-1">
            <FloatLabel variant="on">
              <Password v-model="newUser.password" toggleMask class="w-full">
                <template #header>
                  <div class="font-semibold text-xm mb-4">Kennwort eingeben</div>
                </template>
                <template #footer>
                  <Divider />
                  <ul class="pl-2 ml-2 my-0 leading-normal">
                    <li :class="{ 'text-green-500': /[a-z]/.test(newUser.password), 'text-red-500': !/[a-z]/.test(newUser.password) }">
                      <span v-if="/[a-z]/.test(newUser.password)">✔️</span> Mindestens ein Kleinbuchstabe
                    </li>
                    <li :class="{ 'text-green-500': /[A-Z]/.test(newUser.password), 'text-red-500': !/[A-Z]/.test(newUser.password) }">
                      <span v-if="/[A-Z]/.test(newUser.password)">✔️</span> Mindestens ein Großbuchstabe
                    </li>
                    <li :class="{ 'text-green-500': /\d/.test(newUser.password), 'text-red-500': !/\d/.test(newUser.password) }">
                      <span v-if="/\d/.test(newUser.password)">✔️</span> Mindestens eine Ziffer
                    </li>
                    <li :class="{ 'text-green-500': newUser.password.length >= 8, 'text-red-500': newUser.password.length < 8 }">
                      <span v-if="newUser.password.length >= 8">✔️</span> Mindestens 8 Zeichen
                    </li>
                  </ul>
                </template>
              </Password>
              <label>Passwort</label>
            </FloatLabel>
          </div>
          <div class="mb-4 w-full mx-1">
            <FloatLabel variant="on">
              <Password v-model="newUser.password_confirmation" :feedback="false" toggleMask class="w-full" />
              <label>Passwort bestätigen</label>
            </FloatLabel>
          </div>
        </div>
        <div class="mb-4 w-full mx-1">
            <div class="field">
                <FloatLabel variant="on">
                    <MultiSelect v-model="newUser.rollen" :options="rollen" optionLabel="name" optionValue="id" display="chip" class="w-full" />
                    <label>Rollen</label>
                </FloatLabel>
          </div>
        </div>
      </form>
    </template>

    <!-- Footer -->
    <template #footer>
      <button @click="emit('close')" class="mr-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
      <button @click="emit('add-user')" class="bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
    </template>
  </Modal>
</template>
