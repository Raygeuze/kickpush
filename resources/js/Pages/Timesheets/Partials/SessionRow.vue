<script setup>
import { Link } from '@inertiajs/vue3';
import SessionEditForm from './SessionEditForm.vue';

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    session: {
        type: Object,
        required: true,
    },
    tasks: {
        type: Array,
        default: () => [],
    },
    invoices: {
        type: Array,
        default: () => [],
    },
    compactActions: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ session.task_name }}</p>
                    <span v-if="session.is_running" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                        Running
                    </span>
                    <span v-else-if="session.is_paused" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300">Open</span>
                    <span v-else-if="session.invoice_locked" class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">Locked</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    {{ session.user_name }} · {{ session.started_time }}<span v-if="session.stopped_time">-{{ session.stopped_time }}</span> · {{ state.invoiceLabel(session) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="font-mono text-sm font-bold text-gray-950 dark:text-white">{{ state.formatPreciseDuration(state.sessionDuration(session)) }}</span>

                <button
                    v-if="session.can_operate && (session.is_running || session.is_paused)"
                    type="button"
                    :class="compactActions ? 'rounded-lg bg-gray-950 px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-gray-800 disabled:opacity-60 dark:bg-white dark:text-gray-950' : 'rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-200'"
                    :disabled="state.isBusy(session.id)"
                    @click="state.stopSession(session)"
                >
                    Stop
                </button>

                <button
                    v-if="session.can_operate && !session.is_running && !session.is_paused && !session.invoice_locked"
                    type="button"
                    class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-200"
                    :disabled="state.isBusy(session.id)"
                    @click="state.restartSession(session)"
                >
                    Restart
                </button>

                <button
                    v-if="session.can_update && !session.invoice_locked && !session.is_running && !session.is_paused"
                    type="button"
                    class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-200"
                    :disabled="state.isBusy(session.id)"
                    @click="state.editingSessionId === session.id ? state.cancelEditing() : state.startEditing(session)"
                >
                    {{ state.editingSessionId === session.id ? 'Cancel' : 'Edit' }}
                </button>

                <Link v-if="session.invoice_id" :href="route('invoices.show', session.invoice_id)" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">
                    Invoice
                </Link>

                <button
                    v-if="session.can_delete"
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-600 text-white transition hover:bg-red-700 disabled:opacity-60"
                    :disabled="state.isDeleting(session.id)"
                    title="Delete timer session"
                    aria-label="Delete timer session"
                    @click="state.deleteSession(session)"
                >
                    <span v-if="state.isDeleting(session.id)" class="text-[10px] font-semibold">...</span>
                    <svg v-else viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6" /><path d="M14 11v6" /></svg>
                </button>
            </div>
        </div>

        <SessionEditForm
            v-if="state.editingSessionId === session.id"
            :state="state"
            :session="session"
            :tasks="tasks"
            :invoices="invoices"
        />
    </div>
</template>
