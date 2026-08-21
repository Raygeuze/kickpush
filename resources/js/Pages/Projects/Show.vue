<script setup>
import { computed, ref } from 'vue';
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
});

const selectedWorkerId = ref(
    props.selectedWorkerId === null || props.selectedWorkerId === undefined
        ? ''
        : String(props.selectedWorkerId)
);

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

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">First Tracked</p>
                            <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ formatDate(summary.first_tracked_at) }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Last Tracked</p>
                            <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ formatDate(summary.last_tracked_at) }}</p>
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
