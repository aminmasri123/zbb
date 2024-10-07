<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'


const form = reactive({
  first_name: null,
  last_name: null,
  email: null,
})

function submitUser() {
  router.post('/users-store', form)
}
export default {
    // Komponente referenzieren
    components: {
        AppLayout,
    },
};
</script>



<template>
    <app-layout>
        <!-- Header Slot -->

        <template #header>
            <div class="page-header">
                <div class="flex items-center">
                    <div class="flex-grow">
                        <h3 class="text-xl font-semibold">Schule
                            <i class="las la-building text-xl ml-2"></i>
                        </h3>
                        <ul class="flex pl-0  text-gray-600 text-sm">
                            <li><a :href="route('dashboard')" class="text-gray-500 no-underline">Dashboard/</a></li>
                            <li class="text-gray-500">Schule Anlegen</li>
                        </ul>
                    </div>
                    <div class="ml-auto">
                        <a href="#" class="btna no-underline flex items-center space-x-2 bg-orange-500 px-4 py-2 rounded shadow hover:bg-white hover:border hover:border-orange-500 text-white">
                            <i class="las la-list-ul"></i>
                            <span>Schulliste</span>
                        </a>
                    </div>
                </div>
            </div>

        </template>
        <!-- Page Content -->
        <div class="content container m-4 mx-auto">
            <!-- Page Header -->


            <div class="bg-white rounded-lg shadow-lg p-6 pt-3">
                <div class="items-center">
                    <h5 class="text-lg font-semibold">Schule Anlegen</h5>
                    <div class="card-border border-b-2"></div>
                </div>
                <div class="mt-4">
                    <form @submit.prevent="submitUser">
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Schulform<span class="text-red-500">*</span></label>
                                <div class="flex space-x-4">
                                    <div class="flex items-center">
                                        <input class="form-radio text-blue-500" type="radio" name="schulform" id="gemeinschaftsschule" value="0" checked>
                                        <label for="gemeinschaftsschule" class="ml-2">Gemeinschaftsschule</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input class="form-radio text-blue-500" type="radio" name="schulform" id="foederschule" value="1">
                                        <label for="foederschule" class="ml-2">Föderschule</label>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="name" class="block text-gray-700 mb-2">Schule<span class="text-red-500">*</span></label>
                                <input id="name" type="text" class="mt-1 block w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring focus:ring-blue-500" name="name" required autofocus>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="anrede" class="block text-gray-700 mb-2">Anrede<span class="text-red-500">*</span></label>
                                    <select id="anrede" name="anrede" class="block w-full mt-1 border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring focus:ring-blue-500" required>
                                        <option value="none" selected disabled hidden>Anrede wählen</option>
                                        <option value="Frau">Frau</option>
                                        <option value="Herr">Herr</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="ansprechpartner" class="block text-gray-700 mb-2">Ansprechpartner<span class="text-red-500">*</span></label>
                                    <input id="ansprechpartner" type="text" class="mt-1 block w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring focus:ring-blue-500" name="ansprechpartner" required>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="tel" class="block text-gray-700 mb-2">Telefon<span class="text-red-500">*</span></label>
                                    <input id="tel" type="text" class="mt-1 block w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring focus:ring-blue-500" name="tel" required>
                                </div>

                                <div>
                                    <label for="handy" class="block text-gray-700 mb-2">Handy</label>
                                    <input id="handy" type="text" class="mt-1 block w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring focus:ring-blue-500" name="handy">
                                </div>
                            </div>

                            <div>
                                <label for="adresse" class="block text-gray-700 mb-2">Adresse</label>
                                <input id="adresse" type="text" class="mt-1 block w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring focus:ring-blue-500" name="adresse">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="email" class="block text-gray-700 mb-2">E-Mail</label>
                                    <input id="email" type="text" class="mt-1 block w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring focus:ring-blue-500" name="email">
                                </div>

                                <div>
                                    <label for="webseite" class="block text-gray-700 mb-2">Webseite</label>
                                    <input id="webseite" type="text" class="mt-1 block w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring focus:ring-blue-500" name="webseite">
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="w-full bg-orange-500 text-white px-4 py-2 rounded shadow hover:bg-blue-600">Speichern</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </app-layout>
</template>

