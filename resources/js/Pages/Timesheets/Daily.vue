<script setup>
import SessionRow from './Partials/SessionRow.vue';

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    page: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="space-y-5">
        <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-950">
            <div class="mb-2 flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-gray-400">
                <span>Week of {{ page.weekStart }}</span>
                <span>{{ state.formatDuration(state.weekDuration) }} logged this week</span>
            </div>
            <div class="grid grid-cols-5 gap-1.5 sm:grid-cols-7">
                <button
                    v-for="day in state.visibleDays"
                    :key="day.key"
                    type="button"
                    class="flex flex-col items-center rounded-lg p-2.5 text-center transition"
                    :class="[
                        state.activeDayKey === day.key
                            ? 'border-2 border-emerald-500 bg-emerald-50/80 text-gray-950 shadow-sm dark:border-emerald-400 dark:bg-emerald-950/40 dark:text-white'
                            : 'border-2 border-transparent bg-gray-50 text-gray-800 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800',
                        day.is_today && state.activeDayKey !== day.key ? 'ring-1 ring-emerald-500/50' : '',
                    ]"
                    @click="state.selectDay(day.key)"
                >
                    <span class="text-xs font-medium" :class="state.activeDayKey === day.key ? 'font-semibold text-emerald-700 dark:text-emerald-300' : 'opacity-75'">
                        {{ day.short_label }}
                    </span>
                    <span class="text-sm font-bold">{{ day.date_label }}</span>
                    <span class="mt-1 font-mono text-xs font-semibold" :class="state.activeDayKey === day.key ? 'text-emerald-700 dark:text-emerald-300' : 'text-emerald-600 dark:text-emerald-400'">
                        {{ state.formatDuration(state.dayDuration(day.key)) }}
                    </span>
                    <span class="mt-0.5 text-[10px]" :class="state.activeDayKey === day.key ? 'text-emerald-800/80 dark:text-emerald-200/80' : 'opacity-60'">
                        {{ state.daySessionsCount(day.key) }} session{{ state.daySessionsCount(day.key) === 1 ? '' : 's' }}
                    </span>
                </button>
            </div>
        </section>

        <section v-if="page.canCreateSessions" class="relative rounded-xl border border-emerald-300 bg-emerald-50/50 p-5 dark:border-emerald-800 dark:bg-emerald-950/20">
            <p class="text-xs text-gray-500 dark:text-gray-400 sm:absolute sm:right-5 sm:top-4">
                Timer runs from now and is recorded on {{ state.activeDay.full_label }}.
            </p>
            <h3 class="text-sm font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Start new timer</h3>

            <form class="mt-3 grid gap-3 sm:grid-cols-3" @submit.prevent="state.startDayTimer">
                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    Project
                    <select v-model="state.dayStartForm.project_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Select project</option>
                        <optgroup v-for="group in state.projectsByClient" :key="group.clientName" :label="group.clientName">
                            <option v-for="project in group.projects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                        </optgroup>
                    </select>
                </label>

                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    Task
                    <select v-model="state.dayStartForm.task_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" :disabled="!state.dayStartForm.project_id">
                        <option value="">Select task</option>
                        <option v-for="task in state.dayProjectTasks" :key="task.id" :value="String(task.id)">{{ task.name }}</option>
                    </select>
                </label>

                <div class="flex items-end">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="state.startingDayTimer || state.hasActiveSession || !state.dayStartForm.task_id"
                        :title="state.hasActiveSession ? 'Stop your active timer before starting another' : 'Start recording time'"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                        <span>{{ state.startingDayTimer ? 'Starting...' : 'Start timer' }}</span>
                    </button>
                </div>
            </form>
        </section>

        <p v-if="state.formErrors" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ state.formErrors }}</p>

        <div class="space-y-4">
            <article v-for="group in state.currentDayProjectGroups" :key="group.key" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-950">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-bold text-gray-950 dark:text-white">{{ group.projectName }}</h3>
                        <p class="text-xs text-gray-500">{{ group.clientName }}</p>
                    </div>
                    <span class="font-mono text-sm font-bold text-gray-950 dark:text-white">
                        {{ state.formatPreciseDuration(group.sessions.reduce((total, session) => total + state.sessionDuration(session), 0)) }}
                    </span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <SessionRow
                        v-for="session in group.sessions"
                        :key="session.id"
                        :state="state"
                        :session="session"
                        :tasks="state.tasksForProject(session.project_id)"
                        :invoices="state.invoicesForClient(session.client_id)"
                        compact-actions
                    />
                </div>
            </article>

            <div v-if="state.currentDaySessions.length === 0" class="rounded-xl border border-dashed border-gray-300 py-12 text-center text-sm text-gray-500 dark:border-gray-700">
                No timer sessions recorded on {{ state.activeDay.full_label }}.
            </div>
        </div>
    </div>
</template>
