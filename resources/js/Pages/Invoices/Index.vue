<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    clients: {
        type: Array,
        default: () => [],
    },
    selectedClientId: {
        type: [Number, String, null],
        default: null,
    },
    financialYears: {
        type: Array,
        default: () => [],
    },
    selectedFinancialYearId: {
        type: Number,
        required: true,
    },
    currentFinancialYearId: {
        type: Number,
        required: true,
    },
    selectedFinancialYearLabel: {
        type: String,
        required: true,
    },
    invoices: {
        type: Array,
        default: () => [],
    },
});

const selectedClientId = ref(props.selectedClientId ? String(props.selectedClientId) : '');
const selectedFinancialYearId = ref(String(props.selectedFinancialYearId));
const invoicesList = ref([...props.invoices]);
const markingPaidIds = ref([]);

const financialYearOptions = computed(() => {
    return props.financialYears.map((financialYear) => ({
        value: String(financialYear.id),
        label: financialYear.label,
    }));
});

const selectedClientName = computed(() => {
    if (!selectedClientId.value) {
        return 'All clients';
    }

    const found = props.clients.find((client) => String(client.id) === selectedClientId.value);

    return found ? found.name : 'Selected client';
});

function applyFilter() {
    router.get('/invoices', {
        client_id: selectedClientId.value || undefined,
        financial_year_id: Number(selectedFinancialYearId.value),
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function clearFilter() {
    selectedClientId.value = '';
    selectedFinancialYearId.value = String(props.currentFinancialYearId);
    applyFilter();
}

function formatInvoiceId(id) {
    return `INV${id}`;
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function isMarkingPaid(invoiceId) {
    return markingPaidIds.value.includes(invoiceId);
}

async function markPaid(invoiceId) {
    if (isMarkingPaid(invoiceId)) {
        return;
    }

    markingPaidIds.value.push(invoiceId);

    try {
        const response = await axios.post(`/invoices/${invoiceId}/mark-paid`);
        const updated = response.data.invoice;

        invoicesList.value = invoicesList.value.map((invoice) => {
            if (invoice.id !== updated.id) {
                return invoice;
            }

            return {
                ...invoice,
                ...updated,
            };
        });
    } catch {
        // Keep page interaction simple here; status badges remain unchanged on failure.
    } finally {
        markingPaidIds.value = markingPaidIds.value.filter((id) => id !== invoiceId);
    }
}
</script>

<template>
    <AppLayout title="Invoices">
        <Head title="Invoices" />

        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-6xl space-y-6">
                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Invoices By Client</h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Filter invoices by client and New Zealand financial year.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Link
                                :href="route('invoices.financialYearTaxSummary')"
                                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition"
                            >
                                Financial Year Tax Summary
                            </Link>
                            <Link href="/" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                Back to Timer
                            </Link>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto_auto] gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Client</label>
                            <select
                                v-model="selectedClientId"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            >
                                <option value="">All clients</option>
                                <option v-for="client in clients" :key="client.id" :value="String(client.id)">
                                    {{ client.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Financial Year (NZ)</label>
                            <select
                                v-model="selectedFinancialYearId"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            >
                                <option v-for="financialYear in financialYearOptions" :key="financialYear.value" :value="financialYear.value">
                                    {{ financialYear.label }}
                                </option>
                            </select>
                        </div>

                        <button
                            type="button"
                            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition"
                            @click="applyFilter"
                        >
                            Apply Filter
                        </button>

                        <button
                            type="button"
                            class="rounded-xl bg-gray-200 dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-700 transition"
                            @click="clearFilter"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ selectedClientName }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ invoicesList.length }} invoice(s)</p>
                    </div>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Financial year: {{ selectedFinancialYearLabel }}</p>

                    <p v-if="invoicesList.length === 0" class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        No invoices found for this filter.
                    </p>

                    <div v-else class="mt-4 space-y-3">
                        <div
                            v-for="invoice in invoicesList"
                            :key="invoice.id"
                            class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatInvoiceId(invoice.id) }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Client: {{ invoice.client ? invoice.client.name : 'Unassigned' }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Created: {{ formatDate(invoice.created_at) }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Issued: {{ formatDate(invoice.issued_at) }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Due: {{ formatDate(invoice.due_at) }}</p>
                                </div>

                                <div class="flex flex-col items-end gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="invoice.status === 'paid' ? 'bg-green-700 text-white' : (invoice.is_finalized ? 'bg-gray-800 text-white' : 'bg-emerald-100 text-emerald-700')"
                                    >
                                        {{ invoice.status === 'paid' ? 'Paid' : (invoice.is_finalized ? 'Finalized' : 'Draft') }}
                                    </span>

                                    <button
                                        v-if="invoice.status === 'finalized'"
                                        type="button"
                                        class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700 transition disabled:opacity-60"
                                        :disabled="isMarkingPaid(invoice.id)"
                                        @click="markPaid(invoice.id)"
                                    >
                                        {{ isMarkingPaid(invoice.id) ? 'Marking...' : 'Mark As Paid' }}
                                    </button>

                                    <Link
                                        :href="`/invoices/${invoice.id}`"
                                        class="text-sm font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                                    >
                                        Open Invoice
                                    </Link>

                                    <Link
                                        :href="route('invoices.taxSummary', invoice.id)"
                                        class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                    >
                                        Tax Summary
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
