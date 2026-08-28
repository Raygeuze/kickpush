<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const canManageNonTimerRecords = computed(() => page.props.auth?.user?.current_team?.can_manage_non_timer_records !== false);

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
    clientYearSummaries: {
        type: Array,
        default: () => [],
    },
});

const selectedClientId = ref(props.selectedClientId ? String(props.selectedClientId) : '');
const selectedFinancialYearId = ref(String(props.selectedFinancialYearId));
const invoicesList = ref([...props.invoices]);
const markingPaidIds = ref([]);
const deletingInvoiceIds = ref([]);

watch(
    () => props.invoices,
    (nextInvoices) => {
        invoicesList.value = [...(nextInvoices || [])];
        markingPaidIds.value = [];
        deletingInvoiceIds.value = [];
    }
);

watch(
    () => props.selectedClientId,
    (nextClientId) => {
        selectedClientId.value = nextClientId ? String(nextClientId) : '';
    }
);

watch(
    () => props.selectedFinancialYearId,
    (nextFinancialYearId) => {
        selectedFinancialYearId.value = String(nextFinancialYearId);
    }
);

const financialYearOptions = computed(() => {
    return props.financialYears.map((financialYear) => ({
        value: String(financialYear.id),
        label: financialYear.label,
    }));
});

const snapshotSummaries = computed(() => {
    const summaries = props.clientYearSummaries || [];

    if (summaries.length === 0) {
        return [];
    }

    if (props.selectedClientId) {
        return summaries.map((summary) => ({
            ...summary,
            paid: {
                ...summary.paid,
                clients: Number(summary.paid?.invoice_count || 0) > 0
                    ? [{ client_name: summary.client_name, invoice_count: Number(summary.paid?.invoice_count || 0) }]
                    : [],
            },
            sent_not_paid: {
                ...summary.sent_not_paid,
                clients: Number(summary.sent_not_paid?.invoice_count || 0) > 0
                    ? [{ client_name: summary.client_name, invoice_count: Number(summary.sent_not_paid?.invoice_count || 0) }]
                    : [],
            },
            draft: {
                ...summary.draft,
                clients: Number(summary.draft?.invoice_count || 0) > 0
                    ? [{ client_name: summary.client_name, invoice_count: Number(summary.draft?.invoice_count || 0) }]
                    : [],
            },
            overdue: {
                ...summary.overdue,
                clients: Number(summary.overdue?.invoice_count || 0) > 0
                    ? [{ client_name: summary.client_name, invoice_count: Number(summary.overdue?.invoice_count || 0) }]
                    : [],
            },
        }));
    }

    const totals = summaries.reduce((accumulator, summary) => {
        const clientName = String(summary.client_name || 'Unassigned Client');
        const paidCount = Number(summary.paid?.invoice_count || 0);
        const sentCount = Number(summary.sent_not_paid?.invoice_count || 0);
        const draftCount = Number(summary.draft?.invoice_count || 0);
        const overdueCount = Number(summary.overdue?.invoice_count || 0);

        accumulator.paid.invoice_count += paidCount;
        accumulator.sent_not_paid.invoice_count += sentCount;
        accumulator.draft.invoice_count += draftCount;
        accumulator.overdue.invoice_count += overdueCount;

        if (paidCount > 0) {
            accumulator.paid.clientsMap[clientName] = (accumulator.paid.clientsMap[clientName] || 0) + paidCount;
        }

        if (sentCount > 0) {
            accumulator.sent_not_paid.clientsMap[clientName] = (accumulator.sent_not_paid.clientsMap[clientName] || 0) + sentCount;
        }

        if (draftCount > 0) {
            accumulator.draft.clientsMap[clientName] = (accumulator.draft.clientsMap[clientName] || 0) + draftCount;
        }

        if (overdueCount > 0) {
            accumulator.overdue.clientsMap[clientName] = (accumulator.overdue.clientsMap[clientName] || 0) + overdueCount;
        }

        return accumulator;
    }, {
        client_id: 'all-clients',
        client_name: 'All Clients',
        paid: { invoice_count: 0, clientsMap: {} },
        sent_not_paid: { invoice_count: 0, clientsMap: {} },
        draft: { invoice_count: 0, clientsMap: {} },
        overdue: { invoice_count: 0, clientsMap: {} },
    });

    totals.paid.clients = Object.entries(totals.paid.clientsMap)
        .map(([client_name, invoice_count]) => ({ client_name, invoice_count }))
        .sort((left, right) => left.client_name.localeCompare(right.client_name));
    totals.sent_not_paid.clients = Object.entries(totals.sent_not_paid.clientsMap)
        .map(([client_name, invoice_count]) => ({ client_name, invoice_count }))
        .sort((left, right) => left.client_name.localeCompare(right.client_name));
    totals.draft.clients = Object.entries(totals.draft.clientsMap)
        .map(([client_name, invoice_count]) => ({ client_name, invoice_count }))
        .sort((left, right) => left.client_name.localeCompare(right.client_name));
    totals.overdue.clients = Object.entries(totals.overdue.clientsMap)
        .map(([client_name, invoice_count]) => ({ client_name, invoice_count }))
        .sort((left, right) => left.client_name.localeCompare(right.client_name));

    delete totals.paid.clientsMap;
    delete totals.sent_not_paid.clientsMap;
    delete totals.draft.clientsMap;
    delete totals.overdue.clientsMap;

    return [totals];
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

function isDeletingInvoice(invoiceId) {
    return deletingInvoiceIds.value.includes(invoiceId);
}

async function markPaid(invoiceId) {
    if (!canManageNonTimerRecords.value) {
        return;
    }

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

async function deleteInvoice(invoiceId) {
    if (!canManageNonTimerRecords.value) {
        return;
    }

    if (isDeletingInvoice(invoiceId)) {
        return;
    }

    if (!window.confirm('Delete this invoice? This cannot be undone.')) {
        return;
    }

    deletingInvoiceIds.value.push(invoiceId);

    try {
        await axios.delete(`/invoices/${invoiceId}`);
        invoicesList.value = invoicesList.value.filter((invoice) => invoice.id !== invoiceId);
    } catch {
        // Keep behavior simple; user can retry deletion.
    } finally {
        deletingInvoiceIds.value = deletingInvoiceIds.value.filter((id) => id !== invoiceId);
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
                                v-if="$page.props.auth.user?.current_team?.is_owner"
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

                    <p v-if="!canManageNonTimerRecords" class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                        Your role is view-only for invoices outside timer session actions.
                    </p>

                    <div class="mt-4">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Client Year Snapshot</h3>

                        <p v-if="snapshotSummaries.length === 0" class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            No client summaries available for this financial year filter.
                        </p>

                        <div v-else class="mt-3 grid grid-cols-1 gap-3">
                            <div
                                v-for="summary in snapshotSummaries"
                                :key="`client-year-summary-${summary.client_id ?? 'unassigned'}`"
                                class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ summary.client_name }}</p>
                                </div>

                                <div class="mt-3 grid grid-cols-1 lg:grid-cols-4 gap-2 text-xs">
                                    <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 p-2">
                                        <p class="font-semibold text-emerald-700 dark:text-emerald-300">Paid</p>
                                        <p class="text-gray-700 dark:text-gray-200">{{ Number(summary.paid?.invoice_count || 0) }} invoices</p>
                                        <p v-if="!(summary.paid?.clients || []).length" class="mt-1 text-gray-600 dark:text-gray-300">No client invoices</p>
                                        <div v-else class="mt-1 space-y-0.5">
                                            <p
                                                v-for="clientBreakdown in summary.paid.clients"
                                                :key="`paid-${summary.client_id}-${clientBreakdown.client_name}`"
                                                class="text-gray-600 dark:text-gray-300"
                                            >
                                                {{ clientBreakdown.client_name }}: {{ Number(clientBreakdown.invoice_count || 0) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-cyan-50 dark:bg-cyan-900/20 p-2">
                                        <p class="font-semibold text-cyan-700 dark:text-cyan-300">Sent</p>
                                        <p class="text-gray-700 dark:text-gray-200">{{ Number(summary.sent_not_paid?.invoice_count || 0) }} invoices</p>
                                        <p v-if="!(summary.sent_not_paid?.clients || []).length" class="mt-1 text-gray-600 dark:text-gray-300">No client invoices</p>
                                        <div v-else class="mt-1 space-y-0.5">
                                            <p
                                                v-for="clientBreakdown in summary.sent_not_paid.clients"
                                                :key="`sent-${summary.client_id}-${clientBreakdown.client_name}`"
                                                class="text-gray-600 dark:text-gray-300"
                                            >
                                                {{ clientBreakdown.client_name }}: {{ Number(clientBreakdown.invoice_count || 0) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 p-2">
                                        <p class="font-semibold text-indigo-700 dark:text-indigo-300">In Progress</p>
                                        <p class="text-gray-700 dark:text-gray-200">{{ Number(summary.draft?.invoice_count || 0) }} invoices</p>
                                        <p v-if="!(summary.draft?.clients || []).length" class="mt-1 text-gray-600 dark:text-gray-300">No client invoices</p>
                                        <div v-else class="mt-1 space-y-0.5">
                                            <p
                                                v-for="clientBreakdown in summary.draft.clients"
                                                :key="`draft-${summary.client_id}-${clientBreakdown.client_name}`"
                                                class="text-gray-600 dark:text-gray-300"
                                            >
                                                {{ clientBreakdown.client_name }}: {{ Number(clientBreakdown.invoice_count || 0) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-rose-50 dark:bg-rose-900/20 p-2">
                                        <p class="font-semibold text-rose-700 dark:text-rose-300">Overdue</p>
                                        <p class="text-gray-700 dark:text-gray-200">{{ Number(summary.overdue?.invoice_count || 0) }} invoices</p>
                                        <p v-if="!(summary.overdue?.clients || []).length" class="mt-1 text-gray-600 dark:text-gray-300">No client invoices</p>
                                        <div v-else class="mt-1 space-y-0.5">
                                            <p
                                                v-for="clientBreakdown in summary.overdue.clients"
                                                :key="`overdue-${summary.client_id}-${clientBreakdown.client_name}`"
                                                class="text-gray-600 dark:text-gray-300"
                                            >
                                                {{ clientBreakdown.client_name }}: {{ Number(clientBreakdown.invoice_count || 0) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="invoicesList.length === 0" class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        No invoices found for this filter.
                    </p>

                    <div v-else class="mt-4 space-y-3">
                        <Link
                            v-for="invoice in invoicesList"
                            :key="invoice.id"
                            :href="`/invoices/${invoice.id}`"
                            class="block rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:border-blue-400 dark:hover:border-blue-500 transition"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ invoice.client ? invoice.client.name : 'Unassigned Client' }} - {{ formatInvoiceId(invoice.id) }}
                                    </p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Created: {{ formatDate(invoice.created_at) }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Issued: {{ formatDate(invoice.issued_at) }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Due: {{ formatDate(invoice.due_at) }}</p>
                                </div>

                                <div class="flex flex-col items-end gap-2">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="invoice.status === 'paid' ? 'bg-green-700 text-white' : (invoice.is_finalized ? 'bg-gray-800 text-white' : 'bg-emerald-100 text-emerald-700')"
                                        >
                                            {{ invoice.status === 'paid' ? 'Paid' : (invoice.is_finalized ? 'Finalized' : 'Draft') }}
                                        </span>

                                        <button
                                            v-if="canManageNonTimerRecords && !invoice.is_finalized"
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600 text-white hover:bg-red-700 transition disabled:opacity-60"
                                            :disabled="isDeletingInvoice(invoice.id)"
                                            title="Delete Invoice"
                                            aria-label="Delete Invoice"
                                            @click.prevent="deleteInvoice(invoice.id)"
                                        >
                                            <span v-if="isDeletingInvoice(invoice.id)" class="text-[10px] font-semibold">...</span>
                                            <svg v-else viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M3 6h18" />
                                                <path d="M8 6V4h8v2" />
                                                <path d="M19 6l-1 14H6L5 6" />
                                                <path d="M10 11v6" />
                                                <path d="M14 11v6" />
                                            </svg>
                                        </button>
                                    </div>

                                    <button
                                        v-if="canManageNonTimerRecords && invoice.status === 'finalized'"
                                        type="button"
                                        class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700 transition disabled:opacity-60"
                                        :disabled="isMarkingPaid(invoice.id) || isDeletingInvoice(invoice.id)"
                                        @click.prevent="markPaid(invoice.id)"
                                    >
                                        {{ isMarkingPaid(invoice.id) ? 'Marking...' : 'Mark As Paid' }}
                                    </button>

                                    <Link
                                        v-if="$page.props.auth.user?.current_team?.is_owner"
                                        :href="route('invoices.taxSummary', invoice.id)"
                                        class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                        @click.prevent
                                    >
                                        Tax Summary
                                    </Link>

                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
