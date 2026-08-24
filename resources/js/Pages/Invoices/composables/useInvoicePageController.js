import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export function useInvoicePageController(options) {
    const {
        initialInvoice,
        initialAssignedSessions = [],
        initialClientTasks = [],
        initialAvailableSessions = [],
        initialExpenses = [],
        initialSummary,
        formatDuration,
        onInvoiceDeleted,
    } = options;

    const invoice = ref(initialInvoice);
    const assignedSessions = ref(initialAssignedSessions || []);
    const clientTasks = ref(initialClientTasks || []);
    const availableSessions = ref(initialAvailableSessions || []);
    const expenses = ref(initialExpenses || []);
    const summary = ref(initialSummary);

    const statusMessage = ref('');

    const isFinalizing = ref(false);
    const isMarkingPaid = ref(false);
    const isSendingInvoiceEmail = ref(false);
    const isDeletingInvoice = ref(false);
    const isSavingDiscount = ref(false);
    const isSubmittingExpense = ref(false);
    const isSubmittingManualSession = ref(false);
    const isInlineTimerLoading = ref(false);

    const busySessionIds = ref([]);
    const busyExpenseIds = ref([]);

    const savingSessionDateIds = ref([]);
    const savingSessionDurationIds = ref([]);
    const savingSessionTaskIds = ref([]);
    const savingSessionDetailsIds = ref([]);

    const editingSessionDurationId = ref(null);
    const editingSessionDetailsId = ref(null);

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

    const sessionDateDrafts = ref(
        Object.fromEntries(
            (initialAssignedSessions || []).map((session) => {
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
            (initialAssignedSessions || []).map((session) => [session.id, getSessionDurationHms(session)])
        )
    );

    const sessionTaskDrafts = ref(
        Object.fromEntries(
            (initialAssignedSessions || []).map((session) => [session.id, session?.task_id ? String(session.task_id) : ''])
        )
    );

    const sessionProjectDrafts = ref(
        Object.fromEntries(
            (initialAssignedSessions || []).map((session) => [session.id, session?.task?.project_id ? String(session.task.project_id) : ''])
        )
    );

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

    function isEditingSessionDuration(sessionId) {
        return editingSessionDurationId.value === sessionId;
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

    function setSessionProjectDraft(sessionId, value) {
        sessionProjectDrafts.value[sessionId] = value ? String(value) : '';
    }

    function setSessionTaskDraft(sessionId, value) {
        sessionTaskDrafts.value[sessionId] = value ? String(value) : '';
    }

    function setSessionDateDraft(sessionId, value) {
        sessionDateDrafts.value[sessionId] = value || '';
    }

    function setSessionDurationDraft(sessionId, value) {
        sessionDurationDrafts.value[sessionId] = value || '';
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
            const durationSessionStillPresent = assignedSessions.value.some(
                (session) => session.id === editingSessionDurationId.value
            );

            if (!durationSessionStillPresent) {
                editingSessionDurationId.value = null;
            }
        }

        if (editingSessionDetailsId.value !== null) {
            const detailsSessionStillPresent = assignedSessions.value.some(
                (session) => session.id === editingSessionDetailsId.value
            );

            if (!detailsSessionStillPresent) {
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
            if (typeof onInvoiceDeleted === 'function') {
                onInvoiceDeleted();
            }
        } catch (error) {
            statusMessage.value = error?.response?.data?.message || 'Failed to delete invoice.';
        } finally {
            isDeletingInvoice.value = false;
        }
    }

    function startEditingSessionDuration(session) {
        if (isFinalized.value || isSavingSessionDuration(session.id)) {
            return;
        }

        editingSessionDurationId.value = session.id;
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

    return {
        invoice,
        assignedSessions,
        clientTasks,
        availableSessions,
        expenses,
        summary,

        statusMessage,
        isFinalizing,
        isMarkingPaid,
        isSendingInvoiceEmail,
        isDeletingInvoice,
        isSavingDiscount,
        isSubmittingExpense,
        isSubmittingManualSession,
        isInlineTimerLoading,

        discountType,
        discountValue,

        manualDurationMinutes,
        manualStartedAt,
        selectedInlineProjectId,
        selectedInlineTaskId,
        selectedManualProjectId,
        selectedManualTaskId,

        inlineElapsedSeconds,
        inlineActiveSessionId,
        isInlineTimerRunning,
        isInlineTimerPaused,

        expenseName,
        expenseDescription,
        expenseAmount,

        isFinalized,
        isPaid,
        hasActiveClientTasks,
        clientProjects,
        assignedSessionsByProject,

        tasksForProject,
        displaySessionDuration,
        visibleSessionsForProject,
        hasMoreSessionsForProject,
        hiddenSessionsCountForProject,
        totalDurationSecondsForProject,
        isProjectSectionExpanded,
        toggleProjectSection,

        isBusy,
        isExpenseBusy,
        isSavingSessionDate,
        isSavingSessionDuration,
        isSavingSessionTask,
        isSavingSessionDetails,
        isEditingSessionDetails,
        isEditingSessionDuration,
        isSessionStopped,

        getSessionDateDraft,
        getSessionTaskDraft,
        getSessionProjectDraft,
        getSessionDurationDraft,
        sessionEditTasksForProject,
        setSessionProjectDraft,
        setSessionTaskDraft,
        setSessionDateDraft,
        setSessionDurationDraft,
        syncSessionTaskDraftForProject,

        deleteInvoice,
        finalizeInvoice,
        markInvoicePaid,
        emailInvoiceToClient,
        saveInvoiceDiscount,

        addExpense,
        removeExpense,

        createManualSession,
        runInlinePrimaryAction,
        stopInlineTimer,
        resumeStoppedSession,
        submitResumedSession,
        removeSession,
        startEditingSessionDetails,
        cancelEditingSessionDetails,
        saveSessionDetails,
        startEditingSessionDuration,
        cancelEditingSessionDuration,
        updateSessionDuration,
        updateSessionDate,
        updateSessionTask,
        addSession,
    };
}
