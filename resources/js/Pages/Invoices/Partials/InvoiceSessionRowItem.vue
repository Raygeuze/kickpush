<script setup>
defineProps({
    session: {
        type: Object,
        required: true,
    },
    controller: {
        type: Object,
        required: true,
    },
    formatters: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatters.formatSessionHeaderDate(session.started_at || session.created_at) }}</p>
                <template v-if="!controller.isEditingSessionDetails(session.id)">
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <p class="text-xs text-gray-600 dark:text-gray-300">{{ session.task?.name || 'General' }}<span v-if="session.task?.project"> in {{ session.task.project.name }}</span></p>
                        <button
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-gray-300 text-gray-600 transition hover:bg-gray-100 disabled:opacity-60 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                            :disabled="controller.isFinalized || controller.isSavingSessionDetails(session.id)"
                            title="Edit session details"
                            aria-label="Edit session details"
                            @click="controller.startEditingSessionDetails(session)"
                        >
                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Recorded by {{ session.user?.name || 'Unknown user' }}
                    </p>
                </template>

                <template v-else>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div>
                            <label class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Project</label>
                            <select
                                :value="controller.getSessionProjectDraft(session)"
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                :disabled="controller.isFinalized || controller.isSavingSessionDetails(session.id)"
                                @change="controller.setSessionProjectDraft(session.id, $event.target.value); controller.syncSessionTaskDraftForProject(session)"
                            >
                                <option value="">Select project</option>
                                <option
                                    v-for="project in controller.clientProjects"
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
                                :value="controller.getSessionTaskDraft(session)"
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                :disabled="controller.isFinalized || controller.isSavingSessionDetails(session.id) || !controller.getSessionProjectDraft(session)"
                                @change="controller.setSessionTaskDraft(session.id, $event.target.value)"
                            >
                                <option value="">Select task</option>
                                <option
                                    v-for="task in controller.sessionEditTasksForProject(session)"
                                    :key="task.id"
                                    :value="String(task.id)"
                                >
                                    {{ formatters.formatTaskOption(task) }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Date</label>
                            <input
                                :value="controller.getSessionDateDraft(session)"
                                type="date"
                                class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                :max="'9999-12-31'"
                                :disabled="controller.isFinalized || controller.isSavingSessionDetails(session.id) || !controller.isSessionStopped(session)"
                                @input="controller.setSessionDateDraft(session.id, $event.target.value)"
                            />
                        </div>
                    </div>
                    <p v-if="!controller.isSessionStopped(session)" class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                        Date can only be changed after the session is stopped.
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                            :disabled="controller.isFinalized || controller.isSavingSessionDetails(session.id)"
                            @click="controller.saveSessionDetails(session)"
                        >
                            {{ controller.isSavingSessionDetails(session.id) ? 'Saving...' : 'Save Details' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-100 disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                            :disabled="controller.isSavingSessionDetails(session.id)"
                            @click="controller.cancelEditingSessionDetails(session)"
                        >
                            Cancel
                        </button>
                    </div>
                </template>
            </div>

            <div class="flex items-center gap-3">
                <template v-if="!controller.isEditingSessionDuration(session.id)">
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ controller.displaySessionDuration(session) }}</p>
                    <button
                        v-if="controller.isSessionStopped(session)"
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-300 text-gray-600 transition hover:bg-gray-100 disabled:opacity-60 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                        :disabled="controller.isFinalized || controller.isSavingSessionDuration(session.id)"
                        title="Edit session duration"
                        aria-label="Edit session duration"
                        @click="controller.startEditingSessionDuration(session)"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                        </svg>
                    </button>
                </template>

                <template v-else>
                    <input
                        :value="controller.getSessionDurationDraft(session)"
                        type="text"
                        inputmode="numeric"
                        pattern="^\\d+:[0-5]\\d:[0-5]\\d$"
                        placeholder="00:00:00"
                        class="w-28 rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        :disabled="controller.isFinalized || controller.isSavingSessionDuration(session.id)"
                        @input="controller.setSessionDurationDraft(session.id, $event.target.value)"
                    />
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-2 py-1 text-xs font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="controller.isFinalized || controller.isSavingSessionDuration(session.id)"
                        @click="controller.updateSessionDuration(session)"
                    >
                        {{ controller.isSavingSessionDuration(session.id) ? 'Saving...' : 'Save' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 transition hover:bg-gray-100 disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                        :disabled="controller.isSavingSessionDuration(session.id)"
                        @click="controller.cancelEditingSessionDuration(session)"
                    >
                        Cancel
                    </button>
                </template>

                <button
                    v-if="controller.isSessionStopped(session)"
                    type="button"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="controller.isFinalized || controller.isBusy(session.id)"
                    @click="controller.resumeStoppedSession(session)"
                >
                    {{ controller.isBusy(session.id) ? 'Working...' : 'Resume' }}
                </button>

                <button
                    v-if="!controller.isSessionStopped(session)"
                    type="button"
                    class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-green-700 disabled:opacity-60"
                    :disabled="controller.isFinalized || controller.isInlineTimerLoading || controller.isBusy(session.id) || controller.inlineActiveSessionId !== session.id"
                    @click="controller.submitResumedSession(session)"
                >
                    {{ controller.isInlineTimerLoading || controller.isBusy(session.id) ? 'Working...' : 'Submit' }}
                </button>

                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full text-white transition disabled:opacity-60"
                    :class="controller.isFinalized ? 'cursor-not-allowed bg-gray-500' : 'bg-red-600 hover:bg-red-700'"
                    :disabled="controller.isFinalized || controller.isBusy(session.id)"
                    title="Remove session"
                    aria-label="Remove session"
                    @click="controller.removeSession(session.id)"
                >
                    <span v-if="controller.isBusy(session.id)" class="text-[10px] font-semibold">...</span>
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
</template>
