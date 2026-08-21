<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const isRunning = ref(false);
const isPaused = ref(false);
const elapsedSeconds = ref(0);
const activeSessionId = ref(null);
const statusMessage = ref('');
const isLoading = ref(false);
const isCreatingInvoice = ref(false);
const isSubmittingSession = ref(false);
const historySessions = ref([]);
const currentInvoice = ref(null);
const clients = ref([]);
const selectedClientId = ref('');
const selectedProjectId = ref('');
const invoiceNotes = ref('');
const pendingSessionId = ref(null);
const isCreatingInvoiceFlowOpen = ref(false);

let intervalId = null;
let runningBaselineSeconds = 0;
let runningStartedAtMs = null;

const isInvoiceFinalized = computed(() => currentInvoice.value?.status === 'finalized');
const shouldShowInvoiceSetup = computed(() => !currentInvoice.value || isCreatingInvoiceFlowOpen.value);
const currentInvoiceProjects = computed(() => {
    const invoiceClientId = currentInvoice.value?.client?.id;

    if (!invoiceClientId) {
        return [];
    }

    const client = (clients.value || []).find((item) => Number(item.id) === Number(invoiceClientId));

    return (client?.projects || []).filter((project) => project?.is_active !== false);
});

const formattedElapsed = computed(() => {
    const hours = Math.floor(elapsedSeconds.value / 3600);
    const minutes = Math.floor((elapsedSeconds.value % 3600) / 60);
    const seconds = elapsedSeconds.value % 60;

    return `${hours.toString().padStart(2, '0')}:${minutes
        .toString()
        .padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
});

function startLocalTicker() {
    if (intervalId) {
        return;
    }

    syncElapsedFromClock();
    intervalId = setInterval(() => {
        syncElapsedFromClock();
    }, 250);
}

function stopLocalTicker() {
    if (!intervalId) {
        runningStartedAtMs = null;
        return;
    }

    clearInterval(intervalId);
    intervalId = null;
    runningStartedAtMs = null;
}

function setRunningBaseline(baseSeconds) {
    runningBaselineSeconds = Math.max(0, Number(baseSeconds || 0));
    runningStartedAtMs = Date.now();
    elapsedSeconds.value = runningBaselineSeconds;
}

function syncElapsedFromClock() {
    if (runningStartedAtMs === null) {
        return;
    }

    const elapsedSinceBaseline = Math.max(0, Math.floor((Date.now() - runningStartedAtMs) / 1000));
    elapsedSeconds.value = runningBaselineSeconds + elapsedSinceBaseline;
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

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatInvoiceId(invoiceId) {
    return `INV${invoiceId}`;
}

function getSessionDuration(session) {
    if (session.id === activeSessionId.value && (isRunning.value || isPaused.value)) {
        return formattedElapsed.value;
    }

    if (session.duration_seconds !== null && session.duration_seconds !== undefined) {
        return formatDuration(session.duration_seconds);
    }

    const startedAt = new Date(session.started_at);
    const stoppedAt = session.stopped_at ? new Date(session.stopped_at) : new Date();
    const seconds = Math.floor((stoppedAt - startedAt) / 1000);

    return formatDuration(seconds);
}

async function loadHistory() {
    if (!currentInvoice.value) {
        historySessions.value = [];
        return;
    }

    try {
        const response = await axios.get('/timer/history', {
            params: {
                limit: 10,
                confirmed_only: 1,
                invoice_id: currentInvoice.value.id,
            },
        });

        historySessions.value = response.data.sessions || [];
    } catch (error) {
        historySessions.value = [];
        statusMessage.value = error?.response?.data?.message || 'Failed to load session history.';
    }
}

async function loadLatestInvoice() {
    try {
        const response = await axios.get('/invoices/latest');

        currentInvoice.value = response.data.invoice;
    } catch {
        currentInvoice.value = null;
    }
}

async function loadClients() {
    try {
        const response = await axios.get('/clients/list');

        clients.value = response.data.clients || [];
    } catch {
        clients.value = [];
    }
}

async function createInvoice() {
    isCreatingInvoice.value = true;

    try {
        const response = await axios.post('/invoices/create', {
            client_id: selectedClientId.value ? Number(selectedClientId.value) : null,
            notes: invoiceNotes.value || null,
        });

        currentInvoice.value = response.data.invoice;
        statusMessage.value = `Invoice ${formatInvoiceId(response.data.invoice.id)} created.`;
        selectedClientId.value = '';
        invoiceNotes.value = '';
        isCreatingInvoiceFlowOpen.value = false;
        await loadHistory();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to create invoice.';
    } finally {
        isCreatingInvoice.value = false;
    }
}

async function loadStatus() {
    try {
        const response = await axios.get('/timer/status');

        if (response.data.active && response.data.session) {
            const session = response.data.session;

            activeSessionId.value = session.id;
            isRunning.value = Boolean(response.data.running);
            isPaused.value = Boolean(response.data.paused);

            if (isRunning.value) {
                setRunningBaseline(response.data.elapsed_seconds);
                startLocalTicker();
                statusMessage.value = 'Timer is running.';
            } else {
                elapsedSeconds.value = Math.max(0, Number(response.data.elapsed_seconds || 0));
                stopLocalTicker();
                statusMessage.value = 'Timer is paused.';
            }
            return;
        }

        isRunning.value = false;
        isPaused.value = false;
        activeSessionId.value = null;
        elapsedSeconds.value = 0;
        stopLocalTicker();
    } catch {
        statusMessage.value = 'Could not load timer status.';
    }
}

async function startTimer() {
    if (!currentInvoice.value) {
        statusMessage.value = 'Create an invoice before starting the timer.';
        return;
    }

    if (isInvoiceFinalized.value) {
        statusMessage.value = 'This invoice is finalized. Create a new invoice before recording more time.';
        return;
    }

    if (!selectedProjectId.value) {
        statusMessage.value = 'Select a project before starting a timer session.';
        return;
    }

    isLoading.value = true;

    try {
        const response = await axios.post('/timer/start', {
            project_id: Number(selectedProjectId.value),
        });

        statusMessage.value = response.data.message;
        pendingSessionId.value = null;
        await loadStatus();
        loadHistory();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to start timer.';
    } finally {
        isLoading.value = false;
    }
}

async function pauseTimer() {
    isLoading.value = true;

    try {
        const response = await axios.post('/timer/pause');

        isRunning.value = false;
        isPaused.value = true;
        stopLocalTicker();
        elapsedSeconds.value = Math.max(0, Number(response.data.session?.accumulated_seconds || elapsedSeconds.value));
        activeSessionId.value = response.data.session?.id ?? activeSessionId.value;
        statusMessage.value = response.data.message;
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to pause timer.';
    } finally {
        isLoading.value = false;
    }
}

async function resumeTimer() {
    isLoading.value = true;

    try {
        const response = await axios.post('/timer/resume');

        statusMessage.value = response.data.message;
        await loadStatus();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to resume timer.';
    } finally {
        isLoading.value = false;
    }
}

async function stopTimer() {
    isLoading.value = true;

    try {
        const response = await axios.post('/timer/stop');

        isRunning.value = false;
        isPaused.value = false;
        stopLocalTicker();
        elapsedSeconds.value = 0;
        statusMessage.value = `${response.data.message} Duration saved to database.`;
        activeSessionId.value = null;
        pendingSessionId.value = response.data.session?.id ?? null;
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to stop timer.';
    } finally {
        isLoading.value = false;
    }
}

async function submitSessionToInvoice() {
    if (!currentInvoice.value || !pendingSessionId.value) {
        return;
    }

    if (isInvoiceFinalized.value) {
        statusMessage.value = 'Finalized invoices cannot receive new timer sessions.';
        return;
    }

    isSubmittingSession.value = true;

    try {
        const response = await axios.post('/timer/submit-to-invoice', {
            session_id: pendingSessionId.value,
            invoice_id: currentInvoice.value.id,
        });

        statusMessage.value = response.data.message;
        pendingSessionId.value = null;
        await loadHistory();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to submit session to invoice.';
    } finally {
        isSubmittingSession.value = false;
    }
}

function runPrimaryTimerAction() {
    if (isRunning.value) {
        pauseTimer();
        return;
    }

    if (isPaused.value) {
        resumeTimer();
        return;
    }

    startTimer();
}

function openInvoiceCreateFlow() {
    isCreatingInvoiceFlowOpen.value = true;
}

function cancelInvoiceCreateFlow() {
    if (!currentInvoice.value) {
        return;
    }

    isCreatingInvoiceFlowOpen.value = false;
    selectedClientId.value = '';
    invoiceNotes.value = '';
}

function ensureSelectedProject() {
    const availableProjects = currentInvoiceProjects.value || [];

    if (!availableProjects.length) {
        selectedProjectId.value = '';
        return;
    }

    const exists = availableProjects.some((project) => String(project.id) === selectedProjectId.value);

    if (!exists) {
        selectedProjectId.value = String(availableProjects[0].id);
    }
}

watch(currentInvoiceProjects, () => {
    ensureSelectedProject();
});

onMounted(() => {
    loadClients();
    loadStatus();
    loadLatestInvoice().then(loadHistory);
});

onBeforeUnmount(() => {
    stopLocalTicker();
});
</script>

<template>
    <div class="w-full max-w-xl mx-auto">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-lg p-6 sm:p-8 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice</h3>

            <div v-if="currentInvoice" class="mt-3 rounded-xl border border-green-200 dark:border-green-800 px-4 py-3">
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    Active: {{ formatInvoiceId(currentInvoice.id) }}
                </p>
                <p v-if="currentInvoice.client" class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                    Client: {{ currentInvoice.client.name }}
                </p>
                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                    Status: {{ currentInvoice.status }}
                </p>
                <p v-if="isInvoiceFinalized" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                    This invoice is locked. Create a new invoice to continue tracking billable time.
                </p>
                <a
                    :href="`/invoices/${currentInvoice.id}`"
                    class="inline-block mt-2 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                >
                    View invoice details
                </a>
                <a
                    href="/invoices"
                    class="inline-block mt-2 ml-3 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                    Browse invoices
                </a>
            </div>

            <div v-if="!currentInvoice" class="mt-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Create an invoice first. Timer controls unlock after invoice creation.
                </p>
            </div>

            <div v-if="currentInvoice" class="mt-4">
                <button
                    type="button"
                    class="px-5 py-2.5 rounded-xl text-white font-semibold bg-blue-600 hover:bg-blue-700 transition disabled:opacity-60"
                    :disabled="isCreatingInvoice"
                    @click="openInvoiceCreateFlow"
                >
                    Create New Invoice
                </button>
            </div>

            <div v-if="shouldShowInvoiceSetup" class="mt-4 space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">New Invoice Setup</h4>
                <p class="text-xs text-gray-600 dark:text-gray-300">
                    Select a client for the new invoice, then create it.
                </p>

                <select
                    v-model="selectedClientId"
                    class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                >
                    <option value="">No client selected</option>
                    <option v-for="client in clients" :key="client.id" :value="String(client.id)">
                        {{ client.name }}
                    </option>
                </select>

                <a href="/clients/create" class="inline-block text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                    Create a new client
                </a>

                <textarea
                    v-model="invoiceNotes"
                    rows="3"
                    class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                    placeholder="Optional invoice notes"
                />

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="px-5 py-2.5 rounded-xl text-white font-semibold bg-blue-600 hover:bg-blue-700 transition disabled:opacity-60"
                        :disabled="isCreatingInvoice"
                        @click="createInvoice"
                    >
                        {{ isCreatingInvoice ? 'Creating...' : currentInvoice ? 'Create New Invoice' : 'Create Invoice' }}
                    </button>

                    <button
                        v-if="currentInvoice"
                        type="button"
                        class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-800 hover:bg-gray-300 dark:hover:bg-gray-700 transition"
                        @click="cancelInvoiceCreateFlow"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-lg p-6 sm:p-8">
        <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">Timer</p>
        <h2 class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-white mt-3">
            {{ formattedElapsed }}
        </h2>

        <div class="mt-4">
            <label class="text-xs font-medium text-gray-700 dark:text-gray-200">Project for new timer sessions</label>
            <select
                v-model="selectedProjectId"
                class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                :disabled="!currentInvoice || isRunning || isPaused"
            >
                <option value="">Select project</option>
                <option
                    v-for="project in currentInvoiceProjects"
                    :key="project.id"
                    :value="String(project.id)"
                >
                    {{ project.name }}
                </option>
            </select>
            <p v-if="currentInvoice && currentInvoiceProjects.length === 0" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                This invoice client has no active projects. Create one before starting a timer session.
            </p>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button
                type="button"
                class="px-6 py-3 rounded-xl text-white font-semibold transition disabled:opacity-60"
                :class="isRunning ? 'bg-amber-600 hover:bg-amber-700' : isPaused ? 'bg-blue-600 hover:bg-blue-700' : 'bg-green-600 hover:bg-green-700'"
                :disabled="isLoading || !currentInvoice"
                @click="runPrimaryTimerAction"
            >
                {{ isRunning ? 'Pause Timer' : isPaused ? 'Resume Timer' : 'Start Timer' }}
            </button>

            <button
                v-if="isRunning || isPaused"
                type="button"
                class="px-5 py-3 rounded-xl text-white font-semibold bg-red-600 hover:bg-red-700 transition disabled:opacity-60"
                :disabled="isLoading || !currentInvoice"
                @click="stopTimer"
            >
                Stop Timer
            </button>

            <span class="text-sm text-gray-600 dark:text-gray-300">
                {{ isRunning ? 'Recording in progress' : isPaused ? 'Paused' : 'Not recording' }}
            </span>
        </div>

        <p v-if="statusMessage" class="mt-4 text-sm text-gray-700 dark:text-gray-300">
            {{ statusMessage }}
        </p>
        <p v-if="activeSessionId" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Session #{{ activeSessionId }}
        </p>

        <div v-if="pendingSessionId && currentInvoice" class="mt-5">
            <button
                type="button"
                class="px-5 py-2.5 rounded-xl text-white font-semibold bg-blue-600 hover:bg-blue-700 transition disabled:opacity-60"
                :disabled="isSubmittingSession || isInvoiceFinalized"
                @click="submitSessionToInvoice"
            >
                {{ isSubmittingSession ? 'Submitting...' : 'Submit Session To Invoice' }}
            </button>
            <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                Session #{{ pendingSessionId }} is ready to be confirmed on invoice {{ formatInvoiceId(currentInvoice.id) }}.
            </p>
        </div>
        </div>

        <div class="mt-6 bg-white dark:bg-gray-900 rounded-2xl shadow-lg p-6 sm:p-8">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirmed Sessions For Invoice</h3>

            <p v-if="historySessions.length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                No confirmed sessions yet for this invoice.
            </p>

            <div v-else class="mt-4 space-y-3">
                <div
                    v-for="session in historySessions"
                    :key="session.id"
                    class="rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3"
                >
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            Session #{{ session.id }}
                        </p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            {{ getSessionDuration(session) }}
                        </p>
                    </div>

                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                        Started: {{ formatDateTime(session.started_at) }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        Stopped: {{ session.stopped_at ? formatDateTime(session.stopped_at) : 'Running' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
