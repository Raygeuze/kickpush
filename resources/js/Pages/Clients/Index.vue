<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    clients: {
        type: Array,
        default: () => [],
    },
});

const clients = ref(props.clients || []);
const editingClientId = ref(null);
const editingProjectId = ref(null);
const editingTaskId = ref(null);
const isSaving = ref(false);
const statusMessage = ref('');
const projectDrafts = ref({});
const taskDrafts = ref({});
const projectEditForm = ref({
    name: '',
    description: '',
});
const taskEditForm = ref({
    project_id: '',
    name: '',
    description: '',
});

const form = ref({
    name: '',
    email: '',
    currency: 'USD',
    hourly_rate: 0,
    notes: '',
});

function normalizeClient(client) {
    return {
        ...client,
        projects: Array.isArray(client.projects) ? client.projects : [],
        tasks: Array.isArray(client.tasks) ? client.tasks : [],
    };
}

function resetDrafts() {
    const projectState = {};
    const taskState = {};

    clients.value.forEach((client) => {
        projectState[client.id] = {
            name: '',
            description: '',
        };

        const firstActiveProject = (client.projects || []).find((project) => project.is_active !== false);

        taskState[client.id] = {
            project_id: firstActiveProject ? String(firstActiveProject.id) : '',
            name: '',
            description: '',
        };
    });

    projectDrafts.value = projectState;
    taskDrafts.value = taskState;
}

function initializeClients(payload) {
    clients.value = (payload || []).map((client) => normalizeClient(client));
    resetDrafts();
}

initializeClients(props.clients || []);

async function refreshClients() {
    const response = await axios.get('/clients/list');
    initializeClients(response.data.clients || []);
}

function startEdit(client) {
    editingClientId.value = client.id;
    form.value = {
        name: client.name || '',
        email: client.email || '',
        currency: client.currency || 'USD',
        hourly_rate: Number(client.hourly_rate ?? 0),
        notes: client.notes || '',
    };
    statusMessage.value = '';
}

function cancelEdit() {
    editingClientId.value = null;
    form.value = {
        name: '',
        email: '',
        currency: 'USD',
        hourly_rate: 0,
        notes: '',
    };
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

function activeProjectsForClient(client) {
    return (client?.projects || []).filter((project) => project?.is_active !== false);
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

async function saveProjectEdit(project) {
    if (isSaving.value) {
        return;
    }

    if (!projectEditForm.value.name?.trim()) {
        statusMessage.value = 'Project name is required.';
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(`/projects/${project.id}`, {
            name: projectEditForm.value.name,
            description: projectEditForm.value.description || null,
        });

        await refreshClients();
        statusMessage.value = response.data.message || 'Project updated.';
        cancelProjectEdit();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update project.';
    } finally {
        isSaving.value = false;
    }
}

async function saveTaskEdit(task, client) {
    if (isSaving.value) {
        return;
    }

    if (!taskEditForm.value.project_id) {
        statusMessage.value = 'Select a project for this task.';
        return;
    }

    if (!taskEditForm.value.name?.trim()) {
        statusMessage.value = 'Task name is required.';
        return;
    }

    const validProject = activeProjectsForClient(client).some(
        (project) => String(project.id) === taskEditForm.value.project_id
    );

    if (!validProject) {
        statusMessage.value = 'Choose an active project for this task.';
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
        statusMessage.value = response.data.message || 'Task updated.';
        cancelTaskEdit();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update task.';
    } finally {
        isSaving.value = false;
    }
}

async function saveEdit(clientId) {
    if (isSaving.value) {
        return;
    }

    if (!form.value.name.trim()) {
        statusMessage.value = 'Client name is required.';
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(`/clients/${clientId}`, {
            name: form.value.name,
            email: form.value.email || null,
            currency: (form.value.currency || '').toUpperCase(),
            hourly_rate: Number(form.value.hourly_rate || 0),
            notes: form.value.notes || null,
        });

        await refreshClients();
        statusMessage.value = response.data.message || 'Client updated.';
        cancelEdit();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update client.';
    } finally {
        isSaving.value = false;
    }
}

async function createProject(clientId) {
    if (isSaving.value) {
        return;
    }

    const draft = projectDrafts.value[clientId] || { name: '', description: '' };

    if (!draft.name?.trim()) {
        statusMessage.value = 'Project name is required.';
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.post('/projects/create', {
            client_id: clientId,
            name: draft.name,
            description: draft.description || null,
        });

        await refreshClients();
        statusMessage.value = response.data.message || 'Project created.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to create project.';
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
        statusMessage.value = response.data.message || 'Project updated.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update project.';
    } finally {
        isSaving.value = false;
    }
}

async function deleteProject(project) {
    if (isSaving.value) {
        return;
    }

    if (!window.confirm(`Delete project "${project.name}" and all of its tasks?`)) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.delete(`/projects/${project.id}`);

        await refreshClients();
        statusMessage.value = response.data.message || 'Project deleted.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to delete project.';
    } finally {
        isSaving.value = false;
    }
}

async function createTask(clientId) {
    if (isSaving.value) {
        return;
    }

    const draft = taskDrafts.value[clientId] || { project_id: '', name: '', description: '' };

    if (!draft.project_id) {
        statusMessage.value = 'Select a project before creating a task.';
        return;
    }

    if (!draft.name?.trim()) {
        statusMessage.value = 'Task name is required.';
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.post('/tasks/create', {
            client_id: clientId,
            project_id: Number(draft.project_id),
            name: draft.name,
            description: draft.description || null,
        });

        await refreshClients();
        statusMessage.value = response.data.message || 'Task created.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to create task.';
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
        statusMessage.value = response.data.message || 'Task updated.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update task.';
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
        statusMessage.value = response.data.message || 'Task default updated.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update task default.';
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
        statusMessage.value = response.data.message || 'Task deleted.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to delete task.';
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <AppLayout title="Clients">
        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-6xl rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Clients</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Manage clients, then create projects and tasks under each client.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Link :href="route('dashboard')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                            Back to Timer
                        </Link>
                        <Link :href="route('clients.createPage')" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                            Add Client
                        </Link>
                    </div>
                </div>

                <p v-if="statusMessage" class="mt-4 text-sm text-gray-700 dark:text-gray-300">
                    {{ statusMessage }}
                </p>

                <div v-if="clients.length === 0" class="mt-6 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-6 text-sm text-gray-600 dark:text-gray-300">
                    No clients yet. Create your first client to start assigning projects and tasks.
                </div>

                <div v-else class="mt-6 space-y-5">
                    <div
                        v-for="client in clients"
                        :key="client.id"
                        class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                    >
                        <div v-if="editingClientId !== client.id" class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-base font-semibold text-gray-900 dark:text-white">{{ client.name }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ client.email || 'No email' }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">Currency: {{ client.currency || 'USD' }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">Hourly rate: {{ formatHourlyRate(client.hourly_rate) }}/hr</p>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ client.notes || 'No notes' }}</p>
                            </div>

                            <button
                                type="button"
                                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700 transition"
                                @click="startEdit(client)"
                            >
                                Edit Client
                            </button>
                        </div>

                        <div v-else class="space-y-3">
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                placeholder="Client name"
                            />
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                placeholder="Client email"
                            />
                            <input
                                v-model="form.currency"
                                type="text"
                                maxlength="3"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm uppercase text-gray-900 dark:text-white"
                                placeholder="Currency code"
                            />
                            <input
                                v-model="form.hourly_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                placeholder="Hourly rate"
                            />
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                placeholder="Client notes"
                            />

                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition disabled:opacity-60"
                                    :disabled="isSaving"
                                    @click="saveEdit(client.id)"
                                >
                                    {{ isSaving ? 'Saving...' : 'Save Changes' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 transition"
                                    @click="cancelEdit"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <div class="mt-5 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Projects</p>

                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <input
                                    v-model="projectDrafts[client.id].name"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                    placeholder="Project name"
                                    :disabled="isSaving"
                                />
                                <input
                                    v-model="projectDrafts[client.id].description"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                    placeholder="Description (optional)"
                                    :disabled="isSaving"
                                />
                                <button
                                    type="button"
                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                                    :disabled="isSaving"
                                    @click="createProject(client.id)"
                                >
                                    Create Project
                                </button>
                            </div>

                            <div v-if="!client.projects.length" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                No projects yet.
                            </div>

                            <div v-else class="mt-3 space-y-2">
                                <div v-for="project in client.projects" :key="project.id" class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                    <div v-if="editingProjectId !== project.id" class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ project.name }}</p>
                                            <p class="text-xs text-gray-600 dark:text-gray-300">{{ project.description || 'No description' }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Link
                                                :href="route('projects.show', project.id)"
                                                class="rounded-lg bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700 transition"
                                            >
                                                View
                                            </Link>
                                            <button type="button" class="rounded-lg bg-indigo-600 px-2 py-1 text-xs text-white disabled:opacity-60" :disabled="isSaving" @click="startProjectEdit(project)">
                                                Edit
                                            </button>
                                            <button type="button" class="rounded-lg bg-gray-200 dark:bg-gray-700 px-2 py-1 text-xs text-gray-800 dark:text-gray-200 disabled:opacity-60" :disabled="isSaving" @click="toggleProjectActive(project)">
                                                {{ project.is_active ? 'Archive' : 'Unarchive' }}
                                            </button>
                                            <button type="button" class="rounded-lg bg-red-600 px-2 py-1 text-xs text-white disabled:opacity-60" :disabled="isSaving" @click="deleteProject(project)">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <input
                                            v-model="projectEditForm.name"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-900 dark:text-white"
                                            placeholder="Project name"
                                            :disabled="isSaving"
                                        />
                                        <input
                                            v-model="projectEditForm.description"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-900 dark:text-white"
                                            placeholder="Description"
                                            :disabled="isSaving"
                                        />
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="rounded-lg bg-emerald-600 px-2 py-1 text-xs text-white disabled:opacity-60" :disabled="isSaving" @click="saveProjectEdit(project)">
                                                Save
                                            </button>
                                            <button type="button" class="rounded-lg bg-gray-200 dark:bg-gray-700 px-2 py-1 text-xs text-gray-800 dark:text-gray-200" :disabled="isSaving" @click="cancelProjectEdit">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Tasks</p>
                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                Tasks must belong to a project.
                            </p>

                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
                                <select
                                    v-model="taskDrafts[client.id].project_id"
                                    class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                    :disabled="isSaving"
                                >
                                    <option value="">Select project</option>
                                    <option
                                        v-for="project in activeProjectsForClient(client)"
                                        :key="project.id"
                                        :value="String(project.id)"
                                    >
                                        {{ project.name }}
                                    </option>
                                </select>
                                <input
                                    v-model="taskDrafts[client.id].name"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                    placeholder="Task name"
                                    :disabled="isSaving"
                                />
                                <input
                                    v-model="taskDrafts[client.id].description"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                    placeholder="Description (optional)"
                                    :disabled="isSaving"
                                />
                                <button
                                    type="button"
                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                                    :disabled="isSaving || activeProjectsForClient(client).length === 0"
                                    @click="createTask(client.id)"
                                >
                                    Create Task
                                </button>
                            </div>

                            <div v-if="!client.tasks.length" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                No tasks yet.
                            </div>

                            <div v-else class="mt-3 space-y-2">
                                <div v-for="task in client.tasks" :key="task.id" class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                    <div v-if="editingTaskId !== task.id" class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatTaskOption(task) }}</p>
                                            <p class="text-xs text-gray-600 dark:text-gray-300">{{ task.description || 'No description' }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="rounded-lg bg-indigo-600 px-2 py-1 text-xs text-white disabled:opacity-60" :disabled="isSaving" @click="startTaskEdit(task)">
                                                Edit
                                            </button>
                                            <button type="button" class="rounded-lg bg-gray-200 dark:bg-gray-700 px-2 py-1 text-xs text-gray-800 dark:text-gray-200 disabled:opacity-60" :disabled="isSaving" @click="toggleTaskActive(task)">
                                                {{ task.is_active ? 'Archive' : 'Unarchive' }}
                                            </button>
                                            <button type="button" class="rounded-lg bg-amber-500 px-2 py-1 text-xs text-white disabled:opacity-60" :disabled="isSaving || task.is_active === false" @click="setTaskDefault(task)">
                                                {{ task.is_default ? 'Unset Default' : 'Set Default' }}
                                            </button>
                                            <button type="button" class="rounded-lg bg-red-600 px-2 py-1 text-xs text-white disabled:opacity-60" :disabled="isSaving" @click="deleteTask(task)">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <select
                                            v-model="taskEditForm.project_id"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-900 dark:text-white"
                                            :disabled="isSaving"
                                        >
                                            <option value="">Select project</option>
                                            <option
                                                v-for="project in activeProjectsForClient(client)"
                                                :key="project.id"
                                                :value="String(project.id)"
                                            >
                                                {{ project.name }}
                                            </option>
                                        </select>
                                        <input
                                            v-model="taskEditForm.name"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-900 dark:text-white"
                                            placeholder="Task name"
                                            :disabled="isSaving"
                                        />
                                        <input
                                            v-model="taskEditForm.description"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1 text-xs text-gray-900 dark:text-white"
                                            placeholder="Description"
                                            :disabled="isSaving"
                                        />
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="rounded-lg bg-emerald-600 px-2 py-1 text-xs text-white disabled:opacity-60" :disabled="isSaving" @click="saveTaskEdit(task, client)">
                                                Save
                                            </button>
                                            <button type="button" class="rounded-lg bg-gray-200 dark:bg-gray-700 px-2 py-1 text-xs text-gray-800 dark:text-gray-200" :disabled="isSaving" @click="cancelTaskEdit">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
