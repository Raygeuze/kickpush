<script setup>
defineProps({
    state: {
        type: Object,
        required: true,
    },
    formatDuration: {
        type: Function,
        required: true,
    },
    tasksForProject: {
        type: Function,
        required: true,
    },
    formatTaskOption: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'update:selectedInlineProjectId',
    'update:selectedInlineTaskId',
    'update:selectedManualProjectId',
    'update:selectedManualTaskId',
    'update:manualDurationMinutes',
    'update:manualStartedAt',
    'runInlinePrimaryAction',
    'stopInlineTimer',
    'deleteInlineTimer',
    'createManualSession',
]);
</script>

<template>
    <div>
        <div class="mt-4 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
            <div class="grid gap-3 lg:grid-cols-[minmax(110px,0.6fr)_minmax(180px,1.2fr)_minmax(180px,1.2fr)_auto_auto] lg:items-end">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Timer</p>
                    <p class="mt-1 font-mono text-2xl font-bold text-gray-900 dark:text-white">
                        {{ formatDuration(state.inlineElapsedSeconds) }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                        {{ state.isInlineTimerRunning ? 'Recording' : state.isInlineTimerActive ? 'Open session' : 'Idle' }}
                    </p>
                </div>

                <label class="text-xs font-medium text-gray-700 dark:text-gray-200">
                    Project
                    <select
                        :value="state.selectedInlineProjectId"
                        class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        :disabled="state.isFinalized || state.isInlineTimerLoading || state.isInlineTimerRunning || state.isInlineTimerActive || !state.clientProjects.length"
                        @change="emit('update:selectedInlineProjectId', $event.target.value)"
                    >
                        <option value="">Select project</option>
                        <option v-for="project in state.clientProjects" :key="project.id" :value="String(project.id)">
                            {{ project.name }}
                        </option>
                    </select>
                </label>

                <label class="text-xs font-medium text-gray-700 dark:text-gray-200">
                    Task
                    <select
                        :value="state.selectedInlineTaskId"
                        class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        :disabled="state.isFinalized || state.isInlineTimerLoading || state.isInlineTimerRunning || state.isInlineTimerActive || !state.selectedInlineProjectId"
                        @change="emit('update:selectedInlineTaskId', $event.target.value)"
                    >
                        <option value="">Use project default task</option>
                        <option v-for="task in tasksForProject(state.selectedInlineProjectId)" :key="task.id" :value="String(task.id)">
                            {{ formatTaskOption(task) }}
                        </option>
                    </select>
                </label>

                <button
                    type="button"
                    class="rounded-xl px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                    :class="state.isInlineTimerActive ? 'bg-gray-700 hover:bg-gray-800 dark:bg-gray-600 dark:hover:bg-gray-500' : 'bg-green-600 hover:bg-green-700'"
                    :disabled="state.isFinalized || state.isInlineTimerLoading || state.isInlineTimerDeleting || (!state.isInlineTimerActive && !state.hasActiveClientTasks)"
                    @click="emit('runInlinePrimaryAction')"
                >
                    {{ state.isInlineTimerLoading ? 'Working...' : (state.isInlineTimerActive ? 'Stop Timer' : 'Start Timer') }}
                </button>

                <button
                    v-if="state.inlineActiveSessionId"
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-red-600 text-white transition hover:bg-red-700 disabled:opacity-60"
                    :disabled="state.isFinalized || state.isInlineTimerLoading || state.isInlineTimerDeleting"
                    title="Delete timer session"
                    aria-label="Delete timer session"
                    @click="emit('deleteInlineTimer')"
                >
                    <span v-if="state.isInlineTimerDeleting" class="text-[10px] font-semibold">...</span>
                    <svg v-else viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 6h18" />
                        <path d="M8 6V4h8v2" />
                        <path d="M19 6l-1 14H6L5 6" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                    </svg>
                </button>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                <p v-if="state.inlineActiveSessionId" class="text-gray-500 dark:text-gray-400">Session #{{ state.inlineActiveSessionId }}</p>
                <p v-if="!state.hasActiveClientTasks" class="text-amber-700 dark:text-amber-300">Create an active task for this client before starting invoice timer sessions.</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-5">
            <input
                :value="state.manualDurationMinutes"
                type="number"
                min="1"
                step="1"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                placeholder="Duration (minutes)"
                :disabled="state.isFinalized || state.isSubmittingManualSession"
                @input="emit('update:manualDurationMinutes', $event.target.value)"
            />
            <input
                :value="state.manualStartedAt"
                type="date"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                :disabled="state.isFinalized || state.isSubmittingManualSession"
                @input="emit('update:manualStartedAt', $event.target.value)"
            />
            <select
                :value="state.selectedManualProjectId"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                :disabled="state.isFinalized || state.isSubmittingManualSession || !state.clientProjects.length"
                @change="emit('update:selectedManualProjectId', $event.target.value)"
            >
                <option value="">Select project</option>
                <option
                    v-for="project in state.clientProjects"
                    :key="project.id"
                    :value="String(project.id)"
                >
                    {{ project.name }}
                </option>
            </select>
            <select
                :value="state.selectedManualTaskId"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                :disabled="state.isFinalized || state.isSubmittingManualSession || !state.selectedManualProjectId"
                @change="emit('update:selectedManualTaskId', $event.target.value)"
            >
                <option value="">Use project default task</option>
                <option
                    v-for="task in tasksForProject(state.selectedManualProjectId)"
                    :key="task.id"
                    :value="String(task.id)"
                >
                    {{ formatTaskOption(task) }}
                </option>
            </select>
            <button
                type="button"
                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                :disabled="state.isFinalized || state.isSubmittingManualSession || !state.selectedManualProjectId"
                @click="emit('createManualSession')"
            >
                {{ state.isSubmittingManualSession ? 'Adding Session...' : 'Add Session' }}
            </button>
        </div>

        <p v-if="!state.hasActiveClientTasks" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
            Manual sessions require at least one active task on this client.
        </p>
    </div>
</template>
