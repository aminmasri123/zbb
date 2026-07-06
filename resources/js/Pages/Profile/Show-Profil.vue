<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import SectionBorder from '@/Components/SectionBorder.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

import { ref } from 'vue'

const activeTab = ref('profile') // default Tab
</script>

<template>
    <AppLayout title="Profile">
        <template #header>{{ $t('Profil') }}</template>

        <div>
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
                <div class="min-h-screen bg-gray-50">

                    <!-- Page Content -->
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

                        <!-- Page Header -->
                        <div class="mb-6">
                            <nav class="text-sm text-gray-500 mt-1">
                                <ol class="list-reset flex">
                                    <li>
                                        <a href="{{ route('dashboard') }}" class="text-zbb hover:underline">
                                            {{ $t('dashboard') }}
                                        </a>
                                    </li>
                                    <li class="mx-2">/</li>
                                    <li class="text-gray-700">{{ $t('Profil') }}</li>
                                </ol>
                            </nav>
                        </div>
                        <!-- /Page Header -->

                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="p-6">
                                <div class="flex flex-col md:flex-row items-start gap-6">

                                    <!-- Profilbild -->
                                    <div class="flex-shrink-0">
                                        <img class="h-24 w-24 rounded-full border"
                                           :src="$page.props.auth.user.profile_photo_url"
                                           :alt="$page.props.auth.user.name"
                                        >
                                    </div>

                                    <!-- Profilinformationen -->
                                    <div class="flex-1">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <h3 class="text-xl font-bold text-gray-900">
                                                    {{ $page.props.auth.user.name ?? '' }}
                                                </h3>
                                                <p class="text-sm text-gray-500">
<!--                                                     {{ $page.props.auth.user.user_log.titel ?? '' }} -->
                                                </p>

                                                <div class="mt-4 flex gap-2">
                                                    <Link :href="route('dashboard')"
                                                          class="px-4 py-2 bg-zbb text-white text-sm rounded hover:bg-orange-400">
                                                        {{ $t('dashboard') }}
                                                    </Link>
                                                    <Link :href="route('profile.show')"
                                                         class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded hover:bg-gray-300"
                                                    >
                                                        {{ $t('Einstellung') }}
                                                    </Link>
                                                </div>
                                            </div>

                                            <div>
                                                <ul class="space-y-2 text-sm text-gray-700">
                                                    <li class="flex">
                                                        <span class="w-32 font-medium">{{ $t('tel') }}:</span>
                                                        <span>
                                                            <!-- {{ $page.props.auth.user.user_log.tel ?? '---------------------' }} -->
                                                        </span>
                                                    </li>
                                                    <li class="flex">
                                                        <span class="w-32 font-medium">{{ $t('email') }}:</span>
                                                        <span>
                                                            {{ $page.props.auth.user.email ?? '---------------------' }}
                                                        </span>
                                                    </li>
                                                    <li class="flex">
                                                        <span class="w-32 font-medium">{{ $t('geburtstagdatum') }}:</span>
                                                        <span>
                                                            <!-- {{ $page.props.auth.user.user_log.geburtsdatum ?? '---------------------' }} -->
                                                        </span>
                                                    </li>
                                                    <li class="flex">
                                                        <span class="w-32 font-medium">{{ $t('geschlecht') }}:</span>
                                                        <span>
                                                            <!-- {{ $page.props.auth.user.user_log.geschlecht ?? '---------------------' }} -->
                                                        </span>
                                                    </li>
                                                    <span class="flex items-center gap-2">
                                                        <template v-if="$page.props.abteilungen && $page.props.abteilungen.length">
                                                            <div v-for="abteilung in $page.props.abteilungen" :key="abteilung.id">
                                                                <Link
                                                                    :href="`/abteilungen/${abteilung.id}`"
                                                                    class="text-zbb hover:underline mx-1"
                                                                    >
                                                                    {{ abteilung.name }}
                                                                </Link>
                                                            </div>
                                                        </template>
                                                        <span v-else>---------------------</span>
                                                    </span>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bearbeiten Button -->

                                        <div>
                                            <a data-target="#profile_edit" data-toggle="modal" href="#"
                                                class="text-gray-900 hover:text-indigo-600">
                                                <i class="las la-pencil-alt text-xl"></i>
                                            </a>
                                        </div>
                                </div>
                            </div>
                        </div>

                        <div class="w-full">
                            <!-- Tabs Navigation -->
                            <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                                <button
                                @click="activeTab = 'profile'"
                                :class="activeTab === 'profile'
                                    ? 'border-zbb text-zbb'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                >
                                Profile
                                </button>

                                <button
                                @click="activeTab = 'projects'"
                                :class="activeTab === 'projects'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                >
                                Projects
                                </button>

                                <button
                                @click="activeTab = 'bank'"
                                :class="activeTab === 'bank'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                                >
                                Bank & Statutory <small class="text-red-500">(Admin Only)</small>
                                </button>
                            </nav>
                            </div>

                            <!-- Tabs Content -->
                            <div class="mt-6">
                            <!-- Profile Tab -->
                            <div v-if="activeTab === 'profile'" class="space-y-6">
                                <div class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-semibold">Personal Informations</h3>
                                <ul class="mt-4 space-y-2 text-sm">
                                    <li class="flex justify-between"><span class="font-medium">Passport No.</span><span>9876543210</span></li>
                                    <li class="flex justify-between"><span class="font-medium">Tel</span><a href="tel:9876543210" class="text-indigo-600">9876543210</a></li>
                                    <li class="flex justify-between"><span class="font-medium">Nationality</span><span>Indian</span></li>
                                </ul>
                                </div>
                            </div>

                            <!-- Projects Tab -->
                            <div v-if="activeTab === 'projects'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                <div class="bg-white shadow rounded-lg p-6">
                                <h4 class="text-base font-semibold">Office Management</h4>
                                <p class="mt-2 text-sm text-gray-600">Lorem Ipsum is simply dummy text…</p>
                                <p class="mt-2 text-xs text-gray-500">Deadline: <span class="text-gray-800">17 Apr 2019</span></p>
                                <p class="mt-2 text-xs text-gray-500">Progress: <span class="text-green-600">40%</span></p>
                                <div class="w-full bg-gray-200 h-2 rounded mt-1">
                                    <div class="bg-green-500 h-2 rounded" style="width: 40%"></div>
                                </div>
                                </div>
                            </div>

                            <!-- Bank & Statutory Tab -->
                            <div v-if="activeTab === 'bank'" class="bg-white shadow rounded-lg p-6">
                                <h3 class="text-lg font-semibold">Basic Salary Information</h3>
                                <form class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium">Salary basis <span class="text-red-500">*</span></label>
                                    <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option>Select salary basis type</option>
                                    <option>Hourly</option>
                                    <option>Daily</option>
                                    <option>Weekly</option>
                                    <option>Monthly</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Salary amount</label>
                                    <input type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="0.00">
                                </div>
                                </form>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Modal -->
                <div id="profile_edit"
                    class=" hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                                {{ $t('profile_bearbeiten') }}
                            </h3>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
