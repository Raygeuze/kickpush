<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Daily from './Daily.vue';
import Weekly from './Weekly.vue';
import { useTimesheetSessions } from './composables/useTimesheetSessions';

const props = defineProps({
    view: {
        type: String,
        default: 'day',
    },
    selectedDate: String,
    weekStart: String,
    weekEnd: String,
    timezone: String,
    serverNow: String,
    days: Array,
    sessions: Array,
    activeTimerSession: Object,
    clients: Array,
    projects: Array,
    tasks: Array,
    draftInvoices: Array,
    teamMembers: Array,
    canViewTeamSessions: Boolean,
    canCreateSessions: Boolean,
    filters: Object,
    navigation: Object,
    dayNavigation: Object,
});

const state = useTimesheetSessions(props);
</script>

<template>
    <AppLayout title="Timesheet">
        <Head title="Timesheet" />

        <div class="min-h-screen bg-gray-100 py-8 dark:bg-black">
            <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                <header class="flex flex-col gap-4 border-b border-gray-200 pb-5 dark:border-gray-800 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                            {{ state.currentViewMode === 'day' ? 'Daily record' : 'Weekly record' }}
                        </p>
                        <h1 class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">Timesheet</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            <template v-if="state.currentViewMode === 'day'">
                                {{ state.activeDay.full_label }} · {{ timezone }}
                            </template>
                            <template v-else>
                                {{ weekStart }} to {{ weekEnd }} · {{ timezone }}
                            </template>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex rounded-lg bg-gray-200 p-0.5 dark:bg-gray-800" role="group" aria-label="Timesheet view mode">
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                                :class="state.currentViewMode === 'day' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-900 dark:text-white' : 'text-gray-600 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white'"
                                @click="state.setViewMode('day')"
                            >
                                Day
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                                :class="state.currentViewMode === 'week' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-900 dark:text-white' : 'text-gray-600 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white'"
                                @click="state.setViewMode('week')"
                            >
                                Week
                            </button>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                title="Previous week"
                                aria-label="Previous week"
                                @click="state.goToWeek(navigation.previous_week)"
                            >
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
                            </button>
                            <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800" @click="state.goToToday">
                                {{ state.currentViewMode === 'day' ? 'Today' : 'This week' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                title="Next week"
                                aria-label="Next week"
                                @click="state.goToWeek(navigation.next_week)"
                            >
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                            </button>
                        </div>
                    </div>
                </header>

                <details class="group rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-gray-50 dark:text-gray-100 dark:hover:bg-gray-900">
                        <span>Stats & filters</span>
                        <span class="flex items-center gap-3 text-xs font-medium text-gray-500 dark:text-gray-400">
                            <span v-if="state.currentViewMode === 'day'">{{ state.formatPreciseDuration(state.currentDayDuration) }} · {{ state.currentDaySessions.length }} session{{ state.currentDaySessions.length === 1 ? '' : 's' }}</span>
                            <span v-else>{{ state.formatDuration(state.weekDuration) }} · {{ state.allSessions.length }} session{{ state.allSessions.length === 1 ? '' : 's' }}</span>
                            <svg viewBox="0 0 24 24" class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </span>
                    </summary>

                    <div class="border-t border-gray-200 dark:border-gray-800">
                        <section class="grid grid-cols-2 border-b border-gray-200 dark:border-gray-800 sm:grid-cols-4">
                            <template v-if="state.currentViewMode === 'day'">
                                <div class="border-b border-r border-gray-200 p-4 dark:border-gray-800 sm:border-b-0">
                                    <p class="text-xs text-gray-500">Day total</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ state.formatPreciseDuration(state.currentDayDuration) }}</p>
                                </div>
                                <div class="border-b border-gray-200 p-4 dark:border-gray-800 sm:border-b-0 sm:border-r">
                                    <p class="text-xs text-gray-500">Sessions</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ state.currentDaySessions.length }}</p>
                                </div>
                                <div class="border-r border-gray-200 p-4 dark:border-gray-800">
                                    <p class="text-xs text-gray-500">Projects active</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ state.currentDayProjectsCount }}</p>
                                </div>
                                <div class="p-4">
                                    <p class="text-xs text-gray-500">Open sessions</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ state.currentDayRunningCount }}</p>
                                </div>
                            </template>
                            <template v-else>
                                <div class="border-b border-r border-gray-200 p-4 dark:border-gray-800 sm:border-b-0">
                                    <p class="text-xs text-gray-500">Week total</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ state.formatDuration(state.weekDuration) }}</p>
                                </div>
                                <div class="border-b border-gray-200 p-4 dark:border-gray-800 sm:border-b-0 sm:border-r">
                                    <p class="text-xs text-gray-500">Sessions</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ state.allSessions.length }}</p>
                                </div>
                                <div class="border-r border-gray-200 p-4 dark:border-gray-800">
                                    <p class="text-xs text-gray-500">Active days</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ state.activeDaysCount }}</p>
                                </div>
                                <div class="p-4">
                                    <p class="text-xs text-gray-500">Open sessions</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ state.activeSessionsCount }}</p>
                                </div>
                            </template>
                        </section>

                        <section class="p-4">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                <select v-model="state.filterForm.client_id" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="state.applyFilters">
                                    <option value="">All clients</option>
                                    <option v-for="client in clients" :key="client.id" :value="String(client.id)">{{ client.name }}</option>
                                </select>
                                <select v-model="state.filterForm.project_id" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="state.applyFilters">
                                    <option value="">All projects</option>
                                    <option v-for="project in state.filteredProjects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                                </select>
                                <select v-if="canViewTeamSessions" v-model="state.filterForm.user_id" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="state.applyFilters">
                                    <option value="">All members</option>
                                    <option v-for="member in teamMembers" :key="member.id" :value="String(member.id)">{{ member.name }}</option>
                                </select>
                                <select v-model="state.filterForm.invoice_status" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" @change="state.applyFilters">
                                    <option value="">All invoice states</option>
                                    <option value="unassigned">Unassigned</option>
                                    <option value="draft">Draft</option>
                                    <option value="finalized">Finalized</option>
                                    <option value="paid">Paid</option>
                                </select>
                                <div class="flex items-center justify-between gap-3">
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                        <input v-model="state.showWeekends" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                        Weekends
                                    </label>
                                    <button type="button" class="text-sm font-semibold text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" @click="state.clearFilters">Clear</button>
                                </div>
                            </div>
                            <p v-if="state.statusMessage" class="mt-3 text-sm text-gray-700 dark:text-gray-200">{{ state.statusMessage }}</p>
                        </section>
                    </div>
                </details>

                <div v-if="state.activeTimerRunningElsewhere" class="flex flex-col items-start justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-3 w-3">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                        </span>
                        <div>
                            <p class="text-sm font-semibold">Active timer running on {{ state.activeTimerRunningElsewhere.day_key }}</p>
                            <p class="text-xs opacity-80">
                                {{ state.activeTimerRunningElsewhere.task_name }} · {{ state.activeTimerRunningElsewhere.project_name }} ({{ state.formatPreciseDuration(state.sessionDuration(state.activeTimerRunningElsewhere)) }})
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 shadow-sm hover:bg-gray-50 dark:bg-gray-900 dark:text-white" @click="state.selectDay(state.activeTimerRunningElsewhere.day_key)">
                            View day
                        </button>
                        <button type="button" class="rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-800 dark:bg-amber-600" :disabled="state.isBusy(state.activeTimerRunningElsewhere.id)" @click="state.stopSession(state.activeTimerRunningElsewhere)">
                            Stop timer
                        </button>
                    </div>
                </div>

                <Daily v-if="state.currentViewMode === 'day'" :state="state" :page="props" />
                <Weekly v-else :state="state" :page="props" />
            </div>
        </div>
    </AppLayout>
</template>
