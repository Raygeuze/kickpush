<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    clients: {
        type: Array,
        default: () => [],
    },
});

const clients = ref([]);
const selectedClientId = ref(null);
const activePanel = ref('overview');
const clientSearch = ref('');
const taskSearch = ref('');

const editingClientId = ref(null);
const editingProjectId = ref(null);
const editingTaskId = ref(null);

const isSaving = ref(false);
const statusMessage = ref('');
const statusKind = ref('success');

const clientForm = ref({
    name: '',
    email: '',
    currency: 'USD',
    hourly_rate: 0,
    notes: '',
});

const projectDraft = ref({
    name: '',
    description: '',
});

const taskDraft = ref({
    project_id: '',
    name: '',
    description: '',
});

const projectEditForm = ref({
    name: '',
    description: '',
});

const taskEditForm = ref({
    project_id: '',
    name: '',
    description: '',
});

function normalizeClient(client) {
    return {
        ...client,
        projects: Array.isArray(client.projects) ? client.projects : [],
        tasks: Array.isArray(client.tasks) ? client.tasks : [],
    };
}

function setStatus(message, kind = 'success') {
    statusMessage.value = message;
    statusKind.value = kind;
}

function initializeClients(payload) {
    clients.value = (payload || []).map((client) => normalizeClient(client));
}

function ensureClientSelection() {
    if (!clients.value.length) {
        selectedClientId.value = null;
        return;
    }

    const currentExists = clients.value.some((client) => client.id === selectedClientId.value);

    if (!currentExists) {
        selectedClientId.value = clients.value[0].id;
    }
}

function resetProjectDraft() {
    projectDraft.value = {
        name: '',
        description: '',
    };
}

function resetTaskDraft() {
    taskDraft.value = {
        project_id: selectedClientActiveProjects.value[0]?.id ? String(selectedClientActiveProjects.value[0].id) : '',
        name: '',
        description: '',
    };
}

function resetAllEditing() {
    editingClientId.value = null;
    editingProjectId.value = null;
    editingTaskId.value = null;
}

const filteredClients = computed(() => {
    const query = clientSearch.value.trim().toLowerCase();

    if (!query) {
        return clients.value;
    }

    return clients.value.filter((client) => {
        const fields = [client.name, client.email, client.currency].filter(Boolean).join(' ').toLowerCase();
        return fields.includes(query);
    });
});

const selectedClient = computed(() => {
    if (!selectedClientId.value) {
        return null;
    }

    return clients.value.find((client) => client.id === selectedClientId.value) || null;
});

const selectedClientProjects = computed(() => selectedClient.value?.projects || []);

const selectedClientActiveProjects = computed(() => {
    return selectedClientProjects.value.filter((project) => project?.is_active !== false);
});

const selectedClientTasks = computed(() => selectedClient.value?.tasks || []);

const filteredSelectedTasks = computed(() => {
    const query = taskSearch.value.trim().toLowerCase();

    if (!query) {
        return selectedClientTasks.value;
    }

    return selectedClientTasks.value.filter((task) => {
        const projectName = task?.project?.name || '';
        const fields = [task?.name, task?.description, projectName].filter(Boolean).join(' ').toLowerCase();
        return fields.includes(query);
    });
});

const dashboardCounts = computed(() => {
    const totalClients = clients.value.length;
    const totalProjects = clients.value.reduce((sum, client) => sum + (client.projects?.length || 0), 0);
    const totalTasks = clients.value.reduce((sum, client) => sum + (client.tasks?.length || 0), 0);
    const activeTasks = clients.value.reduce(
        (sum, client) => sum + (client.tasks || []).filter((task) => task.is_active !== false).length,
        0
    );

    return {
        totalClients,
        totalProjects,
        totalTasks,
        activeTasks,
    };
});

function taskCountForProject(projectId) {
    return selectedClientTasks.value.filter((task) => Number(task.project_id) === Number(projectId)).length;
}

function formatHourlyRate(value) {
    const amount = Number(value ?? 0);
    return Number.isFinite(amount) ? amount.toFixed(2) : '0.00';
}

function formatTaskOption(task) {
    if (!task) {
        return 'Unknown task';
    }

    if (task.project?.name) {
        return `${task.name} (${task.project.name})`;
    }

    return task.name;
}

function selectClient(clientId) {
    if (isSaving.value) {
        return;
    }

    selectedClientId.value = clientId;
    taskSearch.value = '';
    resetAllEditing();
    resetProjectDraft();
    resetTaskDraft();
}

function startClientEdit() {
    if (!selectedClient.value) {
        return;
    }

    editingClientId.value = selectedClient.value.id;
    clientForm.value = {
        name: selectedClient.value.name || '',
        email: selectedClient.value.email || '',
        currency: selectedClient.value.currency || 'USD',
        hourly_rate: Number(selectedClient.value.hourly_rate ?? 0),
        notes: selectedClient.value.notes || '',
    };
    setStatus('');
}

function cancelClientEdit() {
    editingClientId.value = null;
    clientForm.value = {
        name: '',
        email: '',
        currency: 'USD',
        hourly_rate: 0,
        notes: '',
    };
}

function startProjectEdit(project) {
    editingProjectId.value = project.id;
    projectEditForm.value = {
        name: project.name || '',
        description: project.description || '',
    };
}

function cancelProjectEdit() {
    editingProjectId.value = null;
    projectEditForm.value = {
        name: '',
        description: '',
    };
}

function startTaskEdit(task) {
    editingTaskId.value = task.id;
    taskEditForm.value = {
        project_id: task?.project_id ? String(task.project_id) : '',
        name: task.name || '',
        description: task.description || '',
    };
}

function cancelTaskEdit() {
    editingTaskId.value = null;
    taskEditForm.value = {
        project_id: '',
        name: '',
        description: '',
    };
}

async function refreshClients() {
    const response = await axios.get('/clients/list');
    initializeClients(response.data.clients || []);
    ensureClientSelection();
}

async function saveClientEdit() {
    if (isSaving.value || !selectedClient.value) {
        return;
    }

    if (!clientForm.value.name.trim()) {
        setStatus('Client name is required.', 'error');
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(`/clients/${selectedClient.value.id}`, {
            name: clientForm.value.name,
            email: clientForm.value.email || null,
            currency: (clientForm.value.currency || '').toUpperCase(),
            hourly_rate: Number(clientForm.value.hourly_rate || 0),
            notes: clientForm.value.notes || null,
        });

        await refreshClients();
        setStatus(response.data.message || 'Client updated.', 'success');
        cancelClientEdit();
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to update client.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function createProject() {
    if (isSaving.value || !selectedClient.value) {
        return;
    }

    if (!projectDraft.value.name?.trim()) {
        setStatus('Project name is required.', 'error');
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.post('/projects/create', {
            client_id: selectedClient.value.id,
            name: projectDraft.value.name,
            description: projectDraft.value.description || null,
        });

        await refreshClients();
        resetProjectDraft();
        setStatus(response.data.message || 'Project created.', 'success');
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to create project.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function saveProjectEdit(project) {
    if (isSaving.value) {
        return;
    }

    if (!projectEditForm.value.name?.trim()) {
        setStatus('Project name is required.', 'error');
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(`/projects/${project.id}`, {
            name: projectEditForm.value.name,
            description: projectEditForm.value.description || null,
        });

        await refreshClients();
        cancelProjectEdit();
        setStatus(response.data.message || 'Project updated.', 'success');
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to update project.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function toggleProjectActive(project) {
    if (isSaving.value) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(`/projects/${project.id}`, {
            is_active: !project.is_active,
        });

        await refreshClients();
        setStatus(response.data.message || 'Project updated.', 'success');
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to update project.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function deleteProject(project) {
    if (isSaving.value) {
        return;
    }

    if (!window.confirm(`Delete project "${project.name}"? This only works when the project has no tasks.`)) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.delete(`/projects/${project.id}`);
        await refreshClients();
        setStatus(response.data.message || 'Project deleted.', 'success');
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to delete project.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function createTask() {
    if (isSaving.value || !selectedClient.value) {
        return;
    }

    if (!taskDraft.value.project_id) {
        setStatus('Select a project before creating a task.', 'error');
        return;
    }

    if (!taskDraft.value.name?.trim()) {
        setStatus('Task name is required.', 'error');
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.post('/tasks/create', {
            client_id: selectedClient.value.id,
            project_id: Number(taskDraft.value.project_id),
            name: taskDraft.value.name,
            description: taskDraft.value.description || null,
        });

        await refreshClients();
        resetTaskDraft();
        setStatus(response.data.message || 'Task created.', 'success');
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to create task.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function saveTaskEdit(task) {
    if (isSaving.value) {
        return;
    }

    if (!taskEditForm.value.project_id) {
        setStatus('Select a project for this task.', 'error');
        return;
    }

    if (!taskEditForm.value.name?.trim()) {
        setStatus('Task name is required.', 'error');
        return;
    }

    const validProject = selectedClientActiveProjects.value.some(
        (project) => String(project.id) === taskEditForm.value.project_id
    );

    if (!validProject) {
        setStatus('Choose an active project for this task.', 'error');
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(`/tasks/${task.id}`, {
            project_id: Number(taskEditForm.value.project_id),
            name: taskEditForm.value.name,
            description: taskEditForm.value.description || null,
        });

        await refreshClients();
        cancelTaskEdit();
        setStatus(response.data.message || 'Task updated.', 'success');
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to update task.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function toggleTaskActive(task) {
    if (isSaving.value) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(`/tasks/${task.id}`, {
            is_active: !task.is_active,
        });

        await refreshClients();
        setStatus(response.data.message || 'Task updated.', 'success');
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to update task.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function setTaskDefault(task) {
    if (isSaving.value) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(`/tasks/${task.id}`, {
            is_default: !task.is_default,
        });

        await refreshClients();
        setStatus(response.data.message || 'Task default updated.', 'success');
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to update task default.', 'error');
    } finally {
        isSaving.value = false;
    }
}

async function deleteTask(task) {
    if (isSaving.value) {
        return;
    }

    if (!window.confirm(`Delete task "${task.name}"?`)) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.delete(`/tasks/${task.id}`);
        await refreshClients();
        setStatus(response.data.message || 'Task deleted.', 'success');
    } catch (error) {
        setStatus(error?.response?.data?.message || 'Failed to delete task.', 'error');
    } finally {
        isSaving.value = false;
    }
}

watch(
    selectedClient,
    () => {
        resetProjectDraft();
        resetTaskDraft();
        cancelProjectEdit();
        cancelTaskEdit();
    },
    { immediate: true }
);

initializeClients(props.clients || []);
ensureClientSelection();
</script>

<template>
    <AppLayout title="Clients">
        <div class="min-h-screen bg-gray-100 px-4 py-10 dark:bg-black">
            <div class="mx-auto w-full max-w-7xl space-y-6 rounded-2xl bg-white p-6 shadow-lg dark:bg-gray-900 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Client Workspace</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Manage one client at a time, with projects and tasks grouped in one place.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Link :href="route('dashboard')" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                            Back to Timer
                        </Link>
                        <Link
                            :href="route('clients.createPage')"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700"
                        >
                            Add Client
                        </Link>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Clients</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ dashboardCounts.totalClients }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Projects</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ dashboardCounts.totalProjects }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Tasks</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ dashboardCounts.totalTasks }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Active Tasks</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ dashboardCounts.activeTasks }}</p>
                    </div>
                </div>

                <p
                    v-if="statusMessage"
                    class="rounded-lg px-3 py-2 text-sm"
                    :class="statusKind === 'error' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200'"
                >
                    {{ statusMessage }}
                </p>

                <div v-if="clients.length === 0" class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                    No clients yet. Create your first client to start assigning projects and tasks.
                </div>

                <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <aside class="lg:col-span-4 xl:col-span-3">
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <input
                                v-model="clientSearch"
                                type="text"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                placeholder="Search clients"
                            />

                            <div class="mt-3 max-h-[36rem] space-y-2 overflow-y-auto pr-1">
                                <button
                                    v-for="client in filteredClients"
                                    :key="client.id"
                                    type="button"
                                    class="w-full rounded-lg border px-3 py-2 text-left transition"
                                    :class="selectedClientId === client.id ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-600 dark:bg-indigo-950/50' : 'border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/50'"
                                    @click="selectClient(client.id)"
                                >
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ client.name }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">{{ client.currency || 'USD' }} • {{ client.email || 'No email' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ client.projects.length }} projects • {{ client.tasks.length }} tasks
                                    </p>
                                </button>

                                <p v-if="filteredClients.length === 0" class="rounded-lg border border-dashed border-gray-300 p-3 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">
                                    No clients match your search.
                                </p>
                            </div>
                        </div>
                    </aside>

                    <section class="lg:col-span-8 xl:col-span-9">
                        <div v-if="selectedClient" class="space-y-5">
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedClient.name }}</h2>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ selectedClient.email || 'No email' }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">
                                            {{ selectedClient.currency || 'USD' }} • {{ formatHourlyRate(selectedClient.hourly_rate) }}/hr
                                        </p>
                                    </div>
                                    <button
                                        v-if="editingClientId !== selectedClient.id"
                                        type="button"
                                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                                        @click="startClientEdit"
                                    >
                                        Edit Client
                                    </button>
                                </div>

                                <div v-if="editingClientId !== selectedClient.id" class="mt-3">
                                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ selectedClient.notes || 'No notes added yet.' }}</p>
                                </div>

                                <div v-else class="mt-4 space-y-3">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <input
                                            v-model="clientForm.name"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                            placeholder="Client name"
                                        />
                                        <input
                                            v-model="clientForm.email"
                                            type="email"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                            placeholder="Client email"
                                        />
                                        <input
                                            v-model="clientForm.currency"
                                            type="text"
                                            maxlength="3"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm uppercase text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                            placeholder="Currency"
                                        />
                                        <input
                                            v-model="clientForm.hourly_rate"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                            placeholder="Hourly rate"
                                        />
                                    </div>
                                    <textarea
                                        v-model="clientForm.notes"
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        placeholder="Notes"
                                    />

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                                            :disabled="isSaving"
                                            @click="saveClientEdit"
                                        >
                                            {{ isSaving ? 'Saving...' : 'Save Changes' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 transition hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600"
                                            :disabled="isSaving"
                                            @click="cancelClientEdit"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                    :class="activePanel === 'overview' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200'"
                                    @click="activePanel = 'overview'"
                                >
                                    Overview
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                    :class="activePanel === 'projects' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200'"
                                    @click="activePanel = 'projects'"
                                >
                                    Projects
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                    :class="activePanel === 'tasks' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200'"
                                    @click="activePanel = 'tasks'"
                                >
                                    Tasks
                                </button>
                            </div>

                            <div v-if="activePanel === 'overview'" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Projects</p>
                                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ selectedClientProjects.length }}</p>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Active Projects</p>
                                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ selectedClientActiveProjects.length }}</p>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Tasks</p>
                                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ selectedClientTasks.length }}</p>
                                </div>
                            </div>

                            <div v-if="activePanel === 'projects'" class="space-y-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Projects</h3>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <input
                                        v-model="projectDraft.name"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        placeholder="Project name"
                                        :disabled="isSaving"
                                    />
                                    <input
                                        v-model="projectDraft.description"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        placeholder="Description (optional)"
                                        :disabled="isSaving"
                                    />
                                    <button
                                        type="button"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                        :disabled="isSaving"
                                        @click="createProject"
                                    >
                                        Create Project
                                    </button>
                                </div>

                                <div v-if="selectedClientProjects.length === 0" class="text-sm text-gray-600 dark:text-gray-300">
                                    No projects yet.
                                </div>

                                <div v-else class="space-y-2">
                                    <div
                                        v-for="project in selectedClientProjects"
                                        :key="project.id"
                                        class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                                    >
                                        <div v-if="editingProjectId !== project.id" class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    {{ project.name }}
                                                    <span v-if="project.is_active === false" class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">Archived</span>
                                                </p>
                                                <p class="text-xs text-gray-600 dark:text-gray-300">{{ project.description || 'No description' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ taskCountForProject(project.id) }} tasks</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <Link
                                                    :href="route('projects.show', project.id)"
                                                    class="rounded-lg bg-blue-600 px-2 py-1 text-xs text-white transition hover:bg-blue-700"
                                                >
                                                    View
                                                </Link>
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-indigo-600 px-2 py-1 text-xs text-white disabled:opacity-60"
                                                    :disabled="isSaving"
                                                    @click="startProjectEdit(project)"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-gray-200 px-2 py-1 text-xs text-gray-800 disabled:opacity-60 dark:bg-gray-700 dark:text-gray-200"
                                                    :disabled="isSaving"
                                                    @click="toggleProjectActive(project)"
                                                >
                                                    {{ project.is_active ? 'Archive' : 'Unarchive' }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-red-600 px-2 py-1 text-xs text-white disabled:opacity-60"
                                                    :disabled="isSaving"
                                                    @click="deleteProject(project)"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </div>

                                        <div v-else class="space-y-2">
                                            <input
                                                v-model="projectEditForm.name"
                                                type="text"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                placeholder="Project name"
                                                :disabled="isSaving"
                                            />
                                            <input
                                                v-model="projectEditForm.description"
                                                type="text"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                placeholder="Description"
                                                :disabled="isSaving"
                                            />
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-emerald-600 px-2 py-1 text-xs text-white disabled:opacity-60"
                                                    :disabled="isSaving"
                                                    @click="saveProjectEdit(project)"
                                                >
                                                    Save
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-gray-200 px-2 py-1 text-xs text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                                                    :disabled="isSaving"
                                                    @click="cancelProjectEdit"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="activePanel === 'tasks'" class="space-y-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Tasks</h3>
                                    <input
                                        v-model="taskSearch"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 sm:w-64 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        placeholder="Search tasks"
                                    />
                                </div>

                                <p class="text-xs text-gray-600 dark:text-gray-300">Tasks must belong to an active project.</p>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                    <select
                                        v-model="taskDraft.project_id"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        :disabled="isSaving"
                                    >
                                        <option value="">Select project</option>
                                        <option
                                            v-for="project in selectedClientActiveProjects"
                                            :key="project.id"
                                            :value="String(project.id)"
                                        >
                                            {{ project.name }}
                                        </option>
                                    </select>
                                    <input
                                        v-model="taskDraft.name"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        placeholder="Task name"
                                        :disabled="isSaving"
                                    />
                                    <input
                                        v-model="taskDraft.description"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        placeholder="Description (optional)"
                                        :disabled="isSaving"
                                    />
                                    <button
                                        type="button"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                        :disabled="isSaving || selectedClientActiveProjects.length === 0"
                                        @click="createTask"
                                    >
                                        Create Task
                                    </button>
                                </div>

                                <div v-if="filteredSelectedTasks.length === 0" class="text-sm text-gray-600 dark:text-gray-300">
                                    No tasks found.
                                </div>

                                <div v-else class="space-y-2">
                                    <div
                                        v-for="task in filteredSelectedTasks"
                                        :key="task.id"
                                        class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                                    >
                                        <div v-if="editingTaskId !== task.id" class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    {{ formatTaskOption(task) }}
                                                    <span v-if="task.is_default" class="ml-2 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">Default</span>
                                                    <span v-if="task.is_active === false" class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">Archived</span>
                                                </p>
                                                <p class="text-xs text-gray-600 dark:text-gray-300">{{ task.description || 'No description' }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-indigo-600 px-2 py-1 text-xs text-white disabled:opacity-60"
                                                    :disabled="isSaving"
                                                    @click="startTaskEdit(task)"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-gray-200 px-2 py-1 text-xs text-gray-800 disabled:opacity-60 dark:bg-gray-700 dark:text-gray-200"
                                                    :disabled="isSaving"
                                                    @click="toggleTaskActive(task)"
                                                >
                                                    {{ task.is_active ? 'Archive' : 'Unarchive' }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-amber-500 px-2 py-1 text-xs text-white disabled:opacity-60"
                                                    :disabled="isSaving || task.is_active === false"
                                                    @click="setTaskDefault(task)"
                                                >
                                                    {{ task.is_default ? 'Unset Default' : 'Set Default' }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-red-600 px-2 py-1 text-xs text-white disabled:opacity-60"
                                                    :disabled="isSaving"
                                                    @click="deleteTask(task)"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </div>

                                        <div v-else class="space-y-2">
                                            <select
                                                v-model="taskEditForm.project_id"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                :disabled="isSaving"
                                            >
                                                <option value="">Select project</option>
                                                <option
                                                    v-for="project in selectedClientActiveProjects"
                                                    :key="project.id"
                                                    :value="String(project.id)"
                                                >
                                                    {{ project.name }}
                                                </option>
                                            </select>
                                            <input
                                                v-model="taskEditForm.name"
                                                type="text"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                placeholder="Task name"
                                                :disabled="isSaving"
                                            />
                                            <input
                                                v-model="taskEditForm.description"
                                                type="text"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                placeholder="Description"
                                                :disabled="isSaving"
                                            />
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-emerald-600 px-2 py-1 text-xs text-white disabled:opacity-60"
                                                    :disabled="isSaving"
                                                    @click="saveTaskEdit(task)"
                                                >
                                                    Save
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-gray-200 px-2 py-1 text-xs text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                                                    :disabled="isSaving"
                                                    @click="cancelTaskEdit"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                            Select a client from the list to begin managing projects and tasks.
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
