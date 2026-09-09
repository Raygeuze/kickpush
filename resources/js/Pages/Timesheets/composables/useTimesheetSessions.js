import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export function useTimesheetSessions(props) {
    const currentViewMode = ref(props.view || props.filters?.view || 'day');
    const activeDayKey = ref(
        props.selectedDate
        || props.filters?.date
        || props.days.find((day) => day.is_today)?.key
        || props.days[0]?.key
        || ''
    );

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
    const editForm = reactive({ task_id: '', session_date: '', duration: '', invoice_id: '' });

    const startingCell = ref(null);
    const startForm = reactive({ project_id: '', task_id: '' });
    const startingTimer = ref(false);

    const dayStartForm = reactive({ project_id: '', task_id: '' });
    const startingDayTimer = ref(false);
    const dayStartModalOpen = ref(false);
    const formErrors = ref('');

    watch(() => props.sessions, (next) => {
        liveSessions.value = [...next];
    }, { deep: true });

    watch(() => props.serverNow, (next) => {
        liveServerNow.value = next;
    });

    watch(() => props.view, (next) => {
        if (next) {
            currentViewMode.value = next;
        }
    });

    watch(() => props.selectedDate, (next) => {
        if (next) {
            activeDayKey.value = next;
        }
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

    function formatClockDuration(totalSeconds) {
        const seconds = Math.max(0, Math.floor(Number(totalSeconds || 0)));

        return [Math.floor(seconds / 3600), Math.floor((seconds % 3600) / 60), seconds % 60]
            .map((part) => String(part).padStart(2, '0'))
            .join(':');
    }

    function parseClockDuration(value) {
        const parts = String(value || '').trim().split(':');

        if (parts.length > 3 || parts.some((part) => !/^\d+$/.test(part))) {
            return null;
        }

        return parts.reduce((total, part) => (total * 60) + Number(part), 0);
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

    function daySessionsCount(dayKey) {
        return liveSessions.value.filter((session) => session.day_key === dayKey).length;
    }

    const weekDuration = computed(() => liveSessions.value.reduce((total, session) => total + sessionDuration(session), 0));
    const activeSessionsCount = computed(() => liveSessions.value.filter((session) => session.is_running || session.is_paused).length);
    const activeDaysCount = computed(() => new Set(liveSessions.value.map((session) => session.day_key)).size);

    const activeDay = computed(() => {
        return props.days.find((day) => day.key === activeDayKey.value)
            || { key: activeDayKey.value, short_label: '', date_label: activeDayKey.value, full_label: activeDayKey.value, is_today: false };
    });

    const currentDaySessions = computed(() => {
        return liveSessions.value
            .filter((session) => session.day_key === activeDayKey.value)
            .sort((a, b) => new Date(a.started_at).getTime() - new Date(b.started_at).getTime());
    });

    const currentDayDuration = computed(() => currentDaySessions.value.reduce((total, session) => total + sessionDuration(session), 0));
    const currentDayProjectsCount = computed(() => new Set(currentDaySessions.value.map((session) => session.project_id || session.project_name)).size);
    const currentDayRunningCount = computed(() => currentDaySessions.value.filter((session) => session.is_running).length);

    const currentDayProjectGroups = computed(() => {
        const groups = new Map();

        currentDaySessions.value.forEach((session) => {
            const key = projectKey(session);

            if (!groups.has(key)) {
                groups.set(key, {
                    key,
                    projectId: session.project_id,
                    projectName: session.project_name,
                    clientName: session.client_name,
                    sessions: [],
                });
            }

            groups.get(key).sessions.push(session);
        });

        return Array.from(groups.values()).sort((left, right) => left.projectName.localeCompare(right.projectName));
    });

    const activeTimerRunningElsewhere = computed(() => {
        const active = liveSessions.value.find((session) => session.is_running && session.can_operate);

        if (!active) {
            return null;
        }

        if (currentViewMode.value === 'day' && active.day_key !== activeDayKey.value) {
            return active;
        }

        return null;
    });

    const dayProjectTasks = computed(() => {
        if (!dayStartForm.project_id) {
            return [];
        }

        return (props.tasks || []).filter((task) => String(task.project_id) === String(dayStartForm.project_id));
    });

    watch(() => dayStartForm.project_id, () => {
        dayStartForm.task_id = dayProjectTasks.value[0] ? String(dayProjectTasks.value[0].id) : '';
    });

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

    function routeParams(extra = {}) {
        return {
            view: currentViewMode.value,
            date: activeDayKey.value,
            week: props.weekStart,
            client_id: filterForm.client_id || undefined,
            project_id: filterForm.project_id || undefined,
            user_id: props.canViewTeamSessions ? (filterForm.user_id || undefined) : undefined,
            invoice_status: filterForm.invoice_status || undefined,
            ...extra,
        };
    }

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

    function chooseCellAndStart(row, day) {
        chooseCell(row, day);
        openStartTimer();
    }

    function setViewMode(mode) {
        currentViewMode.value = mode;
        dayStartModalOpen.value = false;
        startingCell.value = null;
        router.get(route('timesheets.index'), routeParams({ view: mode }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function selectDay(dayKey) {
        activeDayKey.value = dayKey;
        editingSessionId.value = null;
        formErrors.value = '';

        if (currentViewMode.value === 'day') {
            router.get(route('timesheets.index'), routeParams({ view: 'day', date: dayKey }), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }
    }

    function goToToday() {
        if (currentViewMode.value === 'day') {
            selectDay(props.dayNavigation?.current_day || props.selectedDate);

            return;
        }

        goToWeek(props.navigation.current_week);
    }

    function applyFilters() {
        if (
            filterForm.project_id
            && !props.projects.some((project) => String(project.id) === filterForm.project_id && (!filterForm.client_id || String(project.client_id) === filterForm.client_id))
        ) {
            filterForm.project_id = '';
        }

        router.get(route('timesheets.index'), routeParams(), {
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
        router.get(route('timesheets.index'), routeParams({ week, date: undefined }), {
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

    function tasksForProject(projectId) {
        if (!projectId) {
            return props.tasks || [];
        }

        return (props.tasks || []).filter((task) => String(task.project_id) === String(projectId));
    }

    const attachableInvoices = computed(() => {
        const clientId = selectedProject.value?.sessions?.[0]?.client_id;

        if (!clientId) {
            return props.draftInvoices || [];
        }

        return (props.draftInvoices || []).filter((invoice) => String(invoice.client_id) === String(clientId));
    });

    function invoicesForClient(clientId) {
        if (!clientId) {
            return props.draftInvoices || [];
        }

        return (props.draftInvoices || []).filter((invoice) => String(invoice.client_id) === String(clientId));
    }

    function startEditing(session) {
        startingCell.value = null;
        formErrors.value = '';
        editingSessionId.value = session.id;
        editForm.task_id = session.task_id ? String(session.task_id) : '';
        editForm.session_date = session.day_key;
        editForm.duration = formatClockDuration(sessionDuration(session));
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

        const nextSeconds = parseClockDuration(editForm.duration);

        if (nextSeconds === null) {
            formErrors.value = 'Enter the duration as HH:MM:SS.';

            return;
        }

        if (nextSeconds < 1) {
            formErrors.value = 'Duration must be at least one second.';

            return;
        }

        if (nextSeconds !== sessionDuration(session)) {
            payload.duration_seconds = nextSeconds;
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

        chooseCellAndStart(newEntryRow, day);
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

    function openDayStartTimer() {
        formErrors.value = '';
        dayStartModalOpen.value = true;
    }

    function cancelDayStartTimer() {
        dayStartModalOpen.value = false;
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

    async function startDayTimer() {
        if (!dayStartForm.task_id) {
            formErrors.value = 'Select a project and task before starting a timer.';

            return;
        }

        startingDayTimer.value = true;
        formErrors.value = '';

        const started = await mutateSession(null, () => axios.post('/timer/sessions', {
            task_id: Number(dayStartForm.task_id),
            session_date: activeDayKey.value,
        }));

        startingDayTimer.value = false;

        if (started) {
            dayStartForm.project_id = '';
            dayStartForm.task_id = '';
            dayStartModalOpen.value = false;
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
        dayStartModalOpen.value = false;
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

    return reactive({
        currentViewMode,
        activeDayKey,
        filterForm,
        showWeekends,
        selectedDayKey,
        selectedCell,
        statusMessage,
        liveSessions,
        editingSessionId,
        editForm,
        startingCell,
        startForm,
        startingTimer,
        dayStartForm,
        startingDayTimer,
        dayStartModalOpen,
        formErrors,
        visibleDays,
        filteredProjects,
        allSessions,
        projectRows,
        NEW_ROW_KEY,
        newEntryRow,
        isNewEntryCell,
        projectsByClient,
        weekDuration,
        activeSessionsCount,
        activeDaysCount,
        activeDay,
        currentDaySessions,
        currentDayDuration,
        currentDayProjectsCount,
        currentDayRunningCount,
        currentDayProjectGroups,
        activeTimerRunningElsewhere,
        dayProjectTasks,
        selectedSessions,
        selectedProject,
        selectedDay,
        mobileProjectRows,
        sessionDuration,
        formatDuration,
        formatPreciseDuration,
        sessionsForCell,
        cellDuration,
        projectDuration,
        dayDuration,
        daySessionsCount,
        chooseCell,
        chooseCellAndStart,
        setViewMode,
        selectDay,
        goToToday,
        applyFilters,
        clearFilters,
        goToWeek,
        isDeleting,
        isBusy,
        stopSession,
        restartSession,
        detachInvoice,
        projectTasks,
        tasksForProject,
        attachableInvoices,
        invoicesForClient,
        startEditing,
        cancelEditing,
        saveEdit,
        attachInvoice,
        hasActiveSession,
        openStartTimer,
        chooseNewEntryCell,
        cancelStartTimer,
        openDayStartTimer,
        cancelDayStartTimer,
        startTimer,
        startDayTimer,
        deleteSession,
        invoiceLabel,
    });
}
