<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const name = ref('');
const email = ref('');
const currency = ref('USD');
const hourlyRate = ref(0);
const notes = ref('');
const isSubmitting = ref(false);
const statusMessage = ref('');

async function createClient() {
    if (!name.value.trim()) {
        statusMessage.value = 'Client name is required.';
        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post('/clients/create', {
            name: name.value,
            email: email.value || null,
            currency: (currency.value || '').toUpperCase(),
            hourly_rate: Number(hourlyRate.value || 0),
            notes: notes.value || null,
        });

        statusMessage.value = 'Client created successfully.';
        name.value = '';
        email.value = '';
        currency.value = 'USD';
        hourlyRate.value = 0;
        notes.value = '';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to create client.';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <AppLayout title="Create Client">
        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-2xl rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                <div class="flex items-center justify-between gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Client</h1>
                    <Link :href="route('clients.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        Back to Clients
                    </Link>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Add a client once, then select that client during invoice creation.
                </p>

                <div class="mt-6 space-y-3">
                    <input
                        v-model="name"
                        type="text"
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        placeholder="Client name"
                    />
                    <input
                        v-model="email"
                        type="email"
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        placeholder="Client email (optional)"
                    />
                    <input
                        v-model="currency"
                        type="text"
                        maxlength="3"
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm uppercase text-gray-900 dark:text-white"
                        placeholder="Currency code (e.g. USD)"
                    />
                    <input
                        v-model="hourlyRate"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        placeholder="Hourly rate"
                    />
                    <textarea
                        v-model="notes"
                        rows="3"
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        placeholder="Client notes (optional)"
                    />

                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                        :disabled="isSubmitting"
                        @click="createClient"
                    >
                        {{ isSubmitting ? 'Creating...' : 'Create Client' }}
                    </button>
                </div>

                <p v-if="statusMessage" class="mt-4 text-sm text-gray-700 dark:text-gray-300">
                    {{ statusMessage }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
