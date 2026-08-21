<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
    clientTasks: {
        type: Array,
        default: () => [],
    },
    availableSessions: {
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
            subtotal_amount: 0,
            discount_type: null,
            discount_value: 0,
            discount_amount: 0,
            total_billable_amount: 0,
        }),
    },
});

const invoice = ref(props.invoice);
const assignedSessions = ref(props.assignedSessions);
const clientTasks = ref(props.clientTasks || []);
const availableSessions = ref(props.availableSessions);
const expenses = ref(props.expenses);
const summary = ref(props.summary);
const statusMessage = ref('');
const isFinalizing = ref(false);
const isMarkingPaid = ref(false);
const isSendingInvoiceEmail = ref(false);
const busySessionIds = ref([]);
const busyExpenseIds = ref([]);
const isSubmittingExpense = ref(false);
const isSubmittingManualSession = ref(false);
const isInlineTimerLoading = ref(false);
const isDeletingInvoice = ref(false);
const isSavingDiscount = ref(false);
const savingSessionDateIds = ref([]);
const savingSessionDurationIds = ref([]);
const savingSessionTaskIds = ref([]);
const savingSessionDetailsIds = ref([]);
const editingSessionDurationId = ref(null);
const editingSessionDetailsId = ref(null);
const sessionDateDrafts = ref(
    Object.fromEntries(
        (props.assignedSessions || []).map((session) => {
            const value = session?.started_at || session?.created_at;

            if (!value) {
                return [session.id, ''];
            }

            const date = new Date(value);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return [session.id, `${year}-${month}-${day}`];
        })
    )
);
const sessionDurationDrafts = ref(
    Object.fromEntries(
        (props.assignedSessions || []).map((session) => [session.id, getSessionDurationHms(session)])
    )
);
const sessionTaskDrafts = ref(
    Object.fromEntries(
        (props.assignedSessions || []).map((session) => [session.id, session?.task_id ? String(session.task_id) : ''])
    )
);
const sessionProjectDrafts = ref(
    Object.fromEntries(
        (props.assignedSessions || []).map((session) => [session.id, session?.task?.project_id ? String(session.task.project_id) : ''])
    )
);
const isInlineTimerRunning = ref(false);
const isInlineTimerPaused = ref(false);
const inlineElapsedSeconds = ref(0);
const inlineActiveSessionId = ref(null);
const expenseName = ref('');
const expenseDescription = ref('');
const expenseAmount = ref('');
const manualDurationMinutes = ref('');
const selectedInlineProjectId = ref('');
const selectedInlineTaskId = ref('');
const selectedManualProjectId = ref('');
const selectedManualTaskId = ref('');
const discountType = ref(invoice.value?.discount_type || '');
const discountValue = ref(Number(invoice.value?.discount_value ?? 0));
const expandedProjectSections = ref({});

function formatTaskOption(task) {
    if (!task) {
        return 'Unknown task';
    }

    if (task.project?.name) {
        return `${task.name} (${task.project.name})`;
    }

    return task.name;
}

const clientProjects = computed(() => {
    const projectMap = new Map();

    (clientTasks.value || []).forEach((task) => {
        if (!task?.project?.id) {
            return;
        }

        if (!projectMap.has(task.project.id)) {
            projectMap.set(task.project.id, {
                id: task.project.id,
                name: task.project.name,
            });
        }
    });

    return Array.from(projectMap.values()).sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
});

function tasksForProject(projectId) {
    if (!projectId) {
        return [];
    }

    return (clientTasks.value || []).filter(
        (task) => String(task.project_id) === String(projectId) && task?.is_active !== false
    );
}

function getDefaultTaskIdForProject(projectId) {
    const scopedTasks = tasksForProject(projectId);
    const defaultTask = scopedTasks.find((task) => task?.is_default === true);

    if (defaultTask?.id) {
        return String(defaultTask.id);
    }

    return scopedTasks[0]?.id ? String(scopedTasks[0].id) : '';
}

function getDefaultClientTaskId() {
    const defaultTask = (clientTasks.value || []).find((task) => task?.is_default === true && task?.is_active !== false);

    if (defaultTask?.id) {
        return String(defaultTask.id);
    }

    const firstActiveTask = (clientTasks.value || []).find((task) => task?.is_active !== false);

    return firstActiveTask?.id ? String(firstActiveTask.id) : '';
}

function ensureInlineTaskSelection() {
    if (!selectedInlineProjectId.value) {
        selectedInlineTaskId.value = '';
        return;
    }

    if (selectedInlineTaskId.value) {
        const exists = tasksForProject(selectedInlineProjectId.value).some((task) => String(task.id) === selectedInlineTaskId.value);

        if (exists) {
            return;
        }
    }

    selectedInlineTaskId.value = getDefaultTaskIdForProject(selectedInlineProjectId.value);
}

function ensureManualTaskSelection() {
    if (!selectedManualProjectId.value) {
        selectedManualTaskId.value = '';
        return;
    }

    if (selectedManualTaskId.value) {
        const exists = tasksForProject(selectedManualProjectId.value).some((task) => String(task.id) === selectedManualTaskId.value);

        if (exists) {
            return;
        }
    }

    selectedManualTaskId.value = getDefaultTaskIdForProject(selectedManualProjectId.value);
}

function ensureInlineProjectSelection() {
    const projects = clientProjects.value || [];

    if (!projects.length) {
        selectedInlineProjectId.value = '';
        selectedInlineTaskId.value = '';
        return;
    }

    const exists = projects.some((project) => String(project.id) === selectedInlineProjectId.value);

    if (!exists) {
        selectedInlineProjectId.value = String(projects[0].id);
    }

    ensureInlineTaskSelection();
}

function ensureManualProjectSelection() {
    const projects = clientProjects.value || [];

    if (!projects.length) {
        selectedManualProjectId.value = '';
        selectedManualTaskId.value = '';
        return;
    }

    const exists = projects.some((project) => String(project.id) === selectedManualProjectId.value);

    if (!exists) {
        selectedManualProjectId.value = String(projects[0].id);
    }

    ensureManualTaskSelection();
}

function getDefaultManualStartedAt() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const manualStartedAt = ref(getDefaultManualStartedAt());
let inlineIntervalId = null;
let inlineRunningBaselineSeconds = 0;
let inlineRunningStartedAtMs = null;

const isFinalized = computed(() => invoice.value?.is_finalized === true);
const isPaid = computed(() => invoice.value?.status === 'paid');
const hasActiveClientTasks = computed(() => {
    return (clientTasks.value || []).some((task) => task?.is_active !== false);
});
const sortedAssignedSessions = computed(() => {
    return [...assignedSessions.value].sort((a, b) => {
        const aDate = new Date(a?.started_at || a?.created_at || 0).getTime();
        const bDate = new Date(b?.started_at || b?.created_at || 0).getTime();

        return bDate - aDate;
    });
});
const assignedSessionsByProject = computed(() => {
    const groups = new Map();

    sortedAssignedSessions.value.forEach((session) => {
        const projectId = session?.task?.project?.id ?? session?.task?.project_id ?? null;
        const projectName = session?.task?.project?.name || 'Unassigned Project';
        const key = projectId ? `project-${projectId}` : 'project-unassigned';

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                projectId,
                projectName,
                sessions: [],
            });
        }

        groups.get(key).sessions.push(session);
    });

    return Array.from(groups.values());
});

function isProjectSectionExpanded(projectKey) {
    return expandedProjectSections.value[projectKey] === true;
}

function toggleProjectSection(projectKey) {
    expandedProjectSections.value = {
        ...expandedProjectSections.value,
        [projectKey]: !isProjectSectionExpanded(projectKey),
    };
}

function visibleSessionsForProject(section) {
    if (isProjectSectionExpanded(section.key)) {
        return section.sessions;
    }

    return section.sessions.slice(0, 2);
}

function hasMoreSessionsForProject(section) {
    return section.sessions.length > 2;
}

function hiddenSessionsCountForProject(section) {
    return Math.max(0, section.sessions.length - 2);
}

function totalDurationSecondsForProject(section) {
    return (section.sessions || []).reduce((total, session) => {
        if (inlineActiveSessionId.value === session?.id) {
            return total + Math.max(0, Number(inlineElapsedSeconds.value || 0));
        }

        return total + Math.max(0, Number(session?.duration_seconds || 0));
    }, 0);
}

function formatInvoiceId(invoiceId) {
    return `INV${invoiceId}`;
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatSessionHeaderDate(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString(undefined, {
        weekday: 'long',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
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

function displaySessionDuration(session) {
    if (inlineActiveSessionId.value === session?.id) {
        return formatDuration(inlineElapsedSeconds.value);
    }

    return formatDuration(session?.duration_seconds || 0);
}

function startInlineTicker() {
    if (inlineIntervalId) {
        return;
    }

    syncInlineElapsedFromClock();
    inlineIntervalId = setInterval(() => {
        syncInlineElapsedFromClock();
    }, 250);
}

function stopInlineTicker() {
    if (!inlineIntervalId) {
        inlineRunningStartedAtMs = null;
        return;
    }

    clearInterval(inlineIntervalId);
    inlineIntervalId = null;
    inlineRunningStartedAtMs = null;
}

function setInlineRunningBaseline(baseSeconds) {
    inlineRunningBaselineSeconds = Math.max(0, Number(baseSeconds || 0));
    inlineRunningStartedAtMs = Date.now();
    inlineElapsedSeconds.value = inlineRunningBaselineSeconds;
}

function syncInlineElapsedFromClock() {
    if (inlineRunningStartedAtMs === null) {
        return;
    }

    const elapsedSinceBaseline = Math.max(0, Math.floor((Date.now() - inlineRunningStartedAtMs) / 1000));
    inlineElapsedSeconds.value = inlineRunningBaselineSeconds + elapsedSinceBaseline;
}

function isBusy(sessionId) {
    return busySessionIds.value.includes(sessionId);
}

function applyPayload(data) {
    invoice.value = data.invoice;
    assignedSessions.value = data.assigned_sessions || [];
    sessionDateDrafts.value = Object.fromEntries(
        assignedSessions.value.map((session) => [session.id, getSessionDate(session)])
    );
    sessionDurationDrafts.value = Object.fromEntries(
        assignedSessions.value.map((session) => [session.id, getSessionDurationHms(session)])
    );
    sessionTaskDrafts.value = Object.fromEntries(
        assignedSessions.value.map((session) => [session.id, session?.task_id ? String(session.task_id) : ''])
    );
    sessionProjectDrafts.value = Object.fromEntries(
        assignedSessions.value.map((session) => [session.id, session?.task?.project_id ? String(session.task.project_id) : ''])
    );
    if (Array.isArray(data.client_tasks)) {
        clientTasks.value = data.client_tasks;
    }
    ensureInlineProjectSelection();
    ensureManualProjectSelection();
    ensureInlineTaskSelection();
    ensureManualTaskSelection();
    availableSessions.value = data.available_sessions || [];
    expenses.value = data.expenses || [];
    summary.value = data.summary || {
        sessions_count: 0,
        total_duration_seconds: 0,
        total_expenses_amount: 0,
        subtotal_amount: 0,
        discount_type: null,
        discount_value: 0,
        discount_amount: 0,
        total_billable_amount: 0,
    };
    discountType.value = invoice.value?.discount_type || '';
    discountValue.value = Number(invoice.value?.discount_value ?? 0);

    if (editingSessionDurationId.value !== null) {
        const sessionStillPresent = assignedSessions.value.some(
            (session) => session.id === editingSessionDurationId.value
        );

        if (!sessionStillPresent) {
            editingSessionDurationId.value = null;
        }
    }

    if (editingSessionDetailsId.value !== null) {
        const sessionStillPresent = assignedSessions.value.some(
            (session) => session.id === editingSessionDetailsId.value
        );

        if (!sessionStillPresent) {
            editingSessionDetailsId.value = null;
        }
    }
}

async function deleteInvoice() {
    if (isDeletingInvoice.value) {
        return;
    }

    if (!window.confirm('Delete this invoice? This cannot be undone.')) {
        return;
    }

    isDeletingInvoice.value = true;

    try {
        await axios.delete(`/invoices/${invoice.value.id}`);
        router.visit(route('invoices.index'));
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to delete invoice.';
    } finally {
        isDeletingInvoice.value = false;
    }
}

function isExpenseBusy(expenseId) {
    return busyExpenseIds.value.includes(expenseId);
}

function isSavingSessionDate(sessionId) {
    return savingSessionDateIds.value.includes(sessionId);
}

function isSavingSessionDuration(sessionId) {
    return savingSessionDurationIds.value.includes(sessionId);
}

function isSavingSessionTask(sessionId) {
    return savingSessionTaskIds.value.includes(sessionId);
}

function isSavingSessionDetails(sessionId) {
    return savingSessionDetailsIds.value.includes(sessionId);
}

function isEditingSessionDetails(sessionId) {
    return editingSessionDetailsId.value === sessionId;
}

function isSessionStopped(session) {
    return Boolean(session?.stopped_at);
}

function getSessionDate(session) {
    const value = session?.started_at || session?.created_at;

    if (!value) {
        return '';
    }

    const date = new Date(value);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function getSessionDateDraft(session) {
    return sessionDateDrafts.value[session.id] || getSessionDate(session);
}

function getSessionDurationHms(session) {
    return formatDuration(session?.duration_seconds || 0);
}

function getSessionDurationDraft(session) {
    const draft = sessionDurationDrafts.value[session.id];

    return draft === undefined ? getSessionDurationHms(session) : draft;
}

function getSessionTaskDraft(session) {
    const draft = sessionTaskDrafts.value[session.id];

    return draft === undefined ? (session?.task_id ? String(session.task_id) : '') : draft;
}

function getSessionProjectDraft(session) {
    const draft = sessionProjectDrafts.value[session.id];

    if (draft !== undefined && draft !== null && draft !== '') {
        return String(draft);
    }

    if (session?.task?.project_id) {
        return String(session.task.project_id);
    }

    return clientProjects.value[0]?.id ? String(clientProjects.value[0].id) : '';
}

function sessionEditTasksForProject(session) {
    return tasksForProject(getSessionProjectDraft(session));
}

function syncSessionTaskDraftForProject(session) {
    const projectId = getSessionProjectDraft(session);

    if (!projectId) {
        sessionTaskDrafts.value[session.id] = '';
        return;
    }

    const currentTaskId = getSessionTaskDraft(session);
    const availableTasks = tasksForProject(projectId);
    const currentExists = availableTasks.some((task) => String(task.id) === String(currentTaskId));

    if (currentExists) {
        return;
    }

    sessionTaskDrafts.value[session.id] = getDefaultTaskIdForProject(projectId);
}

function parseDurationHms(value) {
    if (typeof value !== 'string') {
        return null;
    }

    const match = value.trim().match(/^(\d+):([0-5]\d):([0-5]\d)$/);

    if (!match) {
        return null;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);
    const seconds = Number(match[3]);

    return (hours * 3600) + (minutes * 60) + seconds;
}

function isEditingSessionDuration(sessionId) {
    return editingSessionDurationId.value === sessionId;
}

function startEditingSessionDuration(session) {
    if (isFinalized.value || isSavingSessionDuration(session.id)) {
        return;
    }

    editingSessionDurationId.value = session.id;
    // Use the exact same formatter as the read-only label so edit mode mirrors displayed elapsed time.
    sessionDurationDrafts.value[session.id] = getSessionDurationHms(session);
}

function cancelEditingSessionDuration(session) {
    if (isSavingSessionDuration(session.id)) {
        return;
    }

    if (editingSessionDurationId.value === session.id) {
        editingSessionDurationId.value = null;
    }

    sessionDurationDrafts.value[session.id] = getSessionDurationHms(session);
}

function startEditingSessionDetails(session) {
    if (isFinalized.value || isSavingSessionDetails(session.id)) {
        return;
    }

    editingSessionDetailsId.value = session.id;
    sessionProjectDrafts.value[session.id] = session?.task?.project_id
        ? String(session.task.project_id)
        : (clientProjects.value[0]?.id ? String(clientProjects.value[0].id) : '');
    sessionTaskDrafts.value[session.id] = session?.task_id ? String(session.task_id) : '';
    sessionDateDrafts.value[session.id] = getSessionDate(session);
    syncSessionTaskDraftForProject(session);
}

function cancelEditingSessionDetails(session) {
    if (isSavingSessionDetails(session.id)) {
        return;
    }

    if (editingSessionDetailsId.value === session.id) {
        editingSessionDetailsId.value = null;
    }

    sessionProjectDrafts.value[session.id] = session?.task?.project_id ? String(session.task.project_id) : '';
    sessionTaskDrafts.value[session.id] = session?.task_id ? String(session.task_id) : '';
    sessionDateDrafts.value[session.id] = getSessionDate(session);
}

async function saveSessionDetails(session) {
    if (isFinalized.value || isSavingSessionDetails(session.id)) {
        return;
    }

    if (!hasActiveClientTasks.value) {
        statusMessage.value = 'Create at least one active task for this client before updating session details.';
        return;
    }

    const taskId = getSessionTaskDraft(session);

    if (!taskId) {
        statusMessage.value = 'Select a task before saving.';
        return;
    }

    const sessionDate = getSessionDateDraft(session);

    if (isSessionStopped(session) && !sessionDate) {
        statusMessage.value = 'Select a date before saving.';
        return;
    }

    savingSessionDetailsIds.value.push(session.id);

    try {
        let payload = null;

        if (isSessionStopped(session)) {
            const dateResponse = await axios.post(`/invoices/${invoice.value.id}/sessions/${session.id}/date`, {
                session_date: sessionDate,
            });

            payload = dateResponse.data;
        }

        const taskResponse = await axios.post(`/invoices/${invoice.value.id}/sessions/${session.id}/task`, {
            project_id: Number(getSessionProjectDraft(session)),
            task_id: Number(taskId),
        });

        payload = taskResponse.data;

        if (payload) {
            applyPayload(payload);
        }

        editingSessionDetailsId.value = null;
        statusMessage.value = 'Session details updated.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update session details.';
    } finally {
        savingSessionDetailsIds.value = savingSessionDetailsIds.value.filter((id) => id !== session.id);
    }
}

async function updateSessionDate(session) {
    const sessionDate = getSessionDateDraft(session);

    if (!sessionDate || isSavingSessionDate(session.id) || isFinalized.value) {
        return;
    }

    savingSessionDateIds.value.push(session.id);

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/sessions/${session.id}/date`, {
            session_date: sessionDate,
        });

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Session date updated.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update session date.';
    } finally {
        savingSessionDateIds.value = savingSessionDateIds.value.filter((id) => id !== session.id);
    }
}

async function updateSessionDuration(session) {
    const durationSeconds = parseDurationHms(getSessionDurationDraft(session));

    if (!Number.isFinite(durationSeconds) || durationSeconds <= 0 || isSavingSessionDuration(session.id) || isFinalized.value) {
        statusMessage.value = 'Use HH:MM:SS (for example 00:22:00).';
        return;
    }

    savingSessionDurationIds.value.push(session.id);

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/sessions/${session.id}/duration`, {
            duration_seconds: durationSeconds,
        });

        applyPayload(response.data);
        editingSessionDurationId.value = null;
        statusMessage.value = response.data.message || 'Session duration updated.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update session duration.';
    } finally {
        savingSessionDurationIds.value = savingSessionDurationIds.value.filter((id) => id !== session.id);
    }
}

async function updateSessionTask(session) {
    if (!hasActiveClientTasks.value) {
        statusMessage.value = 'Create at least one active task for this client before updating session tasks.';
        return;
    }

    const taskId = getSessionTaskDraft(session);

    if (!taskId || isSavingSessionTask(session.id) || isFinalized.value) {
        if (!taskId) {
            statusMessage.value = 'Select a task before saving.';
        }
        return;
    }

    savingSessionTaskIds.value.push(session.id);

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/sessions/${session.id}/task`, {
            task_id: Number(taskId),
        });

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Session task updated.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update session task.';
    } finally {
        savingSessionTaskIds.value = savingSessionTaskIds.value.filter((id) => id !== session.id);
    }
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

    if (!hasActiveClientTasks.value) {
        statusMessage.value = 'Create at least one active task for this client before adding manual sessions.';
        return;
    }

    if (!manualDurationMinutes.value || Number(manualDurationMinutes.value) <= 0) {
        statusMessage.value = 'Enter a manual session duration greater than 0 minutes.';
        return;
    }

    if (!selectedManualProjectId.value) {
        statusMessage.value = 'Select a project before creating a manual session.';
        return;
    }

    isSubmittingManualSession.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/sessions/manual`, {
            duration_minutes: Number(manualDurationMinutes.value),
            started_at: manualStartedAt.value || null,
            project_id: Number(selectedManualProjectId.value),
            task_id: selectedManualTaskId.value ? Number(selectedManualTaskId.value) : null,
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
            selectedInlineTaskId.value = response.data.session?.task_id ? String(response.data.session.task_id) : selectedInlineTaskId.value;
            isInlineTimerRunning.value = Boolean(response.data.running);
            isInlineTimerPaused.value = Boolean(response.data.paused);

            if (isInlineTimerRunning.value) {
                setInlineRunningBaseline(response.data.elapsed_seconds);
                startInlineTicker();
            } else {
                inlineElapsedSeconds.value = Math.max(0, Number(response.data.elapsed_seconds || 0));
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

    if (!hasActiveClientTasks.value) {
        statusMessage.value = 'Create at least one active task for this client before starting timer sessions.';
        return;
    }

    if (!selectedInlineProjectId.value) {
        statusMessage.value = 'Select a project before starting a timer session.';
        return;
    }

    isInlineTimerLoading.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/timer/start`, {
            project_id: Number(selectedInlineProjectId.value),
            task_id: selectedInlineTaskId.value ? Number(selectedInlineTaskId.value) : null,
        });
        await loadInlineTimerStatus();
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
        await loadInlineTimerStatus();
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

async function resumeStoppedSession(session) {
    if (isFinalized.value || isBusy(session.id)) {
        return;
    }

    busySessionIds.value.push(session.id);

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/sessions/${session.id}/resume`);

        applyPayload(response.data);
        await loadInlineTimerStatus();
        statusMessage.value = response.data.message || 'Stopped session resumed.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to resume stopped session.';
    } finally {
        busySessionIds.value = busySessionIds.value.filter((id) => id !== session.id);
    }
}

async function submitResumedSession(session) {
    if (isFinalized.value || isBusy(session.id)) {
        return;
    }

    if (inlineActiveSessionId.value !== session.id) {
        statusMessage.value = 'Only the active resumed session can be submitted.';
        return;
    }

    busySessionIds.value.push(session.id);

    try {
        await stopInlineTimer();
    } finally {
        busySessionIds.value = busySessionIds.value.filter((id) => id !== session.id);
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

async function emailInvoiceToClient() {
    if (invoice.value?.status !== 'finalized' || isSendingInvoiceEmail.value) {
        return;
    }

    isSendingInvoiceEmail.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/email-client`);

        statusMessage.value = response.data.message || 'Invoice email sent to client.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to send invoice email.';
    } finally {
        isSendingInvoiceEmail.value = false;
    }
}

async function addExpense() {
    if (isFinalized.value || isSubmittingExpense.value) {
        return;
    }

    if (!expenseAmount.value || Number(expenseAmount.value) <= 0) {
        statusMessage.value = 'Enter a line item amount greater than 0.';
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
        statusMessage.value = response.data.message || 'Line item added to invoice.';
        expenseName.value = '';
        expenseDescription.value = '';
        expenseAmount.value = '';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to add line item.';
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
        statusMessage.value = response.data.message || 'Line item removed from invoice.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to remove line item.';
    } finally {
        busyExpenseIds.value = busyExpenseIds.value.filter((id) => id !== expenseId);
    }
}

async function saveInvoiceDiscount() {
    if (isFinalized.value || isSavingDiscount.value) {
        return;
    }

    const normalizedType = discountType.value || null;
    const normalizedValue = normalizedType ? Number(discountValue.value || 0) : 0;

    if (normalizedType === 'percentage' && (normalizedValue < 0 || normalizedValue > 100)) {
        statusMessage.value = 'Percentage discount must be between 0 and 100.';
        return;
    }

    if (normalizedType === 'fixed' && normalizedValue < 0) {
        statusMessage.value = 'Fixed discount must be zero or greater.';
        return;
    }

    isSavingDiscount.value = true;

    try {
        const response = await axios.post(`/invoices/${invoice.value.id}/discount`, {
            discount_type: normalizedType,
            discount_value: normalizedValue,
        });

        applyPayload(response.data);
        statusMessage.value = response.data.message || 'Invoice discount updated.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update invoice discount.';
    } finally {
        isSavingDiscount.value = false;
    }
}

onMounted(() => {
    ensureInlineProjectSelection();
    ensureManualProjectSelection();
    ensureInlineTaskSelection();
    ensureManualTaskSelection();
    loadInlineTimerStatus();
});

watch(clientTasks, () => {
    ensureInlineProjectSelection();
    ensureManualProjectSelection();
}, { deep: true });

watch(selectedInlineProjectId, () => {
    ensureInlineTaskSelection();
});

watch(selectedManualProjectId, () => {
    ensureManualTaskSelection();
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
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="isPaid ? 'bg-green-700 text-white' : (isFinalized ? 'bg-gray-800 text-white' : 'bg-emerald-100 text-emerald-700')"
                                >
                                    {{ isPaid ? 'Paid' : (invoice.status === 'finalized' ? 'Finalized' : 'Draft') }}
                                </span>

                                <button
                                    v-if="!isFinalized"
                                    type="button"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-600 text-white hover:bg-red-700 transition disabled:opacity-60"
                                    :disabled="isDeletingInvoice"
                                    title="Delete Invoice"
                                    aria-label="Delete Invoice"
                                    @click="deleteInvoice"
                                >
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 6h18" />
                                        <path d="M8 6V4h8v2" />
                                        <path d="M19 6l-1 14H6L5 6" />
                                        <path d="M10 11v6" />
                                        <path d="M14 11v6" />
                                    </svg>
                                </button>
                            </div>

                            <button
                                v-if="!isFinalized"
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
                                v-if="isFinalized"
                                :href="`/invoices/${invoice.id}/pdf`"
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition"
                            >
                                Download PDF
                            </a>

                            <button
                                v-if="invoice.status === 'finalized'"
                                type="button"
                                class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 transition disabled:opacity-60"
                                :disabled="isSendingInvoiceEmail || !invoice.client || !invoice.client.email"
                                @click="emailInvoiceToClient"
                            >
                                {{ isSendingInvoiceEmail ? 'Sending Email...' : 'Email PDF To Client' }}
                            </button>

                            <p
                                v-if="invoice.status === 'finalized' && (!invoice.client || !invoice.client.email)"
                                class="text-xs text-amber-700 dark:text-amber-300"
                            >
                                Add a valid client email to send this invoice by email.
                            </p>

                            <Link
                                :href="route('invoices.taxSummary', invoice.id)"
                                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition"
                            >
                                Tax Summary
                            </Link>

                        </div>
                    </div>

                    <p v-if="invoice.notes" class="mt-4 text-sm text-gray-700 dark:text-gray-200">
                        {{ invoice.notes }}
                    </p>

                    <p v-if="statusMessage" class="mt-4 text-sm text-gray-700 dark:text-gray-200">
                        {{ statusMessage }}
                    </p>

                    <div class="mt-4 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice Discount</p>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-[180px_1fr_auto] gap-3 items-end">
                            <select
                                v-model="discountType"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                :disabled="isFinalized || isSavingDiscount"
                            >
                                <option value="">No discount</option>
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed amount</option>
                            </select>

                            <input
                                v-model="discountValue"
                                type="number"
                                min="0"
                                :max="discountType === 'percentage' ? 100 : undefined"
                                step="0.01"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                :placeholder="discountType === 'percentage' ? 'Percent (0-100)' : 'Amount'"
                                :disabled="isFinalized || isSavingDiscount || !discountType"
                            />

                            <button
                                type="button"
                                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                                :disabled="isFinalized || isSavingDiscount"
                                @click="saveInvoiceDiscount"
                            >
                                {{ isSavingDiscount ? 'Saving...' : 'Save Discount' }}
                            </button>
                        </div>

                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                            Applied discount: {{ formatCurrency(summary.discount_amount || 0) }}
                        </p>
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
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">One-Off Line Items</p>
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
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Timer</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                            {{ formatDuration(inlineElapsedSeconds) }}
                        </p>
                        <div class="mt-2">
                            <label class="text-xs font-medium text-gray-700 dark:text-gray-200">Project for new timer sessions</label>
                            <select
                                v-model="selectedInlineProjectId"
                                class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                :disabled="isFinalized || isInlineTimerLoading || isInlineTimerRunning || isInlineTimerPaused || !clientProjects.length"
                            >
                                <option value="">Select project</option>
                                <option
                                    v-for="project in clientProjects"
                                    :key="project.id"
                                    :value="String(project.id)"
                                >
                                    {{ project.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mt-2">
                            <label class="text-xs font-medium text-gray-700 dark:text-gray-200">Task for new timer sessions</label>
                            <select
                                v-model="selectedInlineTaskId"
                                class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                :disabled="isFinalized || isInlineTimerLoading || isInlineTimerRunning || isInlineTimerPaused || !selectedInlineProjectId"
                            >
                                <option value="">Use project default task</option>
                                <option
                                    v-for="task in tasksForProject(selectedInlineProjectId)"
                                    :key="task.id"
                                    :value="String(task.id)"
                                >
                                    {{ formatTaskOption(task) }}
                                </option>
                            </select>
                        </div>
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
                            {{ isInlineTimerLoading ? 'Working...' : (isInlineTimerRunning ? 'Pause Timer' : isInlineTimerPaused ? 'Resume Timer' : 'Start Timer') }}
                        </button>

                        <button
                            v-if="isInlineTimerRunning || isInlineTimerPaused"
                            type="button"
                            class="mt-3 ml-3 rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition disabled:opacity-60"
                            :disabled="isFinalized || isInlineTimerLoading"
                            @click="stopInlineTimer"
                        >
                            {{ isInlineTimerLoading ? 'Working...' : 'Submit' }}
                        </button>

                        <p v-if="!hasActiveClientTasks" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                            Create an active task for this client before starting invoice timer sessions.
                        </p>

                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                            {{ isInlineTimerRunning ? 'Recording in progress' : isInlineTimerPaused ? 'Paused' : 'Not recording' }}
                        </p>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-5 gap-3">
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
                        <select
                            v-model="selectedManualProjectId"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            :disabled="isFinalized || isSubmittingManualSession || !clientProjects.length"
                        >
                            <option value="">Select project</option>
                            <option
                                v-for="project in clientProjects"
                                :key="project.id"
                                :value="String(project.id)"
                            >
                                {{ project.name }}
                            </option>
                        </select>
                        <select
                            v-model="selectedManualTaskId"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            :disabled="isFinalized || isSubmittingManualSession || !selectedManualProjectId"
                        >
                            <option value="">Use project default task</option>
                            <option
                                v-for="task in tasksForProject(selectedManualProjectId)"
                                :key="task.id"
                                :value="String(task.id)"
                            >
                                {{ formatTaskOption(task) }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                            :disabled="isFinalized || isSubmittingManualSession || !selectedManualProjectId"
                            @click="createManualSession"
                        >
                            {{ isSubmittingManualSession ? 'Adding Session...' : 'Add Session' }}
                        </button>
                    </div>

                    <p v-if="!hasActiveClientTasks" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                        Manual sessions require at least one active task on this client.
                    </p>

                    <p v-if="assignedSessions.length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        No sessions assigned yet.
                    </p>

                    <div v-else class="mt-4 space-y-5">
                        <section
                            v-for="section in assignedSessionsByProject"
                            :key="section.key"
                            class="rounded-xl border border-gray-200 dark:border-gray-700 p-3"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ section.projectName }}</h3>
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                    Total: {{ formatDuration(totalDurationSecondsForProject(section)) }}
                                </p>
                            </div>

                            <div class="mt-3 space-y-3">
                                <div
                                    v-for="session in visibleSessionsForProject(section)"
                                    :key="session.id"
                                    class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatSessionHeaderDate(session.started_at || session.created_at) }}</p>
                                            <template v-if="!isEditingSessionDetails(session.id)">
                                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                                    <p class="text-xs text-gray-600 dark:text-gray-300">{{ session.task?.name || 'General' }}<span v-if="session.task?.project"> in {{ session.task.project.name }}</span></p>
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-gray-300 text-gray-600 hover:bg-gray-100 transition disabled:opacity-60 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                                        :disabled="isFinalized || isSavingSessionDetails(session.id)"
                                                        title="Edit session details"
                                                        aria-label="Edit session details"
                                                        @click="startEditingSessionDetails(session)"
                                                    >
                                                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <path d="M12 20h9" />
                                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </template>

                                            <template v-else>
                                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                    <div>
                                                        <label class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Project</label>
                                                        <select
                                                            v-model="sessionProjectDrafts[session.id]"
                                                            class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-900 dark:text-white"
                                                            :disabled="isFinalized || isSavingSessionDetails(session.id)"
                                                            @change="syncSessionTaskDraftForProject(session)"
                                                        >
                                                            <option value="">Select project</option>
                                                            <option
                                                                v-for="project in clientProjects"
                                                                :key="project.id"
                                                                :value="String(project.id)"
                                                            >
                                                                {{ project.name }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Task</label>
                                                        <select
                                                            v-model="sessionTaskDrafts[session.id]"
                                                            class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-900 dark:text-white"
                                                            :disabled="isFinalized || isSavingSessionDetails(session.id) || !getSessionProjectDraft(session)"
                                                        >
                                                            <option value="">Select task</option>
                                                            <option
                                                                v-for="task in sessionEditTasksForProject(session)"
                                                                :key="task.id"
                                                                :value="String(task.id)"
                                                            >
                                                                {{ formatTaskOption(task) }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Date</label>
                                                        <input
                                                            v-model="sessionDateDrafts[session.id]"
                                                            type="date"
                                                            class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-900 dark:text-white"
                                                            :max="'9999-12-31'"
                                                            :disabled="isFinalized || isSavingSessionDetails(session.id) || !isSessionStopped(session)"
                                                        />
                                                    </div>
                                                </div>
                                                <p v-if="!isSessionStopped(session)" class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                                                    Date can only be changed after the session is stopped.
                                                </p>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        class="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                                                        :disabled="isFinalized || isSavingSessionDetails(session.id)"
                                                        @click="saveSessionDetails(session)"
                                                    >
                                                        {{ isSavingSessionDetails(session.id) ? 'Saving...' : 'Save Details' }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                                        :disabled="isSavingSessionDetails(session.id)"
                                                        @click="cancelEditingSessionDetails(session)"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </template>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <template v-if="!isEditingSessionDuration(session.id)">
                                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ displaySessionDuration(session) }}</p>
                                                <button
                                                    v-if="isSessionStopped(session)"
                                                    type="button"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-300 text-gray-600 hover:bg-gray-100 transition disabled:opacity-60 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                                    :disabled="isFinalized || isSavingSessionDuration(session.id)"
                                                    title="Edit session duration"
                                                    aria-label="Edit session duration"
                                                    @click="startEditingSessionDuration(session)"
                                                >
                                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M12 20h9" />
                                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                    </svg>
                                                </button>
                                            </template>

                                            <template v-else>
                                                <input
                                                    v-model="sessionDurationDrafts[session.id]"
                                                    type="text"
                                                    inputmode="numeric"
                                                    pattern="^\\d+:[0-5]\\d:[0-5]\\d$"
                                                    placeholder="00:00:00"
                                                    class="w-28 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-900 dark:text-white"
                                                    :disabled="isFinalized || isSavingSessionDuration(session.id)"
                                                />
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-indigo-600 px-2 py-1 text-xs font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                                                    :disabled="isFinalized || isSavingSessionDuration(session.id)"
                                                    @click="updateSessionDuration(session)"
                                                >
                                                    {{ isSavingSessionDuration(session.id) ? 'Saving...' : 'Save' }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                                    :disabled="isSavingSessionDuration(session.id)"
                                                    @click="cancelEditingSessionDuration(session)"
                                                >
                                                    Cancel
                                                </button>
                                            </template>

                                            <button
                                                v-if="isSessionStopped(session)"
                                                type="button"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 transition disabled:opacity-60"
                                                :disabled="isFinalized || isBusy(session.id)"
                                                @click="resumeStoppedSession(session)"
                                            >
                                                {{ isBusy(session.id) ? 'Working...' : 'Resume' }}
                                            </button>

                                            <button
                                                v-if="!isSessionStopped(session)"
                                                type="button"
                                                class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 transition disabled:opacity-60"
                                                :disabled="isFinalized || isInlineTimerLoading || isBusy(session.id) || inlineActiveSessionId !== session.id"
                                                @click="submitResumedSession(session)"
                                            >
                                                {{ isInlineTimerLoading || isBusy(session.id) ? 'Working...' : 'Submit' }}
                                            </button>

                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-white transition disabled:opacity-60"
                                                :class="isFinalized ? 'bg-gray-500 cursor-not-allowed' : 'bg-red-600 hover:bg-red-700'"
                                                :disabled="isFinalized || isBusy(session.id)"
                                                title="Remove session"
                                                aria-label="Remove session"
                                                @click="removeSession(session.id)"
                                            >
                                                <span v-if="isBusy(session.id)" class="text-[10px] font-semibold">...</span>
                                                <svg v-else viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M3 6h18" />
                                                    <path d="M8 6V4h8v2" />
                                                    <path d="M19 6l-1 14H6L5 6" />
                                                    <path d="M10 11v6" />
                                                    <path d="M14 11v6" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="hasMoreSessionsForProject(section)" class="flex justify-end">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                        @click="toggleProjectSection(section.key)"
                                    >
                                        {{ isProjectSectionExpanded(section.key) ? 'Show Latest 2' : `View All (${hiddenSessionsCountForProject(section)} more)` }}
                                    </button>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">One-Off Line Items</h2>

                    <p v-if="isFinalized" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        This invoice is finalized and cannot be changed.
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input
                            v-model="expenseName"
                            type="text"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            placeholder="Line item name (optional)"
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
                        {{ isSubmittingExpense ? 'Adding Line Item...' : 'Add Line Item' }}
                    </button>

                    <p v-if="expenses.length === 0" class="mt-5 text-sm text-gray-600 dark:text-gray-300">
                        No line items added yet.
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
                                        {{ expense.name || 'One-off line item' }}
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
