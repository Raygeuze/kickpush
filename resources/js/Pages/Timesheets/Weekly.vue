<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    weekStart: String,
    weekEnd: String,
    timezone: String,
    serverNow: String,
    days: Array,
    sessions: Array,
    clients: Array,
    projects: Array,
    tasks: Array,
    draftInvoices: Array,
    teamMembers: Array,
    canViewTeamSessions: Boolean,
    canCreateSessions: Boolean,
    filters: Object,
    navigation: Object,
});

const filterForm = reactive({
    client_id: props.filters?.client_id ? String(props.filters.client_id) : '',
    project_id: props.filters?.project_id ? String(props.filters.project_id) : '',
    user_id: props.filters?.user_id ? String(props.filters.user_id) : '',
    invoice_status: props.filters?.invoice_status || '',
});
const showWeekends = ref(true);
const selectedDayKey = ref(props.days.find((day) => day.is_today)?.key || props.days[0]?.key || '');
const selectedCell = ref(null);
const deletingSessionIds = ref([]);
const busySessionIds = ref([]);
const statusMessage = ref('');
const clockMs = ref(Date.now());
let clockInterval = null;

const liveSessions = ref([...props.sessions]);
const liveServerNow = ref(props.serverNow);
const editingSessionId = ref(null);
const editForm = reactive({ task_id: '', session_date: '', duration_minutes: '', invoice_id: '' });
const startingCell = ref(null);
const startForm = reactive({ project_id: '', task_id: '' });
const startingTimer = ref(false);
const formErrors = ref('');

watch(() => props.sessions, (next) => {
    liveSessions.value = [...next];
}, { deep: true });

watch(() => props.serverNow, (next) => {
    liveServerNow.value = next;
});

const visibleDays = computed(() => showWeekends.value ? props.days : props.days.slice(0, 5));

const filteredProjects = computed(() => {
    if (!filterForm.client_id) {
        return props.projects;
    }

    return props.projects.filter((project) => String(project.client_id) === filterForm.client_id);
});

const serverNowMs = computed(() => new Date(liveServerNow.value).getTime());
const allSessions = computed(() => liveSessions.value);

function sessionDuration(session) {
    const baseline = Math.max(0, Number(session.elapsed_seconds || 0));

    if (!session.is_running) {
        return baseline;
    }

    return baseline + Math.max(0, Math.floor((clockMs.value - serverNowMs.value) / 1000));
}

function formatDuration(totalSeconds) {
    const seconds = Math.max(0, Math.floor(Number(totalSeconds || 0)));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return `${hours}:${String(minutes).padStart(2, '0')}`;
}

function formatPreciseDuration(totalSeconds) {
    const seconds = Math.max(0, Math.floor(Number(totalSeconds || 0)));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}

function projectKey(session) {
    return session.project_id ? `project-${session.project_id}` : `project-name-${session.project_name}`;
}

const projectRows = computed(() => {
    const rows = new Map();

    liveSessions.value.forEach((session) => {
        const key = projectKey(session);

        if (!rows.has(key)) {
            rows.set(key, {
                key,
                projectId: session.project_id,
                projectName: session.project_name,
                clientName: session.client_name,
                sessions: [],
            });
        }

        rows.get(key).sessions.push(session);
    });

    return Array.from(rows.values()).sort((left, right) => left.projectName.localeCompare(right.projectName));
});

const NEW_ROW_KEY = '__new__';
const newEntryRow = { key: NEW_ROW_KEY, projectId: null, projectName: 'New entry', clientName: '', sessions: [] };
const isNewEntryCell = computed(() => selectedCell.value?.projectKey === NEW_ROW_KEY);

const projectsByClient = computed(() => {
    const clientNames = new Map((props.clients || []).map((client) => [String(client.id), client.name]));
    const groups = new Map();

    (props.projects || []).forEach((project) => {
        const clientName = clientNames.get(String(project.client_id)) || 'Unassigned client';

        if (!groups.has(clientName)) {
            groups.set(clientName, []);
        }

        groups.get(clientName).push(project);
    });

    return Array.from(groups.entries())
        .map(([clientName, projects]) => ({
            clientName,
            projects: [...projects].sort((left, right) => String(left.name || '').localeCompare(String(right.name || ''))),
        }))
        .sort((left, right) => left.clientName.localeCompare(right.clientName));
});

function sessionsForCell(row, dayKey) {
    return row.sessions.filter((session) => session.day_key === dayKey);
}

function cellDuration(row, dayKey) {
    return sessionsForCell(row, dayKey).reduce((total, session) => total + sessionDuration(session), 0);
}

function projectDuration(row) {
    return row.sessions.reduce((total, session) => total + sessionDuration(session), 0);
}

function dayDuration(dayKey) {
    return liveSessions.value
        .filter((session) => session.day_key === dayKey)
        .reduce((total, session) => total + sessionDuration(session), 0);
}

const weekDuration = computed(() => liveSessions.value.reduce((total, session) => total + sessionDuration(session), 0));
const activeSessionsCount = computed(() => liveSessions.value.filter((session) => session.is_running || session.is_paused).length);
const activeDaysCount = computed(() => new Set(liveSessions.value.map((session) => session.day_key)).size);

const selectedSessions = computed(() => {
    if (!selectedCell.value) {
        return [];
    }

    const row = projectRows.value.find((project) => project.key === selectedCell.value.projectKey);
    return row ? sessionsForCell(row, selectedCell.value.dayKey) : [];
});

const selectedProject = computed(() => projectRows.value.find((project) => project.key === selectedCell.value?.projectKey) || null);
const selectedDay = computed(() => props.days.find((day) => day.key === selectedCell.value?.dayKey) || null);

const mobileProjectRows = computed(() => projectRows.value
    .map((row) => ({ ...row, daySessions: sessionsForCell(row, selectedDayKey.value) }))
    .filter((row) => row.daySessions.length > 0));

function chooseCell(row, day) {
    if (!day) {
        return;
    }

    editingSessionId.value = null;
    startingCell.value = null;
    formErrors.value = '';
    selectedCell.value = {
        projectKey: row.key,
        dayKey: day.key,
    };
}

function applyFilters() {
    if (
        filterForm.project_id
        && !props.projects.some((project) => String(project.id) === filterForm.project_id && (!filterForm.client_id || String(project.client_id) === filterForm.client_id))
    ) {
        filterForm.project_id = '';
    }

    router.get(route('timesheets.index'), {
        week: props.weekStart,
        client_id: filterForm.client_id || undefined,
        project_id: filterForm.project_id || undefined,
        user_id: props.canViewTeamSessions ? (filterForm.user_id || undefined) : undefined,
        invoice_status: filterForm.invoice_status || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters() {
    filterForm.client_id = '';
    filterForm.project_id = '';
    filterForm.user_id = '';
    filterForm.invoice_status = '';
    applyFilters();
}

function goToWeek(week) {
    router.get(route('timesheets.index'), {
        week,
        client_id: filterForm.client_id || undefined,
        project_id: filterForm.project_id || undefined,
        user_id: props.canViewTeamSessions ? (filterForm.user_id || undefined) : undefined,
        invoice_status: filterForm.invoice_status || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function isDeleting(sessionId) {
    return deletingSessionIds.value.includes(sessionId);
}

function isBusy(sessionId) {
    return busySessionIds.value.includes(sessionId);
}

const weekDayKeys = computed(() => props.days.map((day) => day.key));

function applySessionPayload(payload) {
    if (!payload?.session) {
        return;
    }

    const { session, server_now: serverNow } = payload;

    if (serverNow) {
        liveServerNow.value = serverNow;
    }

    const index = liveSessions.value.findIndex((item) => item.id === session.id);
    const withinWeek = weekDayKeys.value.includes(session.day_key);

    if (!withinWeek) {
        if (index !== -1) {
            liveSessions.value.splice(index, 1);
        }

        return;
    }

    if (index === -1) {
        liveSessions.value.push(session);
    } else {
        liveSessions.value.splice(index, 1, session);
    }
}

async function mutateSession(sessionId, request) {
    if (sessionId !== null) {
        busySessionIds.value.push(sessionId);
    }

    formErrors.value = '';

    try {
        const response = await request();
        applySessionPayload(response.data);
        statusMessage.value = response.data?.message || 'Timer session updated.';

        return response.data || {};
    } catch (error) {
        formErrors.value = error?.response?.data?.message
            || Object.values(error?.response?.data?.errors || {}).flat()[0]
            || 'Failed to update timer session.';
        statusMessage.value = formErrors.value;

        return null;
    } finally {
        busySessionIds.value = busySessionIds.value.filter((id) => id !== sessionId);
    }
}

function stopSession(session) {
    return mutateSession(session.id, () => axios.post(`/timer/sessions/${session.id}/stop`));
}

function restartSession(session) {
    return mutateSession(session.id, () => axios.post(`/timer/sessions/${session.id}/restart`));
}

function detachInvoice(session) {
    return mutateSession(session.id, () => axios.delete(`/timer/sessions/${session.id}/invoice`));
}

const projectTasks = computed(() => {
    const projectId = isNewEntryCell.value ? startForm.project_id : selectedProject.value?.projectId;

    if (!projectId) {
        return isNewEntryCell.value ? [] : (props.tasks || []);
    }

    return (props.tasks || []).filter((task) => String(task.project_id) === String(projectId));
});

const attachableInvoices = computed(() => {
    const clientId = selectedProject.value?.sessions?.[0]?.client_id;

    if (!clientId) {
        return props.draftInvoices || [];
    }

    return (props.draftInvoices || []).filter((invoice) => String(invoice.client_id) === String(clientId));
});

function startEditing(session) {
    startingCell.value = null;
    formErrors.value = '';
    editingSessionId.value = session.id;
    editForm.task_id = session.task_id ? String(session.task_id) : '';
    editForm.session_date = session.day_key;
    editForm.duration_minutes = String(Math.max(1, Math.round(sessionDuration(session) / 60)));
    editForm.invoice_id = session.invoice_id ? String(session.invoice_id) : '';
}

function cancelEditing() {
    editingSessionId.value = null;
    formErrors.value = '';
}

async function saveEdit(session) {
    const payload = {};

    if (editForm.task_id && String(editForm.task_id) !== String(session.task_id)) {
        payload.task_id = Number(editForm.task_id);
    }

    if (editForm.session_date && editForm.session_date !== session.day_key) {
        payload.session_date = editForm.session_date;
    }

    const nextMinutes = Number(editForm.duration_minutes);
    const currentMinutes = Math.round(sessionDuration(session) / 60);

    if (Number.isFinite(nextMinutes) && nextMinutes > 0 && nextMinutes !== currentMinutes) {
        payload.duration_minutes = nextMinutes;
    }

    if (Object.keys(payload).length === 0) {
        cancelEditing();

        return;
    }

    const saved = await mutateSession(session.id, () => axios.patch(`/timer/sessions/${session.id}`, payload));

    if (saved) {
        editingSessionId.value = null;
    }
}

async function attachInvoice(session, invoiceId) {
    if (!invoiceId) {
        return;
    }

    await mutateSession(session.id, () => axios.post(`/timer/sessions/${session.id}/invoice`, {
        invoice_id: Number(invoiceId),
    }));
}

const hasActiveSession = computed(() => liveSessions.value.some((session) => (session.is_running || session.is_paused) && session.can_operate));

function openStartTimer() {
    if (!selectedCell.value) {
        return;
    }

    editingSessionId.value = null;
    formErrors.value = '';
    startingCell.value = { ...selectedCell.value };
    startForm.project_id = isNewEntryCell.value ? '' : String(selectedProject.value?.projectId || '');
    startForm.task_id = projectTasks.value[0] ? String(projectTasks.value[0].id) : '';
}

function chooseNewEntryCell(day) {
    if (!day) {
        return;
    }

    chooseCell(newEntryRow, day);
    openStartTimer();
}

watch(() => startForm.project_id, () => {
    if (isNewEntryCell.value) {
        startForm.task_id = projectTasks.value[0] ? String(projectTasks.value[0].id) : '';
    }
});

function cancelStartTimer() {
    startingCell.value = null;
    formErrors.value = '';
}

async function startTimer() {
    if (!startingCell.value || !startForm.task_id) {
        formErrors.value = 'Select a task before starting a timer.';

        return;
    }

    startingTimer.value = true;

    const started = await mutateSession(null, () => axios.post('/timer/sessions', {
        task_id: Number(startForm.task_id),
        session_date: startingCell.value.dayKey,
    }));

    startingTimer.value = false;

    if (started) {
        startingCell.value = null;

        if (started.session) {
            selectedCell.value = {
                projectKey: projectKey(started.session),
                dayKey: started.session.day_key,
            };
        }
    }
}

async function deleteSession(session) {
    if (!session.can_delete || !window.confirm(`Delete timer session #${session.id}? This cannot be undone.`)) {
        return;
    }

    deletingSessionIds.value.push(session.id);

    try {
        const response = await axios.delete(`/timer/${session.id}`);
        statusMessage.value = response.data.message || 'Timer session deleted.';
        liveSessions.value = liveSessions.value.filter((item) => item.id !== session.id);
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to delete timer session.';
    } finally {
        deletingSessionIds.value = deletingSessionIds.value.filter((id) => id !== session.id);
    }
}

function invoiceLabel(session) {
    if (!session.invoice_id) {
        return 'Unassigned';
    }

    return `INV${session.invoice_id} · ${session.invoice_status || 'draft'}`;
}

watch(() => props.weekStart, () => {
    selectedCell.value = null;
    editingSessionId.value = null;
    startingCell.value = null;
    formErrors.value = '';
    selectedDayKey.value = props.days.find((day) => day.is_today)?.key || props.days[0]?.key || '';
});

watch(showWeekends, () => {
    if (!visibleDays.value.some((day) => day.key === selectedDayKey.value)) {
        selectedDayKey.value = visibleDays.value[0]?.key || '';
    }
});

onMounted(() => {
    clockInterval = window.setInterval(() => {
        clockMs.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (clockInterval) {
        window.clearInterval(clockInterval);
    }
});
</script>

<template>
    <AppLayout title="Timesheet">
        <Head title="Timesheet" />

        <div class="min-h-screen bg-gray-100 py-8 dark:bg-black">
            <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                <header class="flex flex-col gap-4 border-b border-gray-200 pb-5 dark:border-gray-800 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">Weekly record</p>
                        <h1 class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">Timesheet</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ weekStart }} to {{ weekEnd }} · {{ timezone }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                            title="Previous week"
                            aria-label="Previous week"
                            @click="goToWeek(navigation.previous_week)"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
                        </button>
                        <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800" @click="goToWeek(navigation.current_week)">
                            This week
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                            title="Next week"
                            aria-label="Next week"
                            @click="goToWeek(navigation.next_week)"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                        </button>
                    </div>
                </header>

                <section class="grid grid-cols-2 border-y border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 sm:grid-cols-4">
                    <div class="border-b border-r border-gray-200 p-4 dark:border-gray-800 sm:border-b-0">
                        <p class="text-xs text-gray-500">Week total</p>
                        <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ formatDuration(weekDuration) }}</p>
                    </div>
                    <div class="border-b border-gray-200 p-4 dark:border-gray-800 sm:border-b-0 sm:border-r">
                        <p class="text-xs text-gray-500">Sessions</p>
                        <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ allSessions.length }}</p>
                    </div>
                    <div class="border-r border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-xs text-gray-500">Active days</p>
                        <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ activeDaysCount }}</p>
                    </div>
                    <div class="p-4">
                        <p class="text-xs text-gray-500">Open sessions</p>
                        <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ activeSessionsCount }}</p>
                    </div>
                </section>

                <section class="border-b border-gray-200 pb-5 dark:border-gray-800">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <select v-model="filterForm.client_id" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="applyFilters">
                            <option value="">All clients</option>
                            <option v-for="client in clients" :key="client.id" :value="String(client.id)">{{ client.name }}</option>
                        </select>
                        <select v-model="filterForm.project_id" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="applyFilters">
                            <option value="">All projects</option>
                            <option v-for="project in filteredProjects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                        </select>
                        <select v-if="canViewTeamSessions" v-model="filterForm.user_id" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="applyFilters">
                            <option value="">All members</option>
                            <option v-for="member in teamMembers" :key="member.id" :value="String(member.id)">{{ member.name }}</option>
                        </select>
                        <select v-model="filterForm.invoice_status" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="applyFilters">
                            <option value="">All invoice states</option>
                            <option value="unassigned">Unassigned</option>
                            <option value="draft">Draft</option>
                            <option value="finalized">Finalized</option>
                            <option value="paid">Paid</option>
                        </select>
                        <div class="flex items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input v-model="showWeekends" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                Weekends
                            </label>
                            <button type="button" class="text-sm font-semibold text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" @click="clearFilters">Clear</button>
                        </div>
                    </div>
                    <p v-if="statusMessage" class="mt-3 text-sm text-gray-700 dark:text-gray-200">{{ statusMessage }}</p>
                </section>

                <section class="hidden overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 md:block">
                    <table class="w-full min-w-[940px] table-fixed border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                                <th class="w-52 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                <th v-for="day in visibleDays" :key="day.key" class="px-2 py-3 text-center text-xs font-semibold text-gray-500" :class="day.is_today ? 'bg-emerald-50 dark:bg-emerald-950/30' : ''">
                                    <span class="block text-gray-900 dark:text-white">{{ day.short_label }}</span>
                                    <span>{{ day.date_label }}</span>
                                </th>
                                <th class="w-24 px-3 py-3 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in projectRows" :key="row.key" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <th class="px-4 py-3 text-left">
                                    <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ row.projectName }}</span>
                                    <span class="block truncate text-xs font-normal text-gray-500">{{ row.clientName }}</span>
                                </th>
                                <td v-for="day in visibleDays" :key="day.key" class="p-1.5" :class="day.is_today ? 'bg-emerald-50/60 dark:bg-emerald-950/20' : ''">
                                    <button
                                        type="button"
                                        class="h-14 w-full rounded-md text-center transition"
                                        :class="[
                                            sessionsForCell(row, day.key).length ? 'bg-gray-100 text-gray-950 hover:bg-emerald-100 dark:bg-gray-900 dark:text-white dark:hover:bg-emerald-950' : 'text-gray-300 hover:bg-gray-50 dark:text-gray-700 dark:hover:bg-gray-900',
                                            selectedCell && selectedCell.projectKey === row.key && selectedCell.dayKey === day.key ? 'ring-2 ring-emerald-500' : '',
                                        ]"
                                        @click="chooseCell(row, day)"
                                    >
                                        <span class="block text-sm font-semibold">{{ sessionsForCell(row, day.key).length ? formatDuration(cellDuration(row, day.key)) : '—' }}</span>
                                        <span v-if="sessionsForCell(row, day.key).length" class="text-[11px] text-gray-500">{{ sessionsForCell(row, day.key).length }} session{{ sessionsForCell(row, day.key).length === 1 ? '' : 's' }}</span>
                                    </button>
                                </td>
                                <td class="px-3 py-3 text-right text-sm font-bold text-gray-950 dark:text-white">{{ formatDuration(projectDuration(row)) }}</td>
                            </tr>
                            <tr v-if="canCreateSessions" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <th class="px-4 py-3 text-left">
                                    <span class="block truncate text-sm font-semibold text-gray-500">New entry</span>
                                    <span class="block truncate text-xs font-normal text-gray-400">Pick a project and task</span>
                                </th>
                                <td v-for="day in visibleDays" :key="day.key" class="p-1.5" :class="day.is_today ? 'bg-emerald-50/60 dark:bg-emerald-950/20' : ''">
                                    <button
                                        type="button"
                                        class="flex h-14 w-full items-center justify-center rounded-md border border-dashed border-gray-300 text-gray-400 transition hover:border-emerald-400 hover:text-emerald-600 dark:border-gray-700 dark:text-gray-600 dark:hover:border-emerald-700"
                                        :class="selectedCell && selectedCell.projectKey === '__new__' && selectedCell.dayKey === day.key ? 'ring-2 ring-emerald-500' : ''"
                                        :title="`Start a timer on ${day.full_label}`"
                                        @click="chooseNewEntryCell(day)"
                                    >
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14" /><path d="M5 12h14" /></svg>
                                    </button>
                                </td>
                                <td class="px-3 py-3"></td>
                            </tr>
                            <tr v-if="projectRows.length" class="bg-gray-50 dark:bg-gray-900">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Daily total</th>
                                <td v-for="day in visibleDays" :key="day.key" class="px-2 py-3 text-center text-sm font-bold text-gray-900 dark:text-white">{{ formatDuration(dayDuration(day.key)) }}</td>
                                <td class="px-3 py-3 text-right text-sm font-bold text-emerald-700 dark:text-emerald-400">{{ formatDuration(weekDuration) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="md:hidden">
                    <div class="flex gap-1 overflow-x-auto border-b border-gray-200 pb-2 dark:border-gray-800">
                        <button v-for="day in visibleDays" :key="day.key" type="button" class="min-w-20 rounded-lg px-3 py-2 text-sm" :class="selectedDayKey === day.key ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-200'" @click="selectedDayKey = day.key">
                            <span class="block font-semibold">{{ day.short_label }}</span>
                            <span class="text-xs opacity-75">{{ day.date_label }}</span>
                        </button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <button v-for="row in mobileProjectRows" :key="row.key" type="button" class="flex w-full items-center justify-between rounded-lg border border-gray-200 bg-white p-4 text-left dark:border-gray-800 dark:bg-gray-950" @click="chooseCell(row, days.find((day) => day.key === selectedDayKey))">
                            <span><span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ row.projectName }}</span><span class="text-xs text-gray-500">{{ row.clientName }} · {{ row.daySessions.length }} session{{ row.daySessions.length === 1 ? '' : 's' }}</span></span>
                            <span class="text-sm font-bold text-gray-950 dark:text-white">{{ formatDuration(cellDuration(row, selectedDayKey)) }}</span>
                        </button>
                        <p v-if="mobileProjectRows.length === 0" class="py-8 text-center text-sm text-gray-500">No sessions recorded on this day.</p>
                        <button v-if="canCreateSessions" type="button" class="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 p-4 text-sm font-semibold text-gray-500 hover:border-emerald-400 hover:text-emerald-600 dark:border-gray-700" @click="chooseNewEntryCell(days.find((day) => day.key === selectedDayKey))">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14" /><path d="M5 12h14" /></svg>
                            New entry
                        </button>
                    </div>
                </section>

                <section v-if="selectedCell" class="border-t border-gray-300 pt-5 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ isNewEntryCell ? 'New entry' : selectedProject?.projectName }}</h2>
                            <p class="text-sm text-gray-500">{{ selectedDay?.full_label }} · {{ formatPreciseDuration(selectedSessions.reduce((total, session) => total + sessionDuration(session), 0)) }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                v-if="canCreateSessions && !startingCell"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                                :disabled="hasActiveSession"
                                :title="hasActiveSession ? 'Stop your active timer before starting another' : 'Start a timer on this project'"
                                @click="openStartTimer"
                            >
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                                Start timer
                            </button>
                            <button type="button" class="text-sm font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="selectedCell = null">Close</button>
                        </div>
                    </div>

                    <p v-if="formErrors" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ formErrors }}</p>

                    <form v-if="startingCell" class="relative mt-3 flex flex-col gap-3 rounded-lg border border-emerald-300 bg-emerald-50/50 p-4 dark:border-emerald-800 dark:bg-emerald-950/20 sm:flex-row sm:items-end" @submit.prevent="startTimer">
                        <p class="absolute right-4 top-3 text-xs text-gray-500">Timer runs from now and is recorded on {{ selectedDay?.full_label }}.</p>
                        <label v-if="isNewEntryCell" class="flex-1 text-xs font-semibold uppercase text-gray-500">
                            Project
                            <select v-model="startForm.project_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="">Select a project</option>
                                <optgroup v-for="group in projectsByClient" :key="group.clientName" :label="group.clientName">
                                    <option v-for="project in group.projects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                                </optgroup>
                            </select>
                        </label>
                        <label class="flex-1 text-xs font-semibold uppercase text-gray-500">
                            Task
                            <select v-model="startForm.task_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="">Select a task</option>
                                <option v-for="task in projectTasks" :key="task.id" :value="String(task.id)">{{ task.name }}</option>
                            </select>
                        </label>
                        <div class="flex items-center gap-3">
                            <button type="submit" class="rounded-lg bg-gray-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60 dark:bg-white dark:text-gray-950" :disabled="startingTimer">Start</button>
                            <button type="button" class="text-sm font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="cancelStartTimer">Cancel</button>
                        </div>
                    </form>

                    <div class="mt-3 divide-y divide-gray-200 border-y border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                        <article v-for="session in selectedSessions" :key="session.id" class="py-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ session.task_name }}</p>
                                        <span v-if="session.is_running" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Running</span>
                                        <span v-else-if="session.is_paused" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Open</span>
                                        <span v-else-if="session.invoice_locked" class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-700">Locked</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">{{ session.user_name }} · {{ session.started_time }}<span v-if="session.stopped_time">–{{ session.stopped_time }}</span> · {{ invoiceLabel(session) }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ formatPreciseDuration(sessionDuration(session)) }}</span>

                                    <button v-if="session.can_operate && (session.is_running || session.is_paused)" type="button" class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-200" :disabled="isBusy(session.id)" @click="stopSession(session)">Stop</button>
                                    <button v-if="session.can_operate && !session.is_running && !session.is_paused && !session.invoice_locked" type="button" class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-200" :disabled="isBusy(session.id)" @click="restartSession(session)">Restart</button>
                                    <button v-if="session.can_update && !session.invoice_locked && !session.is_running && !session.is_paused" type="button" class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-200" :disabled="isBusy(session.id)" @click="editingSessionId === session.id ? cancelEditing() : startEditing(session)">
                                        {{ editingSessionId === session.id ? 'Cancel' : 'Edit' }}
                                    </button>

                                    <Link v-if="session.invoice_id" :href="route('invoices.show', session.invoice_id)" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">Invoice</Link>
                                    <button v-if="session.can_delete" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-600 text-white transition hover:bg-red-700 disabled:opacity-60" :disabled="isDeleting(session.id)" title="Delete timer session" aria-label="Delete timer session" @click="deleteSession(session)">
                                        <span v-if="isDeleting(session.id)" class="text-[10px] font-semibold">...</span>
                                        <svg v-else viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6" /><path d="M14 11v6" /></svg>
                                    </button>
                                </div>
                            </div>

                            <form v-if="editingSessionId === session.id" class="mt-3 grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-4" @submit.prevent="saveEdit(session)">
                                <label class="text-xs font-semibold uppercase text-gray-500 sm:col-span-2">
                                    Task
                                    <select v-model="editForm.task_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                        <option v-for="task in projectTasks" :key="task.id" :value="String(task.id)">{{ task.name }}</option>
                                    </select>
                                </label>
                                <label class="text-xs font-semibold uppercase text-gray-500">
                                    Date
                                    <input v-model="editForm.session_date" type="date" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                </label>
                                <label class="text-xs font-semibold uppercase text-gray-500">
                                    Minutes
                                    <input v-model="editForm.duration_minutes" type="number" min="1" max="10080" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                </label>

                                <div class="flex flex-wrap items-center gap-3 sm:col-span-4">
                                    <button type="submit" class="rounded-lg bg-gray-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60 dark:bg-white dark:text-gray-950" :disabled="isBusy(session.id)">Save changes</button>

                                    <template v-if="session.invoice_id">
                                        <button type="button" class="text-sm font-semibold text-gray-600 hover:text-gray-950 disabled:opacity-60 dark:text-gray-300 dark:hover:text-white" :disabled="isBusy(session.id)" @click="detachInvoice(session)">Remove from invoice</button>
                                    </template>
                                    <template v-else-if="attachableInvoices.length">
                                        <select v-model="editForm.invoice_id" class="rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                            <option value="">Add to draft invoice…</option>
                                            <option v-for="invoice in attachableInvoices" :key="invoice.id" :value="String(invoice.id)">INV{{ invoice.invoice_number || invoice.id }}</option>
                                        </select>
                                        <button type="button" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 disabled:opacity-60 dark:text-emerald-400" :disabled="isBusy(session.id) || !editForm.invoice_id" @click="attachInvoice(session, editForm.invoice_id)">Attach</button>
                                    </template>
                                </div>
                            </form>
                        </article>

                        <p v-if="selectedSessions.length === 0" class="py-6 text-center text-sm text-gray-500">No sessions recorded in this cell yet.</p>
                    </div>
                </section>

                <p v-if="allSessions.length === 0" class="rounded-lg border border-dashed border-gray-300 py-14 text-center text-sm text-gray-500 dark:border-gray-700">
                    No timer sessions match this week and filter selection.
                </p>
            </div>
        </div>
    </AppLayout>
</template>