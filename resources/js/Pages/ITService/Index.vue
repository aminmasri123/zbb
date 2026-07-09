<template>
  <Head title="IT-Service" />

  <AppLayout>
    <template #header>IT-Service</template>

    <div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-slate-950">IT-Service</h1>
            <p class="mt-1 text-sm text-slate-600">Tickets aller Standorte planen, bearbeiten und Geräte verwalten</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              v-if="itPermissions.canCreateTicket"
              @click="openTicketCreate"
              class="inline-flex items-center gap-2 rounded-md bg-zbb px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600"
            >
              <i class="la la-plus"></i>
              Ticket
            </button>
            <button
              v-if="itPermissions.canCreateDevice"
              @click="openDeviceCreate"
              class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
            >
              <i class="la la-desktop"></i>
              Gerät
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div v-for="stat in stats" :key="stat.label" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm font-medium text-slate-500">{{ stat.label }}</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ stat.value }}</div>
          </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex flex-col gap-4 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative w-full lg:max-w-xl">
              <i class="la la-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
              <input
                v-model="search"
                type="text"
                placeholder="Ticket, Gerät, Standort, Beschreibung suchen"
                class="w-full rounded-md border border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-zbb focus:ring-zbb"
              />
            </div>

            <div class="flex overflow-hidden rounded-md border border-slate-300 bg-white">
              <button
                v-for="tab in tabs"
                :key="tab.value"
                @click="activeTab = tab.value"
                class="px-4 py-2 text-sm font-semibold"
                :class="activeTab === tab.value ? 'bg-zbb text-white' : 'text-slate-700 hover:bg-slate-100'"
              >
                {{ tab.label }}
              </button>
            </div>
          </div>

          <div v-if="activeTab === 'tickets'" class="space-y-4 p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
              <select v-model="ticketFilters.status" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Status</option>
                <option v-for="option in ticketOptions.statuses" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <select v-model="ticketFilters.priority" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Prioritäten</option>
                <option v-for="option in ticketOptions.priorities" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <select v-model="ticketFilters.standortId" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Standorte</option>
                <option v-for="standort in standorte" :key="standort.id" :value="String(standort.id)">
                  {{ standort.name }}
                </option>
              </select>
              <select v-model="ticketFilters.assignee" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Zuständigkeiten</option>
                <option value="unassigned">Nicht zugewiesen</option>
                <option v-for="person in personal" :key="person.id" :value="String(person.id)">
                  {{ personName(person) }}
                </option>
              </select>
            </div>

            <div v-if="filteredTickets.length === 0" class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
              Keine IT-Tickets gefunden.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-[1180px] w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-100 text-left text-xs font-semibold uppercase text-slate-600">
                  <tr>
                    <th class="px-4 py-3">Ticket</th>
                    <th class="px-4 py-3">Thema</th>
                    <th class="px-4 py-3">Standort</th>
                    <th class="px-4 py-3">Gerät</th>
                    <th class="px-4 py-3">Priorität</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Zuständig</th>
                    <th class="px-4 py-3">Fällig</th>
                    <th class="px-4 py-3 text-right">Aktion</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                  <tr v-for="ticket in filteredTickets" :key="ticket.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-semibold text-slate-900">{{ ticket.ticket_nr || `IT-${ticket.id}` }}</td>
                    <td class="px-4 py-3">
                      <div class="font-medium text-slate-950">{{ ticket.titel }}</div>
                      <div class="mt-1 line-clamp-1 text-xs text-slate-500">{{ categoryLabel(ticket.kategorie) }} · {{ ticket.beschreibung || 'Keine Beschreibung' }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                      {{ ticket.standort?.name || standortName(ticket.standort_id) || '-' }}
                      <div v-if="ticket.raum" class="text-xs text-slate-500">Raum {{ ticket.raum }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ deviceLabel(ticket.geraet) }}</td>
                    <td class="px-4 py-3">
                      <span :class="priorityClass(ticket.prioritaet)" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                        {{ priorityLabel(ticket.prioritaet) }}
                      </span>
                    </td>
                    <td class="px-4 py-3">
                      <span :class="statusClass(ticket.status)" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                        {{ statusLabel(ticket.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ personName(ticket.zugewiesen_an_person) || '-' }}</td>
                    <td class="px-4 py-3" :class="dueClass(ticket)">{{ formatDate(ticket.faellig_am) || '-' }}</td>
                    <td class="px-4 py-3 text-right">
                      <button
                        v-if="itPermissions.canUpdateTicket"
                        @click="openTicketEdit(ticket)"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                      >
                        Bearbeiten
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-if="activeTab === 'geraete'" class="space-y-4 p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
              <select v-model="deviceFilters.status" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Status</option>
                <option v-for="option in deviceOptions.statuses" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <select v-model="deviceFilters.category" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Kategorien</option>
                <option v-for="option in deviceOptions.categories" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <select v-model="deviceFilters.standortId" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Standorte</option>
                <option v-for="standort in standorte" :key="standort.id" :value="String(standort.id)">
                  {{ standort.name }}
                </option>
              </select>
              <select v-model="deviceFilters.availability" class="rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Alle Verfügbarkeiten</option>
                <option value="1">Verfügbar</option>
                <option value="0">Nicht verfügbar</option>
              </select>
            </div>

            <div v-if="filteredDevices.length === 0" class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
              Keine Geräte gefunden.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-[1180px] w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-100 text-left text-xs font-semibold uppercase text-slate-600">
                  <tr>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Inventar / SN</th>
                    <th class="px-4 py-3">Gerät</th>
                    <th class="px-4 py-3">Standort</th>
                    <th class="px-4 py-3">Verantwortlich</th>
                    <th class="px-4 py-3">Netzwerk</th>
                    <th class="px-4 py-3">Wartung</th>
                    <th class="px-4 py-3">Tickets</th>
                    <th class="px-4 py-3 text-right">Aktion</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                  <tr v-for="geraet in filteredDevices" :key="geraet.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                      <span :class="deviceStatusClass(geraet.status)" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                        {{ deviceStatusLabel(geraet.status) }}
                      </span>
                      <div class="mt-1 text-xs" :class="geraet.verfuegbarkeit ? 'text-emerald-700' : 'text-red-600'">
                        {{ geraet.verfuegbarkeit ? 'verfügbar' : 'nicht verfügbar' }}
                      </div>
                    </td>
                    <td class="px-4 py-3">
                      <div class="font-semibold text-slate-900">{{ geraet.inventarnummer || geraet.productID || '-' }}</div>
                      <div class="text-xs text-slate-500">SN {{ geraet.sn }}</div>
                    </td>
                    <td class="px-4 py-3">
                      <div class="font-medium text-slate-950">{{ geraet.geraet }}</div>
                      <div class="text-xs text-slate-500">{{ geraet.hersteller }} {{ geraet.modell || '' }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                      {{ geraet.standort?.name || standortName(geraet.standort_id) || '-' }}
                      <div v-if="geraet.raum" class="text-xs text-slate-500">Raum {{ geraet.raum }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ personName(geraet.verantwortliche_person) || '-' }}</td>
                    <td class="px-4 py-3 text-slate-700">
                      <div>{{ geraet.ip_adresse || '-' }}</div>
                      <div class="text-xs text-slate-500">{{ geraet.betriebssystem || '' }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                      <div>{{ formatDate(geraet.naechste_wartung_am) || '-' }}</div>
                      <div class="text-xs text-slate-500">Garantie {{ formatDate(geraet.garantiefrist) || '-' }}</div>
                    </td>
                    <td class="px-4 py-3 font-semibold text-slate-900">{{ geraet.offene_it_tickets_count || 0 }}</td>
                    <td class="px-4 py-3 text-right">
                      <button
                        v-if="itPermissions.canUpdateDevice"
                        @click="openDeviceEdit(geraet)"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                      >
                        Bearbeiten
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="ticketModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
      <div class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-lg bg-white shadow-xl">
        <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-950">{{ ticketForm.id ? 'IT-Ticket bearbeiten' : 'IT-Ticket erfassen' }}</h2>
            <p class="mt-1 text-sm text-slate-500">Standort, Priorität, Zuständigkeit und Bearbeitung planen</p>
          </div>
          <button @click="closeTicketModal" class="rounded-md p-2 text-slate-500 hover:bg-slate-100">
            <i class="la la-times"></i>
          </button>
        </div>

        <form @submit.prevent="saveTicket" class="space-y-5 px-5 py-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Titel *</span>
              <input v-model="ticketForm.titel" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
              <small v-if="ticketErrors.titel" class="text-red-600">{{ ticketErrors.titel[0] }}</small>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Kategorie *</span>
              <select v-model="ticketForm.kategorie" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option v-for="option in ticketOptions.categories" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
              <small v-if="ticketErrors.kategorie" class="text-red-600">{{ ticketErrors.kategorie[0] }}</small>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Priorität *</span>
              <select v-model="ticketForm.prioritaet" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option v-for="option in ticketOptions.priorities" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
              <small v-if="ticketErrors.prioritaet" class="text-red-600">{{ ticketErrors.prioritaet[0] }}</small>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Standort</span>
              <select v-model="ticketForm.standort_id" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Kein Standort</option>
                <option v-for="standort in standorte" :key="standort.id" :value="standort.id">{{ standort.name }}</option>
              </select>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Gerät</span>
              <select v-model="ticketForm.geraet_id" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Kein Gerät</option>
                <option v-for="geraet in devices" :key="geraet.id" :value="geraet.id">{{ deviceLabel(geraet) }}</option>
              </select>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Raum</span>
              <input v-model="ticketForm.raum" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Betroffene Person</span>
              <select v-model="ticketForm.betroffene_personen_id" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Nicht gesetzt</option>
                <option v-for="person in personal" :key="person.id" :value="person.id">{{ personName(person) }}</option>
              </select>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Zuständig</span>
              <select v-model="ticketForm.zugewiesen_an_personen_id" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Nicht zugewiesen</option>
                <option v-for="person in personal" :key="person.id" :value="person.id">{{ personName(person) }}</option>
              </select>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Kontakt</span>
              <input v-model="ticketForm.kontakt" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>

            <label v-if="ticketForm.id" class="block">
              <span class="text-sm font-medium text-slate-700">Status</span>
              <select v-model="ticketForm.status" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option v-for="option in ticketOptions.statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
              <small v-if="ticketErrors.status" class="text-red-600">{{ ticketErrors.status[0] }}</small>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Fällig am</span>
              <input v-model="ticketForm.faellig_am" type="date" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Geplant am</span>
              <input v-model="ticketForm.geplant_am" type="datetime-local" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Beschreibung</span>
              <textarea v-model="ticketForm.beschreibung" rows="5" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb"></textarea>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Planung / nächste Schritte</span>
              <textarea v-model="ticketForm.planung" rows="5" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb"></textarea>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Lösung</span>
              <textarea v-model="ticketForm.loesung" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb"></textarea>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Interne Notiz</span>
              <textarea v-model="ticketForm.interne_notiz" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb"></textarea>
            </label>
          </div>

          <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <button
              v-if="ticketForm.id && itPermissions.canDeleteTicket"
              type="button"
              @click="deleteTicket"
              class="inline-flex items-center justify-center gap-2 rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
            >
              <i class="la la-trash"></i>
              Löschen
            </button>
            <div class="flex gap-2 sm:ml-auto">
              <button type="button" @click="closeTicketModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                Abbrechen
              </button>
              <button type="submit" :disabled="savingTicket" class="rounded-md bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-60">
                Speichern
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div v-if="deviceModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
      <div class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-lg bg-white shadow-xl">
        <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-950">{{ deviceForm.id ? 'Gerät bearbeiten' : 'Gerät anlegen' }}</h2>
            <p class="mt-1 text-sm text-slate-500">Inventar, Standort, Verantwortlichkeit und Wartung pflegen</p>
          </div>
          <button @click="closeDeviceModal" class="rounded-md p-2 text-slate-500 hover:bg-slate-100">
            <i class="la la-times"></i>
          </button>
        </div>

        <form @submit.prevent="saveDevice" class="space-y-5 px-5 py-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Seriennummer *</span>
              <input v-model="deviceForm.sn" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
              <small v-if="deviceErrors.sn" class="text-red-600">{{ deviceErrors.sn[0] }}</small>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Produkt ID *</span>
              <input v-model="deviceForm.productID" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
              <small v-if="deviceErrors.productID" class="text-red-600">{{ deviceErrors.productID[0] }}</small>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Inventarnummer</span>
              <input v-model="deviceForm.inventarnummer" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
              <small v-if="deviceErrors.inventarnummer" class="text-red-600">{{ deviceErrors.inventarnummer[0] }}</small>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Gerät *</span>
              <input v-model="deviceForm.geraet" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
              <small v-if="deviceErrors.geraet" class="text-red-600">{{ deviceErrors.geraet[0] }}</small>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Kategorie</span>
              <select v-model="deviceForm.kategorie" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Keine Kategorie</option>
                <option v-for="option in deviceOptions.categories" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Zustand *</span>
              <select v-model="deviceForm.zustand" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option v-for="option in deviceOptions.zustand" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
              <small v-if="deviceErrors.zustand" class="text-red-600">{{ deviceErrors.zustand[0] }}</small>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Status</span>
              <select v-model="deviceForm.status" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option v-for="option in deviceOptions.statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Standort</span>
              <select v-model="deviceForm.standort_id" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Kein Standort</option>
                <option v-for="standort in standorte" :key="standort.id" :value="standort.id">{{ standort.name }}</option>
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Raum</span>
              <input v-model="deviceForm.raum" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Verantwortlich</span>
              <select v-model="deviceForm.verantwortliche_personen_id" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb">
                <option value="">Nicht gesetzt</option>
                <option v-for="person in personal" :key="person.id" :value="person.id">{{ personName(person) }}</option>
              </select>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Hersteller *</span>
              <input v-model="deviceForm.hersteller" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
              <small v-if="deviceErrors.hersteller" class="text-red-600">{{ deviceErrors.hersteller[0] }}</small>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Modell</span>
              <input v-model="deviceForm.modell" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">IP-Adresse</span>
              <input v-model="deviceForm.ip_adresse" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">MAC-Adresse</span>
              <input v-model="deviceForm.mac_adresse" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Betriebssystem</span>
              <input v-model="deviceForm.betriebssystem" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Baujahr</span>
              <input v-model="deviceForm.baujahr" type="date" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Garantie bis</span>
              <input v-model="deviceForm.garantiefrist" type="date" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
              <small v-if="deviceErrors.garantiefrist" class="text-red-600">{{ deviceErrors.garantiefrist[0] }}</small>
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Lagerort</span>
              <input v-model="deviceForm.imLager" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>

            <label class="block">
              <span class="text-sm font-medium text-slate-700">Letzte Wartung</span>
              <input v-model="deviceForm.letzte_wartung_am" type="date" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>
            <label class="block">
              <span class="text-sm font-medium text-slate-700">Nächste Wartung</span>
              <input v-model="deviceForm.naechste_wartung_am" type="date" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb" />
            </label>
            <label class="flex items-center gap-3 pt-6">
              <input v-model="deviceForm.verfuegbarkeit" type="checkbox" class="rounded border-slate-300 text-zbb focus:ring-zbb" />
              <span class="text-sm font-medium text-slate-700">Gerät ist verfügbar</span>
            </label>
          </div>

          <label class="block">
            <span class="text-sm font-medium text-slate-700">Notiz</span>
            <textarea v-model="deviceForm.notiz" rows="4" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:border-zbb focus:ring-zbb"></textarea>
          </label>

          <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <button
              v-if="deviceForm.id && itPermissions.canDeleteDevice"
              type="button"
              @click="deleteDevice"
              class="inline-flex items-center justify-center gap-2 rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
            >
              <i class="la la-trash"></i>
              Löschen
            </button>
            <div class="flex gap-2 sm:ml-auto">
              <button type="button" @click="closeDeviceModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                Abbrechen
              </button>
              <button type="submit" :disabled="savingDevice" class="rounded-md bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-60">
                Speichern
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  tickets: { type: Array, default: () => [] },
  geraete: { type: Array, default: () => [] },
  standorte: { type: Array, default: () => [] },
  personal: { type: Array, default: () => [] },
  ticketOptions: { type: Object, default: () => ({ statuses: [], priorities: [], categories: [] }) },
  deviceOptions: { type: Object, default: () => ({ statuses: [], categories: [], zustand: [] }) },
  itPermissions: {
    type: Object,
    default: () => ({
      canCreateTicket: false,
      canUpdateTicket: false,
      canDeleteTicket: false,
      canCreateDevice: false,
      canUpdateDevice: false,
      canDeleteDevice: false,
    }),
  },
});

const tabs = [
  { label: 'Tickets', value: 'tickets' },
  { label: 'Geräte', value: 'geraete' },
];

const activeTab = ref('tickets');
const search = ref('');
const tickets = ref(JSON.parse(JSON.stringify(props.tickets || [])));
const devices = ref(JSON.parse(JSON.stringify(props.geraete || [])));
const standorte = computed(() => props.standorte || []);
const personal = computed(() => props.personal || []);
const ticketOptions = computed(() => props.ticketOptions || { statuses: [], priorities: [], categories: [] });
const deviceOptions = computed(() => props.deviceOptions || { statuses: [], categories: [], zustand: [] });
const itPermissions = computed(() => props.itPermissions || {});

const ticketFilters = ref({
  status: '',
  priority: '',
  standortId: '',
  assignee: '',
});

const deviceFilters = ref({
  status: '',
  category: '',
  standortId: '',
  availability: '',
});

const ticketModalOpen = ref(false);
const deviceModalOpen = ref(false);
const savingTicket = ref(false);
const savingDevice = ref(false);
const ticketErrors = ref({});
const deviceErrors = ref({});

const emptyTicketForm = () => ({
  id: null,
  titel: '',
  kategorie: 'hardware',
  prioritaet: 'normal',
  status: 'neu',
  standort_id: '',
  geraet_id: '',
  betroffene_personen_id: '',
  zugewiesen_an_personen_id: '',
  raum: '',
  kontakt: '',
  beschreibung: '',
  planung: '',
  loesung: '',
  interne_notiz: '',
  faellig_am: '',
  geplant_am: '',
});

const emptyDeviceForm = () => ({
  id: null,
  sn: '',
  productID: '',
  inventarnummer: '',
  geraet: '',
  kategorie: '',
  zustand: 'Neuwertig',
  status: 'aktiv',
  verfuegbarkeit: true,
  imLager: '',
  standort_id: '',
  verantwortliche_personen_id: '',
  raum: '',
  hersteller: '',
  modell: '',
  ip_adresse: '',
  mac_adresse: '',
  betriebssystem: '',
  baujahr: '',
  garantiefrist: '',
  letzte_wartung_am: '',
  naechste_wartung_am: '',
  notiz: '',
});

const ticketForm = ref(emptyTicketForm());
const deviceForm = ref(emptyDeviceForm());

const stats = computed(() => {
  const openTickets = tickets.value.filter((ticket) => !ticketClosed(ticket.status));
  const criticalTickets = openTickets.filter((ticket) => ticket.prioritaet === 'kritisch' || ticket.prioritaet === 'hoch');
  const overdueTickets = openTickets.filter((ticket) => isOverdue(ticket.faellig_am));

  return [
    { label: 'Offene Tickets', value: openTickets.length },
    { label: 'Hohe Priorität', value: criticalTickets.length },
    { label: 'Überfällig', value: overdueTickets.length },
    { label: 'IT-Geräte', value: devices.value.length },
  ];
});

const filteredTickets = computed(() => {
  const query = search.value.trim().toLowerCase();

  return tickets.value.filter((ticket) => {
    const matchesSearch = !query || [
      ticket.ticket_nr,
      ticket.titel,
      ticket.beschreibung,
      ticket.planung,
      ticket.loesung,
      ticket.standort?.name,
      ticket.raum,
      deviceLabel(ticket.geraet),
      personName(ticket.zugewiesen_an_person),
    ].some((value) => String(value || '').toLowerCase().includes(query));

    const matchesStatus = !ticketFilters.value.status || ticket.status === ticketFilters.value.status;
    const matchesPriority = !ticketFilters.value.priority || ticket.prioritaet === ticketFilters.value.priority;
    const matchesStandort = !ticketFilters.value.standortId || String(ticket.standort_id || '') === ticketFilters.value.standortId;
    const matchesAssignee = !ticketFilters.value.assignee
      || (ticketFilters.value.assignee === 'unassigned'
        ? !ticket.zugewiesen_an_personen_id
        : String(ticket.zugewiesen_an_personen_id || '') === ticketFilters.value.assignee);

    return matchesSearch && matchesStatus && matchesPriority && matchesStandort && matchesAssignee;
  });
});

const filteredDevices = computed(() => {
  const query = search.value.trim().toLowerCase();

  return devices.value.filter((geraet) => {
    const matchesSearch = !query || [
      geraet.sn,
      geraet.productID,
      geraet.inventarnummer,
      geraet.geraet,
      geraet.hersteller,
      geraet.modell,
      geraet.kategorie,
      geraet.standort?.name,
      geraet.raum,
      geraet.ip_adresse,
      geraet.betriebssystem,
      personName(geraet.verantwortliche_person),
    ].some((value) => String(value || '').toLowerCase().includes(query));

    const matchesStatus = !deviceFilters.value.status || (geraet.status || 'aktiv') === deviceFilters.value.status;
    const matchesCategory = !deviceFilters.value.category || geraet.kategorie === deviceFilters.value.category;
    const matchesStandort = !deviceFilters.value.standortId || String(geraet.standort_id || '') === deviceFilters.value.standortId;
    const matchesAvailability = deviceFilters.value.availability === ''
      || (deviceFilters.value.availability === '1' ? Boolean(geraet.verfuegbarkeit) : !geraet.verfuegbarkeit);

    return matchesSearch && matchesStatus && matchesCategory && matchesStandort && matchesAvailability;
  });
});

function openTicketCreate() {
  ticketForm.value = emptyTicketForm();
  ticketErrors.value = {};
  ticketModalOpen.value = true;
}

function openTicketEdit(ticket) {
  ticketForm.value = {
    ...emptyTicketForm(),
    ...JSON.parse(JSON.stringify(ticket)),
    standort_id: ticket.standort_id || '',
    geraet_id: ticket.geraet_id || '',
    betroffene_personen_id: ticket.betroffene_personen_id || '',
    zugewiesen_an_personen_id: ticket.zugewiesen_an_personen_id || '',
    faellig_am: toDateInput(ticket.faellig_am),
    geplant_am: toDateTimeLocal(ticket.geplant_am),
  };
  ticketErrors.value = {};
  ticketModalOpen.value = true;
}

function closeTicketModal() {
  ticketModalOpen.value = false;
  ticketForm.value = emptyTicketForm();
  ticketErrors.value = {};
}

async function saveTicket() {
  savingTicket.value = true;
  ticketErrors.value = {};

  try {
    const payload = nullablePayload(ticketForm.value, [
      'standort_id',
      'geraet_id',
      'betroffene_personen_id',
      'zugewiesen_an_personen_id',
      'raum',
      'kontakt',
      'beschreibung',
      'planung',
      'loesung',
      'interne_notiz',
      'faellig_am',
      'geplant_am',
    ]);

    const response = ticketForm.value.id
      ? await axios.put(route('it-service.tickets.update', ticketForm.value.id), payload)
      : await axios.post(route('it-service.tickets.store'), payload);

    upsertTicket(response.data.ticket);
    closeTicketModal();
    Swal.fire({ title: 'Gespeichert', icon: 'success', timer: 1200, showConfirmButton: false });
  } catch (error) {
    ticketErrors.value = error.response?.data?.errors || {};
    if (!error.response?.data?.errors) {
      Swal.fire({ title: 'Fehler', text: 'Ticket konnte nicht gespeichert werden.', icon: 'error' });
    }
  } finally {
    savingTicket.value = false;
  }
}

async function deleteTicket() {
  const confirmation = await Swal.fire({
    title: 'Ticket löschen?',
    text: 'Das Ticket wird dauerhaft entfernt.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Löschen',
    cancelButtonText: 'Abbrechen',
  });

  if (!confirmation.isConfirmed) return;

  try {
    await axios.delete(route('it-service.tickets.destroy', ticketForm.value.id));
    tickets.value = tickets.value.filter((ticket) => ticket.id !== ticketForm.value.id);
    closeTicketModal();
    Swal.fire({ title: 'Gelöscht', icon: 'success', timer: 1200, showConfirmButton: false });
  } catch (error) {
    Swal.fire({ title: 'Fehler', text: 'Ticket konnte nicht gelöscht werden.', icon: 'error' });
  }
}

function openDeviceCreate() {
  deviceForm.value = emptyDeviceForm();
  deviceErrors.value = {};
  deviceModalOpen.value = true;
}

function openDeviceEdit(geraet) {
  deviceForm.value = {
    ...emptyDeviceForm(),
    ...JSON.parse(JSON.stringify(geraet)),
    standort_id: geraet.standort_id || '',
    verantwortliche_personen_id: geraet.verantwortliche_personen_id || '',
    verfuegbarkeit: Boolean(geraet.verfuegbarkeit),
    baujahr: toDateInput(geraet.baujahr),
    garantiefrist: toDateInput(geraet.garantiefrist),
    letzte_wartung_am: toDateInput(geraet.letzte_wartung_am),
    naechste_wartung_am: toDateInput(geraet.naechste_wartung_am),
  };
  deviceErrors.value = {};
  deviceModalOpen.value = true;
}

function closeDeviceModal() {
  deviceModalOpen.value = false;
  deviceForm.value = emptyDeviceForm();
  deviceErrors.value = {};
}

async function saveDevice() {
  savingDevice.value = true;
  deviceErrors.value = {};

  try {
    const payload = nullablePayload(deviceForm.value, [
      'inventarnummer',
      'kategorie',
      'imLager',
      'standort_id',
      'verantwortliche_personen_id',
      'raum',
      'modell',
      'ip_adresse',
      'mac_adresse',
      'betriebssystem',
      'baujahr',
      'garantiefrist',
      'letzte_wartung_am',
      'naechste_wartung_am',
      'notiz',
    ]);

    const response = deviceForm.value.id
      ? await axios.put(route('it-service.geraete.update', deviceForm.value.id), payload)
      : await axios.post(route('it-service.geraete.store'), payload);

    upsertDevice(response.data.geraet);
    closeDeviceModal();
    Swal.fire({ title: 'Gespeichert', icon: 'success', timer: 1200, showConfirmButton: false });
  } catch (error) {
    deviceErrors.value = error.response?.data?.errors || {};
    if (!error.response?.data?.errors) {
      Swal.fire({ title: 'Fehler', text: 'Gerät konnte nicht gespeichert werden.', icon: 'error' });
    }
  } finally {
    savingDevice.value = false;
  }
}

async function deleteDevice() {
  const confirmation = await Swal.fire({
    title: 'Gerät löschen?',
    text: 'Wenn das Gerät bereits verwendet wurde, wird es ausgesondert.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Löschen',
    cancelButtonText: 'Abbrechen',
  });

  if (!confirmation.isConfirmed) return;

  try {
    const response = await axios.delete(route('it-service.geraete.destroy', deviceForm.value.id));
    if (response.data.geraet) {
      upsertDevice(response.data.geraet);
    } else {
      devices.value = devices.value.filter((geraet) => geraet.id !== deviceForm.value.id);
    }
    closeDeviceModal();
    Swal.fire({ title: response.data.deactivated ? 'Ausgesondert' : 'Gelöscht', icon: 'success', timer: 1200, showConfirmButton: false });
  } catch (error) {
    Swal.fire({ title: 'Fehler', text: 'Gerät konnte nicht gelöscht werden.', icon: 'error' });
  }
}

function upsertTicket(ticket) {
  const index = tickets.value.findIndex((item) => item.id === ticket.id);
  if (index === -1) {
    tickets.value.unshift(ticket);
  } else {
    tickets.value[index] = ticket;
  }
}

function upsertDevice(geraet) {
  const index = devices.value.findIndex((item) => item.id === geraet.id);
  if (index === -1) {
    devices.value.unshift(geraet);
  } else {
    devices.value[index] = geraet;
  }
}

function nullablePayload(form, nullableFields) {
  const payload = { ...form };
  delete payload.id;

  nullableFields.forEach((field) => {
    if (payload[field] === '') {
      payload[field] = null;
    }
  });

  ['standort_id', 'geraet_id', 'betroffene_personen_id', 'zugewiesen_an_personen_id', 'verantwortliche_personen_id'].forEach((field) => {
    if (payload[field] !== undefined && payload[field] !== null && payload[field] !== '') {
      payload[field] = Number(payload[field]);
    }
  });

  return payload;
}

function personName(person) {
  if (!person) return '';
  return [person.vorname, person.nachname].filter(Boolean).join(' ');
}

function standortName(id) {
  const standort = standorte.value.find((item) => Number(item.id) === Number(id));
  return standort?.name || '';
}

function deviceLabel(geraet) {
  if (!geraet) return '-';
  const inventory = geraet.inventarnummer || geraet.productID || geraet.sn;
  const name = [geraet.geraet, geraet.hersteller, geraet.modell].filter(Boolean).join(' ');
  return [inventory, name].filter(Boolean).join(' · ');
}

function labelFor(options, value) {
  return options.find((option) => option.value === value)?.label || value || '-';
}

function statusLabel(value) {
  return labelFor(ticketOptions.value.statuses, value);
}

function priorityLabel(value) {
  return labelFor(ticketOptions.value.priorities, value);
}

function categoryLabel(value) {
  return labelFor(ticketOptions.value.categories, value);
}

function deviceStatusLabel(value) {
  return labelFor(deviceOptions.value.statuses, value || 'aktiv');
}

function statusClass(status) {
  return {
    neu: 'bg-blue-50 text-blue-700',
    gesichtet: 'bg-slate-100 text-slate-700',
    geplant: 'bg-violet-50 text-violet-700',
    in_bearbeitung: 'bg-amber-50 text-amber-700',
    wartet_auf_rueckmeldung: 'bg-cyan-50 text-cyan-700',
    wartet_auf_extern: 'bg-orange-50 text-orange-700',
    geloest: 'bg-emerald-50 text-emerald-700',
    geschlossen: 'bg-slate-200 text-slate-700',
  }[status] || 'bg-slate-100 text-slate-700';
}

function priorityClass(priority) {
  return {
    niedrig: 'bg-slate-100 text-slate-700',
    normal: 'bg-blue-50 text-blue-700',
    hoch: 'bg-amber-50 text-amber-700',
    kritisch: 'bg-red-50 text-red-700',
  }[priority] || 'bg-slate-100 text-slate-700';
}

function deviceStatusClass(status) {
  return {
    aktiv: 'bg-emerald-50 text-emerald-700',
    reserve: 'bg-blue-50 text-blue-700',
    wartung: 'bg-amber-50 text-amber-700',
    defekt: 'bg-red-50 text-red-700',
    ausgesondert: 'bg-slate-200 text-slate-700',
  }[status || 'aktiv'] || 'bg-slate-100 text-slate-700';
}

function dueClass(ticket) {
  if (ticketClosed(ticket.status) || !ticket.faellig_am) return 'text-slate-700';
  return isOverdue(ticket.faellig_am) ? 'font-semibold text-red-600' : 'text-slate-700';
}

function ticketClosed(status) {
  return ['geloest', 'geschlossen'].includes(status);
}

function isOverdue(value) {
  if (!value) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const due = new Date(toDateInput(value));
  return !Number.isNaN(due.getTime()) && due < today;
}

function formatDate(value) {
  if (!value) return '';
  const normalized = toDateInput(value);
  if (!normalized) return '';
  const date = new Date(`${normalized}T00:00:00`);
  return date.toLocaleDateString('de-DE');
}

function toDateInput(value) {
  if (!value) return '';
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
    return value.slice(0, 10);
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function toDateTimeLocal(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function pad(value) {
  return String(value).padStart(2, '0');
}
</script>
