<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const isRunning = ref(false);
const isPaused = ref(false);
const elapsedSeconds = ref(0);
const activeSessionId = ref(null);
const statusMessage = ref('');
const isLoading = ref(false);
const isSubmittingSession = ref(false);
const historySessions = ref([]);
const clients = ref([]);
const selectedProjectId = ref('');
const selectedTaskId = ref('');
const pendingSessionId = ref(null);
const lastConfirmedInvoiceId = ref(null);

let intervalId = null;
let runningBaselineSeconds = 0;
let runningStartedAtMs = null;

const availableProjects = computed(() => {
    return (clients.value || []).flatMap((client) => {
        const projects = Array.isArray(client?.projects) ? client.projects : [];

        return projects
            .filter((project) => project?.is_active !== false)
            .map((project) => ({
                id: project.id,
                name: project.name,
                client_id: client.id,
                client_name: client.name,
            }));
    });
});

const availableProjectsByClient = computed(() => {
    const groups = new Map();

    availableProjects.value.forEach((project) => {
        const clientName = String(project?.client_name || 'Unassigned client');

        if (!groups.has(clientName)) {
            groups.set(clientName, []);
        }

        groups.get(clientName).push(project);
    });

    return Array.from(groups.entries())
        .map(([clientName, projects]) => ({
            clientName,
            projects: [...projects].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''))),
        }))
        .sort((a, b) => a.clientName.localeCompare(b.clientName));
});

const availableTasks = computed(() => {
    if (!selectedProjectId.value) {
        return [];
    }

    const projectId = Number(selectedProjectId.value);

    return (clients.value || []).flatMap((client) => {
        const tasks = Array.isArray(client?.tasks) ? client.tasks : [];

        return tasks
            .filter((task) => Number(task?.project_id) === projectId)
            .filter((task) => task?.is_active !== false)
            .map((task) => ({
                id: task.id,
                name: task.name,
                description: task.description,
                is_default: task.is_default,
            }));
    });
});

const selectedProjectSummary = computed(() => {
    return availableProjects.value.find((project) => String(project.id) === selectedProjectId.value) || null;
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
    try {
        const response = await axios.get('/timer/history', {
            params: {
                limit: 10,
                confirmed_only: 1,
            },
        });

        historySessions.value = response.data.sessions || [];
    } catch (error) {
        historySessions.value = [];
        statusMessage.value = error?.response?.data?.message || 'Failed to load session history.';
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
    if (!selectedProjectId.value) {
        statusMessage.value = 'Select a project before starting a timer session.';
        return;
    }

    if (!selectedTaskId.value) {
        statusMessage.value = 'Select a task before starting a timer session.';
        return;
    }

    isLoading.value = true;

    try {
        const response = await axios.post('/timer/start', {
            project_id: Number(selectedProjectId.value),
            task_id: Number(selectedTaskId.value),
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
        lastConfirmedInvoiceId.value = null;
        pendingSessionId.value = response.data.session?.id ?? null;
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to stop timer.';
    } finally {
        isLoading.value = false;
    }
}

async function submitSessionToInvoice() {
    if (!pendingSessionId.value) {
        return;
    }

    isSubmittingSession.value = true;

    try {
        const response = await axios.post('/timer/submit-to-invoice', {
            session_id: pendingSessionId.value,
        });

        statusMessage.value = response.data.message;
        lastConfirmedInvoiceId.value = response.data?.invoice?.id || null;
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

function ensureSelectedProject() {
    if (!availableProjects.value.length) {
        selectedProjectId.value = '';
        return;
    }

    const exists = availableProjects.value.some((project) => String(project.id) === selectedProjectId.value);

    if (!exists) {
        selectedProjectId.value = String(availableProjects.value[0].id);
    }
}

function ensureSelectedTask() {
    if (!availableTasks.value.length) {
        selectedTaskId.value = '';
        return;
    }

    const exists = availableTasks.value.some((task) => String(task.id) === selectedTaskId.value);

    if (!exists) {
        const defaultTask = availableTasks.value.find((task) => task.is_default);
        selectedTaskId.value = String((defaultTask || availableTasks.value[0]).id);
    }
}

watch(availableProjects, () => {
    ensureSelectedProject();
});

watch(availableTasks, () => {
    ensureSelectedTask();
});

watch(selectedProjectId, () => {
    ensureSelectedTask();
});

onMounted(() => {
    loadClients();
    loadStatus();
    loadHistory();
});

onBeforeUnmount(() => {
    stopLocalTicker();
});
</script>

<template>
    <div class="w-full max-w-xl mx-auto">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-lg p-6 sm:p-8 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Session Assignment</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                Choose a project and task before starting. When you confirm a stopped session, it will automatically attach to the latest draft invoice for that client, or create a new draft if needed.
            </p>
            <div class="mt-4 space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-4">
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-200">Project</label>
                    <select
                        v-model="selectedProjectId"
                        class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        :disabled="isRunning || isPaused"
                    >
                        <option value="">Select project</option>
                        <optgroup
                            v-for="group in availableProjectsByClient"
                            :key="group.clientName"
                            :label="group.clientName"
                        >
                            <option
                                v-for="project in group.projects"
                                :key="project.id"
                                :value="String(project.id)"
                            >
                                {{ project.name }}
                            </option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-200">Task</label>
                    <select
                        v-model="selectedTaskId"
                        class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        :disabled="!selectedProjectId || isRunning || isPaused"
                    >
                        <option value="">Select task</option>
                        <option
                            v-for="task in availableTasks"
                            :key="task.id"
                            :value="String(task.id)"
                        >
                            {{ task.name }}{{ task.is_default ? ' (Default)' : '' }}
                        </option>
                    </select>
                </div>
                <p v-if="selectedProjectSummary" class="text-xs text-gray-600 dark:text-gray-300">
                    Selected client: {{ selectedProjectSummary.client_name }}
                </p>
                <p v-if="availableProjects.length === 0" class="text-xs text-amber-700 dark:text-amber-300">
                    No active projects found. Create a client project and task before starting the timer.
                </p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-lg p-6 sm:p-8">
        <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">Timer</p>
        <h2 class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-white mt-3">
            {{ formattedElapsed }}
        </h2>

        <div class="mt-6 flex items-center gap-3">
            <button
                type="button"
                class="px-6 py-3 rounded-xl text-white font-semibold transition disabled:opacity-60"
                :class="isRunning ? 'bg-amber-600 hover:bg-amber-700' : isPaused ? 'bg-blue-600 hover:bg-blue-700' : 'bg-green-600 hover:bg-green-700'"
                :disabled="isLoading || (!isRunning && !isPaused && (!selectedProjectId || !selectedTaskId))"
                @click="runPrimaryTimerAction"
            >
                {{ isRunning ? 'Pause Timer' : isPaused ? 'Resume Timer' : 'Start Timer' }}
            </button>

            <button
                v-if="isRunning || isPaused"
                type="button"
                class="px-5 py-3 rounded-xl text-white font-semibold bg-red-600 hover:bg-red-700 transition disabled:opacity-60"
                :disabled="isLoading"
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

        <div v-if="pendingSessionId" class="mt-5">
            <button
                type="button"
                class="px-5 py-2.5 rounded-xl text-white font-semibold bg-blue-600 hover:bg-blue-700 transition disabled:opacity-60"
                :disabled="isSubmittingSession"
                @click="submitSessionToInvoice"
            >
                {{ isSubmittingSession ? 'Confirming...' : 'Confirm Session' }}
            </button>
            <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                Session #{{ pendingSessionId }} is ready for confirmation.
                <span v-if="lastConfirmedInvoiceId">Last confirmed invoice: {{ formatInvoiceId(lastConfirmedInvoiceId) }}.</span>
            </p>
        </div>
        </div>

        <div class="mt-6 bg-white dark:bg-gray-900 rounded-2xl shadow-lg p-6 sm:p-8">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirmed Sessions</h3>

            <p v-if="historySessions.length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                No confirmed sessions yet.
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
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                        Invoice: {{ session.invoice_id ? formatInvoiceId(session.invoice_id) : 'Unassigned' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
