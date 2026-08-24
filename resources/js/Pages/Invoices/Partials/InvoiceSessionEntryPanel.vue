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
    'createManualSession',
]);
</script>

<template>
    <div>
        <div class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Timer</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {{ formatDuration(state.inlineElapsedSeconds) }}
            </p>
            <div class="mt-2">
                <label class="text-xs font-medium text-gray-700 dark:text-gray-200">Project for new timer sessions</label>
                <select
                    :value="state.selectedInlineProjectId"
                    class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    :disabled="state.isFinalized || state.isInlineTimerLoading || state.isInlineTimerRunning || state.isInlineTimerPaused || !state.clientProjects.length"
                    @change="emit('update:selectedInlineProjectId', $event.target.value)"
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
            </div>
            <div class="mt-2">
                <label class="text-xs font-medium text-gray-700 dark:text-gray-200">Task for new timer sessions</label>
                <select
                    :value="state.selectedInlineTaskId"
                    class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    :disabled="state.isFinalized || state.isInlineTimerLoading || state.isInlineTimerRunning || state.isInlineTimerPaused || !state.selectedInlineProjectId"
                    @change="emit('update:selectedInlineTaskId', $event.target.value)"
                >
                    <option value="">Use project default task</option>
                    <option
                        v-for="task in tasksForProject(state.selectedInlineProjectId)"
                        :key="task.id"
                        :value="String(task.id)"
                    >
                        {{ formatTaskOption(task) }}
                    </option>
                </select>
            </div>
            <p v-if="state.inlineActiveSessionId" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Session #{{ state.inlineActiveSessionId }}
            </p>
            <button
                type="button"
                class="mt-3 rounded-xl px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                :class="state.isInlineTimerRunning ? 'bg-amber-600 hover:bg-amber-700' : state.isInlineTimerPaused ? 'bg-blue-600 hover:bg-blue-700' : 'bg-green-600 hover:bg-green-700'"
                :disabled="state.isFinalized || state.isInlineTimerLoading"
                @click="emit('runInlinePrimaryAction')"
            >
                {{ state.isInlineTimerLoading ? 'Working...' : (state.isInlineTimerRunning ? 'Pause Timer' : state.isInlineTimerPaused ? 'Resume Timer' : 'Start Timer') }}
            </button>

            <button
                v-if="state.isInlineTimerRunning || state.isInlineTimerPaused"
                type="button"
                class="ml-3 mt-3 rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-700 disabled:opacity-60"
                :disabled="state.isFinalized || state.isInlineTimerLoading"
                @click="emit('stopInlineTimer')"
            >
                {{ state.isInlineTimerLoading ? 'Working...' : 'Submit' }}
            </button>

            <p v-if="!state.hasActiveClientTasks" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                Create an active task for this client before starting invoice timer sessions.
            </p>

            <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                {{ state.isInlineTimerRunning ? 'Recording in progress' : state.isInlineTimerPaused ? 'Paused' : 'Not recording' }}
            </p>
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
