<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    assignedSessions: {
        type: Array,
        default: () => [],
    },
    availableSessions: {
        type: Array,
        default: () => [],
    },
    financialYears: {
        type: Array,
        default: () => [],
    },
    expenses: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            sessions_count: 0,
            total_duration_seconds: 0,
            total_expenses_amount: 0,
            total_billable_amount: 0,
        }),
    },
});

const invoice = ref(props.invoice);
const assignedSessions = ref(props.assignedSessions);
const availableSessions = ref(props.availableSessions);
const expenses = ref(props.expenses);
const summary = ref(props.summary);
const financialYears = ref(props.financialYears);
const selectedFinancialYearId = ref(props.invoice?.financial_year_id ? String(props.invoice.financial_year_id) : '');
const statusMessage = ref('');
const isFinalizing = ref(false);
const isMarkingPaid = ref(false);
const busySessionIds = ref([]);
const busyExpenseIds = ref([]);
const isSubmittingExpense = ref(false);
const isSubmittingManualSession = ref(false);
const isInlineTimerLoading = ref(false);
const isSavingFinancialYear = ref(false);
const isInlineTimerRunning = ref(false);
const isInlineTimerPaused = ref(false);
const inlineElapsedSeconds = ref(0);
const inlineActiveSessionId = ref(null);
const expenseName = ref('');
const expenseDescription = ref('');
const expenseAmount = ref('');
const manualDurationMinutes = ref('');

function getDefaultManualStartedAt() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const manualStartedAt = ref(getDefaultManualStartedAt());
let inlineIntervalId = null;

const isFinalized = computed(() => invoice.value?.is_finalized === true);
const isPaid = computed(() => invoice.value?.status === 'paid');
const sortedAssignedSessions = computed(() => {
    return [...assignedSessions.value].sort((a, b) => {
        const aDate = new Date(a?.started_at || a?.created_at || 0).getTime();
        const bDate = new Date(b?.started_at || b?.created_at || 0).getTime();

        return aDate - bDate;
    });
});

function formatInvoiceId(invoiceId) {
    return `INV${invoiceId}`;
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatDuration(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;

    return `${hours.toString().padStart(2, '0')}:${minutes
        .toString()
        .padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

function formatCurrency(amount) {
    const value = Number(amount || 0);

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
}

function startInlineTicker() {
    if (inlineIntervalId) {
        return;
    }

    inlineIntervalId = setInterval(() => {
        inlineElapsedSeconds.value += 1;
    }, 1000);
}

function stopInlineTicker() {
    if (!inlineIntervalId) {
        return;
    }

    clearInterval(inlineIntervalId);
    inlineIntervalId = null;
}

function isBusy(sessionId) {
    return busySessionIds.value.includes(sessionId);
}

function applyPayload(data) {
    invoice.value = data.invoice;
    selectedFinancialYearId.value = data.invoice?.financial_year_id ? String(data.invoice.financial_year_id) : '';
    assignedSessions.value = data.assigned_sessions || [];
    availableSessions.value = data.available_sessions || [];
    expenses.value = data.expenses || [];
    summary.value = data.summary || {
        sessions_count: 0,
        total_duration_seconds: 0,
        total_expenses_amount: 0,
        total_billable_amount: 0,
    };
}

async function assignFinancialYear() {
    if (!selectedFinancialYearId.value || isSavingFinancialYear.value) {
        return;
    }

    isSavingFinancialYear.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/financial-year`, {
            financial_year_id: Number(selectedFinancialYearId.value),
        });

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Invoice financial year updated.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update invoice financial year.';
    } finally {
        isSavingFinancialYear.value = false;
    }
}

function isExpenseBusy(expenseId) {
    return busyExpenseIds.value.includes(expenseId);
}

async function addSession(sessionId) {
    if (isFinalized.value || isBusy(sessionId)) {
        return;
    }

    busySessionIds.value.push(sessionId);

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/sessions`, {
            session_id: sessionId,
        });

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Session added to invoice.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to add session to invoice.';
    } finally {
        busySessionIds.value = busySessionIds.value.filter((id) => id !== sessionId);
    }
}

async function createManualSession() {
    if (isFinalized.value || isSubmittingManualSession.value) {
        return;
    }

    if (!manualDurationMinutes.value || Number(manualDurationMinutes.value) <= 0) {
        statusMessage.value = 'Enter a manual session duration greater than 0 minutes.';
        return;
    }

    isSubmittingManualSession.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/sessions/manual`, {
            duration_minutes: Number(manualDurationMinutes.value),
            started_at: manualStartedAt.value || null,
        });

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Manual timer session created.';
        manualDurationMinutes.value = '';
        manualStartedAt.value = getDefaultManualStartedAt();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to create manual timer session.';
    } finally {
        isSubmittingManualSession.value = false;
    }
}

async function loadInlineTimerStatus() {
    try {
        const response = await axios.get(`/invoices/${invoice.value.id}/timer/status`);

        if (response.data.active && response.data.session) {
            inlineActiveSessionId.value = response.data.session.id;
            inlineElapsedSeconds.value = Math.max(0, Number(response.data.elapsed_seconds || 0));
            isInlineTimerRunning.value = Boolean(response.data.running);
            isInlineTimerPaused.value = Boolean(response.data.paused);

            if (isInlineTimerRunning.value) {
                startInlineTicker();
            } else {
                stopInlineTicker();
            }
            return;
        }

        isInlineTimerRunning.value = false;
        isInlineTimerPaused.value = false;
        inlineActiveSessionId.value = null;
        inlineElapsedSeconds.value = 0;
        stopInlineTicker();

        if (response.data.message) {
            statusMessage.value = response.data.message;
        }
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to load inline timer status.';
    }
}

async function startInlineTimer() {
    if (isFinalized.value || isInlineTimerLoading.value) {
        return;
    }

    isInlineTimerLoading.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/timer/start`);
        const session = response.data.session;
        const startedAt = new Date(session.started_at);

        inlineActiveSessionId.value = session.id;
        inlineElapsedSeconds.value = Math.max(0, Math.floor((new Date() - startedAt) / 1000));
        isInlineTimerRunning.value = true;
        isInlineTimerPaused.value = false;
        startInlineTicker();
        statusMessage.value = response.data.message || 'Timer started for this invoice.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to start inline timer.';
    } finally {
        isInlineTimerLoading.value = false;
    }
}

async function pauseInlineTimer() {
    if (isFinalized.value || isInlineTimerLoading.value) {
        return;
    }

    isInlineTimerLoading.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/timer/pause`);

        isInlineTimerRunning.value = false;
        isInlineTimerPaused.value = true;
        inlineActiveSessionId.value = response.data.session?.id ?? inlineActiveSessionId.value;
        inlineElapsedSeconds.value = Math.max(0, Number(response.data.session?.accumulated_seconds || inlineElapsedSeconds.value));
        stopInlineTicker();
        statusMessage.value = response.data.message || 'Timer paused for this invoice.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to pause inline timer.';
    } finally {
        isInlineTimerLoading.value = false;
    }
}

async function resumeInlineTimer() {
    if (isFinalized.value || isInlineTimerLoading.value) {
        return;
    }

    isInlineTimerLoading.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/timer/resume`);

        isInlineTimerRunning.value = true;
        isInlineTimerPaused.value = false;
        inlineActiveSessionId.value = response.data.session?.id ?? inlineActiveSessionId.value;
        startInlineTicker();
        statusMessage.value = response.data.message || 'Timer resumed for this invoice.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to resume inline timer.';
    } finally {
        isInlineTimerLoading.value = false;
    }
}

async function stopInlineTimer() {
    if (isFinalized.value || isInlineTimerLoading.value) {
        return;
    }

    isInlineTimerLoading.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/timer/stop`);

        applyPayload(response.data);
        isInlineTimerRunning.value = false;
        isInlineTimerPaused.value = false;
        inlineActiveSessionId.value = null;
        inlineElapsedSeconds.value = 0;
        stopInlineTicker();
        statusMessage.value = response.data.message || 'Timer stopped for this invoice.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to stop inline timer.';
    } finally {
        isInlineTimerLoading.value = false;
    }
}

function runInlinePrimaryAction() {
    if (isInlineTimerRunning.value) {
        pauseInlineTimer();
        return;
    }

    if (isInlineTimerPaused.value) {
        resumeInlineTimer();
        return;
    }

    startInlineTimer();
}

async function removeSession(sessionId) {
    if (isFinalized.value || isBusy(sessionId)) {
        return;
    }

    busySessionIds.value.push(sessionId);

    try {
        const response = await axios.delete(`/invoices/${invoice.value.id}/sessions/${sessionId}`);

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Session removed from invoice.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to remove session from invoice.';
    } finally {
        busySessionIds.value = busySessionIds.value.filter((id) => id !== sessionId);
    }
}

async function finalizeInvoice() {
    if (isFinalized.value || isFinalizing.value) {
        return;
    }

    isFinalizing.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/finalize`);

        applyPayload(response.data);
        isInlineTimerRunning.value = false;
        isInlineTimerPaused.value = false;
        inlineActiveSessionId.value = null;
        inlineElapsedSeconds.value = 0;
        stopInlineTicker();
        statusMessage.value = response.data.message || 'Invoice finalized.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to finalize invoice.';
    } finally {
        isFinalizing.value = false;
    }
}

async function markInvoicePaid() {
    if (isMarkingPaid.value || isPaid.value) {
        return;
    }

    isMarkingPaid.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/mark-paid`);

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Invoice marked as paid.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to mark invoice as paid.';
    } finally {
        isMarkingPaid.value = false;
    }
}

async function addExpense() {
    if (isFinalized.value || isSubmittingExpense.value) {
        return;
    }

    if (!expenseAmount.value || Number(expenseAmount.value) <= 0) {
        statusMessage.value = 'Enter an expense amount greater than 0.';
        return;
    }

    isSubmittingExpense.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/expenses`, {
            name: expenseName.value || null,
            description: expenseDescription.value || null,
            amount: Number(expenseAmount.value),
        });

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Expense added to invoice.';
        expenseName.value = '';
        expenseDescription.value = '';
        expenseAmount.value = '';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to add expense.';
    } finally {
        isSubmittingExpense.value = false;
    }
}

async function removeExpense(expenseId) {
    if (isFinalized.value || isExpenseBusy(expenseId)) {
        return;
    }

    busyExpenseIds.value.push(expenseId);

    try {
        const response = await axios.delete(`/invoices/${invoice.value.id}/expenses/${expenseId}`);

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Expense removed from invoice.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to remove expense.';
    } finally {
        busyExpenseIds.value = busyExpenseIds.value.filter((id) => id !== expenseId);
    }
}

onMounted(() => {
    loadInlineTimerStatus();
});

onBeforeUnmount(() => {
    stopInlineTicker();
});
</script>

<template>
    <AppLayout title="Invoice Details">
        <Head :title="`Invoice ${formatInvoiceId(invoice.id)}`" />

        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-5xl space-y-6">
                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice</p>
                            <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatInvoiceId(invoice.id) }}
                            </h1>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                Created: {{ formatDateTime(invoice.created_at) }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Issued: {{ formatDateTime(invoice.issued_at) }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Paid: {{ formatDateTime(invoice.paid_at) }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Client: {{ invoice.client ? invoice.client.name : 'Unassigned' }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Financial Year: {{ invoice.financial_year ? invoice.financial_year.label : '-' }}
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-3">
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                :class="isPaid ? 'bg-green-700 text-white' : (isFinalized ? 'bg-gray-800 text-white' : 'bg-emerald-100 text-emerald-700')"
                            >
                                {{ isPaid ? 'Paid' : (invoice.status === 'finalized' ? 'Finalized' : 'Draft') }}
                            </span>

                            <button
                                type="button"
                                class="rounded-xl px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                                :class="isFinalized ? 'bg-gray-500 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                                :disabled="isFinalized || isFinalizing"
                                @click="finalizeInvoice"
                            >
                                {{ isFinalizing ? 'Finalizing...' : 'Finalize Invoice' }}
                            </button>

                            <button
                                v-if="invoice.status === 'finalized'"
                                type="button"
                                class="rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition disabled:opacity-60"
                                :disabled="isMarkingPaid"
                                @click="markInvoicePaid"
                            >
                                {{ isMarkingPaid ? 'Marking...' : 'Mark As Paid' }}
                            </button>

                            <a
                                v-if="invoice.status === 'finalized'"
                                :href="`/invoices/${invoice.id}/pdf`"
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition"
                            >
                                Download PDF
                            </a>

                            <Link
                                :href="route('invoices.taxSummary', invoice.id)"
                                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition"
                            >
                                Tax Summary
                            </Link>

                            <Link
                                href="/"
                                class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                Back to Timer
                            </Link>
                        </div>
                    </div>

                    <p v-if="invoice.notes" class="mt-4 text-sm text-gray-700 dark:text-gray-200">
                        {{ invoice.notes }}
                    </p>

                    <p v-if="statusMessage" class="mt-4 text-sm text-gray-700 dark:text-gray-200">
                        {{ statusMessage }}
                    </p>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3">
                        <select
                            v-model="selectedFinancialYearId"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        >
                            <option value="" disabled>Select financial year</option>
                            <option v-for="financialYear in financialYears" :key="financialYear.id" :value="String(financialYear.id)">
                                {{ financialYear.label }}
                            </option>
                        </select>

                        <button
                            type="button"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                            :disabled="!selectedFinancialYearId || isSavingFinancialYear"
                            @click="assignFinancialYear"
                        >
                            {{ isSavingFinancialYear ? 'Saving...' : 'Update Financial Year' }}
                        </button>
                    </div>

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Confirmed Sessions</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ summary.sessions_count }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Time</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatDuration(summary.total_duration_seconds) }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">One-Off Expenses</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(summary.total_expenses_amount) }}</p>
                        </div>
                        <div class="rounded-xl border border-emerald-300 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total Billable</p>
                            <p class="mt-2 text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(summary.total_billable_amount) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Sessions Assigned To Invoice</h2>

                    <p v-if="isFinalized" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        This invoice is finalized and cannot be changed.
                    </p>

                    <div class="mt-4 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Inline Timer</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                            {{ formatDuration(inlineElapsedSeconds) }}
                        </p>
                        <p v-if="inlineActiveSessionId" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Session #{{ inlineActiveSessionId }}
                        </p>
                        <button
                            type="button"
                            class="mt-3 rounded-xl px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                            :class="isInlineTimerRunning ? 'bg-amber-600 hover:bg-amber-700' : isInlineTimerPaused ? 'bg-blue-600 hover:bg-blue-700' : 'bg-green-600 hover:bg-green-700'"
                            :disabled="isFinalized || isInlineTimerLoading"
                            @click="runInlinePrimaryAction"
                        >
                            {{ isInlineTimerLoading ? 'Working...' : (isInlineTimerRunning ? 'Pause Inline Timer' : isInlineTimerPaused ? 'Resume Inline Timer' : 'Start Inline Timer') }}
                        </button>

                        <button
                            v-if="isInlineTimerRunning || isInlineTimerPaused"
                            type="button"
                            class="mt-3 ml-3 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition disabled:opacity-60"
                            :disabled="isFinalized || isInlineTimerLoading"
                            @click="stopInlineTimer"
                        >
                            {{ isInlineTimerLoading ? 'Working...' : 'Stop Inline Timer' }}
                        </button>

                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                            {{ isInlineTimerRunning ? 'Recording in progress' : isInlineTimerPaused ? 'Paused' : 'Not recording' }}
                        </p>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <input
                            v-model="manualDurationMinutes"
                            type="number"
                            min="1"
                            step="1"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            placeholder="Duration (minutes)"
                            :disabled="isFinalized || isSubmittingManualSession"
                        />
                        <input
                            v-model="manualStartedAt"
                            type="date"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            :disabled="isFinalized || isSubmittingManualSession"
                        />
                        <button
                            type="button"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                            :disabled="isFinalized || isSubmittingManualSession"
                            @click="createManualSession"
                        >
                            {{ isSubmittingManualSession ? 'Adding Manual Session...' : 'Add Manual Session' }}
                        </button>
                    </div>

                    <p v-if="assignedSessions.length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        No sessions assigned yet.
                    </p>

                    <div v-else class="mt-4 space-y-3">
                        <div
                            v-for="session in sortedAssignedSessions"
                            :key="session.id"
                            class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatDateTime(session.started_at || session.created_at) }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Started: {{ formatDateTime(session.started_at) }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">Stopped: {{ formatDateTime(session.stopped_at) }}</p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ formatDuration(session.duration_seconds) }}</p>
                                    <button
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-white transition disabled:opacity-60"
                                        :class="isFinalized ? 'bg-gray-500 cursor-not-allowed' : 'bg-red-600 hover:bg-red-700'"
                                        :disabled="isFinalized || isBusy(session.id)"
                                        @click="removeSession(session.id)"
                                    >
                                        {{ isBusy(session.id) ? 'Removing...' : 'Remove' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">One-Off Expenses</h2>

                    <p v-if="isFinalized" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        This invoice is finalized and cannot be changed.
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input
                            v-model="expenseName"
                            type="text"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            placeholder="Expense name (optional)"
                            :disabled="isFinalized || isSubmittingExpense"
                        />

                        <input
                            v-model="expenseAmount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            placeholder="Amount (USD)"
                            :disabled="isFinalized || isSubmittingExpense"
                        />
                    </div>

                    <textarea
                        v-model="expenseDescription"
                        rows="3"
                        class="mt-3 w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        placeholder="Description (optional)"
                        :disabled="isFinalized || isSubmittingExpense"
                    />

                    <button
                        type="button"
                        class="mt-3 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition disabled:opacity-60"
                        :disabled="isFinalized || isSubmittingExpense"
                        @click="addExpense"
                    >
                        {{ isSubmittingExpense ? 'Adding Expense...' : 'Add Expense' }}
                    </button>

                    <p v-if="expenses.length === 0" class="mt-5 text-sm text-gray-600 dark:text-gray-300">
                        No expenses added yet.
                    </p>

                    <div v-else class="mt-5 space-y-3">
                        <div
                            v-for="expense in expenses"
                            :key="expense.id"
                            class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ expense.name || 'One-off expense' }}
                                    </p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">
                                        {{ expense.description || 'No description provided.' }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                        {{ formatCurrency(expense.amount) }}
                                    </p>
                                    <button
                                        type="button"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-white transition disabled:opacity-60"
                                        :class="isFinalized ? 'bg-gray-500 cursor-not-allowed' : 'bg-red-600 hover:bg-red-700'"
                                        :disabled="isFinalized || isExpenseBusy(expense.id)"
                                        @click="removeExpense(expense.id)"
                                    >
                                        {{ isExpenseBusy(expense.id) ? 'Removing...' : 'Remove' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
