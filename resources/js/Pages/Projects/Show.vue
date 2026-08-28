<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    taskSummaries: {
        type: Array,
        default: () => [],
    },
    recentSessions: {
        type: Array,
        default: () => [],
    },
    workers: {
        type: Array,
        default: () => [],
    },
    selectedWorkerId: {
        type: [Number, String, null],
        default: null,
    },
    projectNotes: {
        type: Array,
        default: () => [],
    },
});

const selectedWorkerId = ref(
    props.selectedWorkerId === null || props.selectedWorkerId === undefined
        ? ''
        : String(props.selectedWorkerId)
);
const projectNotesList = ref([...(props.projectNotes || [])]);
const noteBody = ref('');
const isSavingNote = ref(false);
const noteError = ref('');
const editingNoteId = ref(null);
const editingNoteBody = ref('');
const isUpdatingNoteId = ref(null);
const deletingNoteIds = ref([]);
const expandedNoteIds = ref([]);
const activeNotesTab = ref('team');

watch(
    () => props.projectNotes,
    (nextProjectNotes) => {
        projectNotesList.value = [...(nextProjectNotes || [])];

        if (activeNotesTab.value === 'private' && privateNotesCount.value === 0) {
            activeNotesTab.value = 'team';
        }
    }
);

const teamNotesCount = computed(() => projectNotesList.value.filter((note) => note.visibility !== 'private').length);
const privateNotesCount = computed(() => projectNotesList.value.filter((note) => note.visibility === 'private').length);
const visibleNotes = computed(() => {
    if (activeNotesTab.value === 'private') {
        return projectNotesList.value.filter((note) => note.visibility === 'private');
    }

    return projectNotesList.value.filter((note) => note.visibility !== 'private');
});
const activeTabLabel = computed(() => (activeNotesTab.value === 'private' ? 'My Private Notes' : 'Team Notes'));

const assignmentRate = computed(() => {
    const total = Number(props?.summary?.sessions_count || 0);

    if (total <= 0) {
        return 0;
    }

    const assigned = Number(props?.summary?.assigned_sessions_count || 0);
    return Math.round((assigned / total) * 100);
});

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
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
    const currencyCode = String(props?.project?.client?.currency || 'USD').toUpperCase();

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currencyCode,
        }).format(value);
    } catch (error) {
        return `${currencyCode} ${value.toFixed(2)}`;
    }
}

function statusLabel(session) {
    if (!session?.invoice_id) {
        return 'Unassigned';
    }

    return session?.invoice_status ? `Invoice ${session.invoice_id} (${session.invoice_status})` : `Invoice ${session.invoice_id}`;
}

function applyWorkerFilter() {
    router.get(route('projects.show', props.project.id), {
        user_id: selectedWorkerId.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

async function submitProjectNote() {
    if (isSavingNote.value) {
        return;
    }

    const body = String(noteBody.value || '').trim();

    if (!body) {
        noteError.value = 'Enter a note before posting.';
        return;
    }

    noteError.value = '';
    isSavingNote.value = true;

    try {
        const response = await axios.post(`/projects/${props.project.id}/notes`, {
            body,
            visibility: activeNotesTab.value,
        });

        if (response?.data?.note) {
            projectNotesList.value = [response.data.note, ...projectNotesList.value];
            noteBody.value = '';
        }
    } catch (error) {
        noteError.value = error?.response?.data?.message || 'Unable to add note right now.';
    } finally {
        isSavingNote.value = false;
    }
}

function startEditingNote(note) {
    editingNoteId.value = note.id;
    editingNoteBody.value = String(note.body || '');
    noteError.value = '';
}

function cancelEditingNote() {
    editingNoteId.value = null;
    editingNoteBody.value = '';
}

function isDeletingNote(noteId) {
    return deletingNoteIds.value.includes(noteId);
}

function isLongNote(note) {
    const body = String(note?.body || '');

    return body.length > 280;
}

function isNoteExpanded(noteId) {
    return expandedNoteIds.value.includes(noteId);
}

function toggleNoteExpanded(noteId) {
    if (isNoteExpanded(noteId)) {
        expandedNoteIds.value = expandedNoteIds.value.filter((id) => id !== noteId);
        return;
    }

    expandedNoteIds.value = [...expandedNoteIds.value, noteId];
}

function previewNoteBody(note) {
    const body = String(note?.body || '');

    if (!isLongNote(note) || isNoteExpanded(note.id)) {
        return body;
    }

    return `${body.slice(0, 280)}...`;
}

async function saveEditedNote(note) {
    if (isUpdatingNoteId.value !== null) {
        return;
    }

    const body = String(editingNoteBody.value || '').trim();

    if (!body) {
        noteError.value = 'Enter a note before saving.';
        return;
    }

    noteError.value = '';
    isUpdatingNoteId.value = note.id;

    try {
        const response = await axios.put(`/projects/${props.project.id}/notes/${note.id}`, {
            body,
        });
        const updated = response?.data?.note;

        if (updated) {
            projectNotesList.value = projectNotesList.value.map((entry) => (entry.id === updated.id ? updated : entry));
        }

        cancelEditingNote();
    } catch (error) {
        noteError.value = error?.response?.data?.message || 'Unable to save this note right now.';
    } finally {
        isUpdatingNoteId.value = null;
    }
}

async function deleteProjectNote(note) {
    if (isDeletingNote(note.id)) {
        return;
    }

    if (!window.confirm('Delete this note?')) {
        return;
    }

    deletingNoteIds.value.push(note.id);
    noteError.value = '';

    try {
        await axios.delete(`/projects/${props.project.id}/notes/${note.id}`);
        projectNotesList.value = projectNotesList.value.filter((entry) => entry.id !== note.id);

        if (editingNoteId.value === note.id) {
            cancelEditingNote();
        }
    } catch (error) {
        noteError.value = error?.response?.data?.message || 'Unable to delete this note right now.';
    } finally {
        deletingNoteIds.value = deletingNoteIds.value.filter((noteId) => noteId !== note.id);
    }
}
</script>

<template>
    <AppLayout :title="`Project ${project.name}`">
        <Head :title="`Project ${project.name}`" />

        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-6xl space-y-6">
                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">Project Analysis</p>
                            <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ project.name }}</h1>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Client: {{ project.client?.name || 'Unassigned client' }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Status: {{ project.is_active ? 'Active' : 'Archived' }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Hourly rate: {{ formatCurrency(project.hourly_rate || 0) }}/hr</p>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ project.description || 'No project description provided.' }}</p>
                        </div>

                        <div class="flex flex-col gap-3 w-full sm:w-auto">
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by worker</label>
                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="selectedWorkerId"
                                        class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm text-gray-900 dark:text-white"
                                    >
                                        <option value="">All workers</option>
                                        <option
                                            v-for="worker in workers"
                                            :key="worker.id"
                                            :value="String(worker.id)"
                                        >
                                            {{ worker.name }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-blue-700 transition"
                                        @click="applyWorkerFilter"
                                    >
                                        Apply
                                    </button>
                                </div>
                            </div>

                            <Link
                                :href="route('clients.index')"
                                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition text-center"
                            >
                                Back To Clients
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Project Totals</h2>

                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Tracked Time</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatDuration(summary.total_duration_seconds) }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Billable Total</p>
                            <p class="mt-2 text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ formatCurrency(summary.total_billable_amount) }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Sessions</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ summary.sessions_count }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ summary.running_sessions_count }} currently running/paused</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice Assignment</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ assignmentRate }}%</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ summary.assigned_sessions_count }} assigned / {{ summary.unassigned_sessions_count }} unassigned</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Tasks</p>
                            <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ summary.task_count }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ summary.active_task_count }} active, {{ summary.default_task_count }} default</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Average Session</p>
                            <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ formatDuration(summary.average_session_seconds) }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice Status</p>
                            <div class="mt-2 space-y-1 text-sm text-gray-800 dark:text-gray-200">
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-1 dark:border-gray-700">
                                    <span class="font-semibold">Total</span>
                                    <span class="font-semibold tabular-nums">{{ summary.project_invoice_count }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span>Paid</span>
                                    <span class="tabular-nums">{{ summary.project_paid_invoice_count }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span>Sent</span>
                                    <span class="tabular-nums">{{ summary.project_sent_invoice_count }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span>In Progress</span>
                                    <span class="tabular-nums">{{ summary.project_in_progress_invoice_count }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span>Overdue</span>
                                    <span class="tabular-nums">{{ summary.project_overdue_invoice_count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Project Notes</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ projectNotesList.length }} note(s)</p>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            :class="activeNotesTab === 'team' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-cyan-50 text-cyan-800 hover:bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-200 dark:hover:bg-cyan-900/50'"
                            @click="activeNotesTab = 'team'"
                        >
                            Team Notes ({{ teamNotesCount }})
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            :class="activeNotesTab === 'private' ? 'bg-amber-600 text-white shadow-sm' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-200 dark:hover:bg-amber-900/50'"
                            @click="activeNotesTab = 'private'"
                        >
                            My Private Notes ({{ privateNotesCount }})
                        </button>
                    </div>

                    <p v-if="activeNotesTab === 'private'" class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                        Only visible to you.
                    </p>

                    <p v-if="noteError" class="mt-3 text-sm text-red-700 dark:text-red-300">{{ noteError }}</p>

                    <p v-if="visibleNotes.length === 0" class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        No {{ activeTabLabel.toLowerCase() }} yet.
                    </p>

                    <div v-else class="mt-4 space-y-3">
                        <div
                            v-for="note in visibleNotes"
                            :key="note.id"
                            class="rounded-xl border p-4"
                            :class="note.visibility === 'private'
                                ? 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20'
                                : 'border-cyan-200 bg-cyan-50 dark:border-cyan-800 dark:bg-cyan-900/20'"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ note.user_name }}</p>
                                    <span
                                        v-if="note.visibility === 'private'"
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                    >
                                        Private
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(note.created_at) }}</p>
                                    <button
                                        v-if="note.can_edit"
                                        type="button"
                                        class="text-xs font-semibold hover:underline"
                                        :class="note.visibility === 'private' ? 'text-amber-700 dark:text-amber-300' : 'text-cyan-700 dark:text-cyan-300'"
                                        @click="startEditingNote(note)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="note.can_delete"
                                        type="button"
                                        class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline disabled:opacity-60"
                                        :disabled="isDeletingNote(note.id)"
                                        @click="deleteProjectNote(note)"
                                    >
                                        {{ isDeletingNote(note.id) ? 'Deleting...' : 'Delete' }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="editingNoteId === note.id" class="mt-2">
                                <textarea
                                    v-model="editingNoteBody"
                                    rows="3"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                />
                                <div class="mt-2 flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg bg-gray-200 dark:bg-gray-700 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                                        @click="cancelEditingNote"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition disabled:opacity-60"
                                        :disabled="isUpdatingNoteId === note.id"
                                        @click="saveEditedNote(note)"
                                    >
                                        {{ isUpdatingNoteId === note.id ? 'Saving...' : 'Save' }}
                                    </button>
                                </div>
                            </div>

                            <div v-else class="mt-2">
                                <p class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-200">{{ previewNoteBody(note) }}</p>
                                <button
                                    v-if="isLongNote(note)"
                                    type="button"
                                    class="mt-2 text-xs font-semibold hover:underline"
                                    :class="note.visibility === 'private' ? 'text-amber-700 dark:text-amber-300' : 'text-cyan-700 dark:text-cyan-300'"
                                    @click="toggleNoteExpanded(note.id)"
                                >
                                    {{ isNoteExpanded(note.id) ? 'Show less' : 'Show more' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-4 rounded-xl border p-4"
                        :class="activeNotesTab === 'private'
                            ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20'
                            : 'border-cyan-300 bg-cyan-50 dark:border-cyan-800 dark:bg-cyan-900/20'"
                    >
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Add Note</label>
                        <p
                            class="mt-1 text-xs font-semibold"
                            :class="activeNotesTab === 'private' ? 'text-amber-700 dark:text-amber-300' : 'text-cyan-700 dark:text-cyan-300'"
                        >
                            Posting to: {{ activeTabLabel }}
                        </p>
                        <textarea
                            v-model="noteBody"
                            rows="3"
                            class="mt-2 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            placeholder="Write a project update, decision, or context note..."
                        />
                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                                :class="activeNotesTab === 'private' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-cyan-600 hover:bg-cyan-700'"
                                :disabled="isSavingNote"
                                @click="submitProjectNote"
                            >
                                {{ isSavingNote ? 'Posting...' : 'Post Note' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Task Breakdown</h2>

                    <p v-if="taskSummaries.length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        No tasks on this project yet.
                    </p>

                    <div v-else class="mt-4 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/60">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Task</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Sessions</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Tracked Time</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Avg Session</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Billable</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Last Tracked</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="task in taskSummaries" :key="task.id">
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">
                                        <div class="font-semibold">{{ task.name }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-300">
                                            {{ task.is_active ? 'Active' : 'Archived' }}
                                            <span v-if="task.is_default"> • Default</span>
                                            <span v-if="task.description"> • {{ task.description }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ task.sessions_count }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatDuration(task.total_duration_seconds) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatDuration(task.average_session_seconds) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-300">{{ formatCurrency(task.billable_amount) }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ formatDate(task.last_tracked_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Recent Sessions</h2>

                    <p v-if="recentSessions.length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        No tracked sessions for this project yet.
                    </p>

                    <div v-else class="mt-4 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/60">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Worker</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Task</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Duration</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Billable</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Stopped</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="session in recentSessions" :key="session.id">
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ session.worker_name }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">{{ session.task_name }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatDuration(session.duration_seconds) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-300">{{ formatCurrency(session.billable_amount) }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ formatDateTime(session.stopped_at) }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ statusLabel(session) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
