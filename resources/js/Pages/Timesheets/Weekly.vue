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
        <section class="hidden overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950 md:block">
            <table class="w-full min-w-[940px] table-fixed border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                        <th class="w-52 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                        <th v-for="day in state.visibleDays" :key="day.key" class="px-2 py-3 text-center text-xs font-semibold text-gray-500" :class="day.is_today ? 'bg-emerald-50 dark:bg-emerald-950/30' : ''">
                            <span class="block text-gray-900 dark:text-white">{{ day.short_label }}</span>
                            <span>{{ day.date_label }}</span>
                        </th>
                        <th class="w-24 px-3 py-3 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in state.projectRows" :key="row.key" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <th class="px-4 py-3 text-left">
                            <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ row.projectName }}</span>
                            <span class="block truncate text-xs font-normal text-gray-500">{{ row.clientName }}</span>
                        </th>
                        <td v-for="day in state.visibleDays" :key="day.key" class="p-1.5" :class="day.is_today ? 'bg-emerald-50/60 dark:bg-emerald-950/20' : ''">
                            <button
                                type="button"
                                class="h-14 w-full rounded-md text-center transition"
                                :class="[
                                    state.sessionsForCell(row, day.key).length ? 'bg-gray-100 text-gray-950 hover:bg-emerald-100 dark:bg-gray-900 dark:text-white dark:hover:bg-emerald-950' : 'text-gray-300 hover:bg-gray-50 dark:text-gray-700 dark:hover:bg-gray-900',
                                    state.selectedCell && state.selectedCell.projectKey === row.key && state.selectedCell.dayKey === day.key ? 'ring-2 ring-emerald-500' : '',
                                ]"
                                @click="state.chooseCell(row, day)"
                            >
                                <span class="block text-sm font-semibold">{{ state.sessionsForCell(row, day.key).length ? state.formatDuration(state.cellDuration(row, day.key)) : '-' }}</span>
                                <span v-if="state.sessionsForCell(row, day.key).length" class="text-[11px] text-gray-500">{{ state.sessionsForCell(row, day.key).length }} session{{ state.sessionsForCell(row, day.key).length === 1 ? '' : 's' }}</span>
                            </button>
                        </td>
                        <td class="px-3 py-3 text-right text-sm font-bold text-gray-950 dark:text-white">{{ state.formatDuration(state.projectDuration(row)) }}</td>
                    </tr>
                    <tr v-if="page.canCreateSessions" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <th class="px-4 py-3 text-left">
                            <span class="block truncate text-sm font-semibold text-gray-500">New entry</span>
                            <span class="block truncate text-xs font-normal text-gray-400">Pick a project and task</span>
                        </th>
                        <td v-for="day in state.visibleDays" :key="day.key" class="p-1.5" :class="day.is_today ? 'bg-emerald-50/60 dark:bg-emerald-950/20' : ''">
                            <button
                                type="button"
                                class="flex h-14 w-full items-center justify-center rounded-md border border-dashed border-gray-300 text-gray-400 transition hover:border-emerald-400 hover:text-emerald-600 dark:border-gray-700 dark:text-gray-600 dark:hover:border-emerald-700"
                                :class="state.selectedCell && state.selectedCell.projectKey === state.NEW_ROW_KEY && state.selectedCell.dayKey === day.key ? 'ring-2 ring-emerald-500' : ''"
                                :title="`Start a timer on ${day.full_label}`"
                                @click="state.chooseNewEntryCell(day)"
                            >
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14" /><path d="M5 12h14" /></svg>
                            </button>
                        </td>
                        <td class="px-3 py-3"></td>
                    </tr>
                    <tr v-if="state.projectRows.length" class="bg-gray-50 dark:bg-gray-900">
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Daily total</th>
                        <td v-for="day in state.visibleDays" :key="day.key" class="px-2 py-3 text-center text-sm font-bold text-gray-900 dark:text-white">{{ state.formatDuration(state.dayDuration(day.key)) }}</td>
                        <td class="px-3 py-3 text-right text-sm font-bold text-emerald-700 dark:text-emerald-400">{{ state.formatDuration(state.weekDuration) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="md:hidden">
            <div class="flex gap-1 overflow-x-auto border-b border-gray-200 pb-2 dark:border-gray-800">
                <button
                    v-for="day in state.visibleDays"
                    :key="day.key"
                    type="button"
                    class="min-w-20 rounded-lg px-3 py-2 text-sm transition"
                    :class="state.selectedDayKey === day.key
                        ? 'border-2 border-emerald-500 bg-emerald-50/80 text-gray-950 shadow-sm dark:border-emerald-400 dark:bg-emerald-950/40 dark:text-white'
                        : 'border-2 border-transparent bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800'"
                    @click="state.selectedDayKey = day.key"
                >
                    <span class="block font-semibold" :class="state.selectedDayKey === day.key ? 'text-emerald-700 dark:text-emerald-300' : ''">{{ day.short_label }}</span>
                    <span class="text-xs opacity-75">{{ day.date_label }}</span>
                </button>
            </div>
            <div class="mt-3 space-y-2">
                <button v-for="row in state.mobileProjectRows" :key="row.key" type="button" class="flex w-full items-center justify-between rounded-lg border border-gray-200 bg-white p-4 text-left dark:border-gray-800 dark:bg-gray-950" @click="state.chooseCell(row, page.days.find((day) => day.key === state.selectedDayKey))">
                    <span><span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ row.projectName }}</span><span class="text-xs text-gray-500">{{ row.clientName }} · {{ row.daySessions.length }} session{{ row.daySessions.length === 1 ? '' : 's' }}</span></span>
                    <span class="text-sm font-bold text-gray-950 dark:text-white">{{ state.formatDuration(state.cellDuration(row, state.selectedDayKey)) }}</span>
                </button>
                <p v-if="state.mobileProjectRows.length === 0" class="py-8 text-center text-sm text-gray-500">No sessions recorded on this day.</p>
                <button v-if="page.canCreateSessions" type="button" class="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 p-4 text-sm font-semibold text-gray-500 hover:border-emerald-400 hover:text-emerald-600 dark:border-gray-700" @click="state.chooseNewEntryCell(page.days.find((day) => day.key === state.selectedDayKey))">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14" /><path d="M5 12h14" /></svg>
                    New entry
                </button>
            </div>
        </section>

        <section v-if="state.selectedCell" class="border-t border-gray-300 pt-5 dark:border-gray-700">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ state.isNewEntryCell ? 'New entry' : state.selectedProject?.projectName }}</h2>
                    <p class="text-sm text-gray-500">{{ state.selectedDay?.full_label }} · {{ state.formatPreciseDuration(state.selectedSessions.reduce((total, session) => total + state.sessionDuration(session), 0)) }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        v-if="page.canCreateSessions && !state.startingCell"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="state.hasActiveSession"
                        :title="state.hasActiveSession ? 'Stop your active timer before starting another' : 'Start a timer on this project'"
                        @click="state.openStartTimer"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                        Start timer
                    </button>
                    <button type="button" class="text-sm font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="state.selectedCell = null">Close</button>
                </div>
            </div>

            <p v-if="state.formErrors" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ state.formErrors }}</p>

            <form v-if="state.startingCell" class="relative mt-3 flex flex-col gap-3 rounded-lg border border-emerald-300 bg-emerald-50/50 p-4 dark:border-emerald-800 dark:bg-emerald-950/20 sm:flex-row sm:items-end" @submit.prevent="state.startTimer">
                <p class="absolute right-4 top-3 text-xs text-gray-500">Timer runs from now and is recorded on {{ state.selectedDay?.full_label }}.</p>
                <label v-if="state.isNewEntryCell" class="flex-1 text-xs font-semibold uppercase text-gray-500">
                    Project
                    <select v-model="state.startForm.project_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Select a project</option>
                        <optgroup v-for="group in state.projectsByClient" :key="group.clientName" :label="group.clientName">
                            <option v-for="project in group.projects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                        </optgroup>
                    </select>
                </label>
                <label class="flex-1 text-xs font-semibold uppercase text-gray-500">
                    Task
                    <select v-model="state.startForm.task_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Select a task</option>
                        <option v-for="task in state.projectTasks" :key="task.id" :value="String(task.id)">{{ task.name }}</option>
                    </select>
                </label>
                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-lg bg-gray-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60 dark:bg-white dark:text-gray-950" :disabled="state.startingTimer">Start</button>
                    <button type="button" class="text-sm font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="state.cancelStartTimer">Cancel</button>
                </div>
            </form>

            <div class="mt-3 divide-y divide-gray-200 border-y border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                <SessionRow
                    v-for="session in state.selectedSessions"
                    :key="session.id"
                    :state="state"
                    :session="session"
                    :tasks="state.projectTasks"
                    :invoices="state.attachableInvoices"
                />

                <p v-if="state.selectedSessions.length === 0" class="py-6 text-center text-sm text-gray-500">No sessions recorded in this cell yet.</p>
            </div>
        </section>

        <p v-if="state.allSessions.length === 0" class="rounded-lg border border-dashed border-gray-300 py-14 text-center text-sm text-gray-500 dark:border-gray-700">
            No timer sessions match this week and filter selection.
        </p>
    </div>
</template>
