<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    clients: {
        type: Array,
        default: () => [],
    },
});

const clients = ref(props.clients || []);
const editingClientId = ref(null);
const form = ref({
    name: '',
    email: '',
    notes: '',
});
const isSaving = ref(false);
const statusMessage = ref('');

function startEdit(client) {
    editingClientId.value = client.id;
    form.value = {
        name: client.name || '',
        email: client.email || '',
        notes: client.notes || '',
    };
    statusMessage.value = '';
}

function cancelEdit() {
    editingClientId.value = null;
    form.value = {
        name: '',
        email: '',
        notes: '',
    };
}

async function saveEdit(clientId) {
    if (isSaving.value) {
        return;
    }

    if (!form.value.name.trim()) {
        statusMessage.value = 'Client name is required.';
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(`/clients/${clientId}`, {
            name: form.value.name,
            email: form.value.email || null,
            notes: form.value.notes || null,
        });

        const updatedClient = response.data.client;
        const index = clients.value.findIndex((client) => client.id === clientId);

        if (index !== -1) {
            clients.value[index] = updatedClient;
        }

        statusMessage.value = response.data.message || 'Client updated.';
        cancelEdit();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update client.';
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <AppLayout title="Clients">
        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-4xl rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Clients</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Manage client details used for invoices and email delivery.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Link :href="route('dashboard')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                            Back to Timer
                        </Link>
                        <Link :href="route('clients.createPage')" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                            Add Client
                        </Link>
                    </div>
                </div>

                <p v-if="statusMessage" class="mt-4 text-sm text-gray-700 dark:text-gray-300">
                    {{ statusMessage }}
                </p>

                <div v-if="clients.length === 0" class="mt-6 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-6 text-sm text-gray-600 dark:text-gray-300">
                    No clients yet. Create your first client to start assigning them to invoices.
                </div>

                <div v-else class="mt-6 space-y-3">
                    <div
                        v-for="client in clients"
                        :key="client.id"
                        class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                    >
                        <div v-if="editingClientId !== client.id" class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-base font-semibold text-gray-900 dark:text-white">{{ client.name }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ client.email || 'No email' }}</p>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ client.notes || 'No notes' }}</p>
                            </div>

                            <button
                                type="button"
                                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700 transition"
                                @click="startEdit(client)"
                            >
                                Edit
                            </button>
                        </div>

                        <div v-else class="space-y-3">
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                placeholder="Client name"
                            />
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                placeholder="Client email"
                            />
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                placeholder="Client notes"
                            />

                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition disabled:opacity-60"
                                    :disabled="isSaving"
                                    @click="saveEdit(client.id)"
                                >
                                    {{ isSaving ? 'Saving...' : 'Save Changes' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 transition"
                                    @click="cancelEdit"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
