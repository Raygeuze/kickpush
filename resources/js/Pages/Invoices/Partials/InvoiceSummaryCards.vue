<script setup>
defineProps({
    summary: {
        type: Object,
        required: true,
    },
    formatDuration: {
        type: Function,
        required: true,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Confirmed Sessions</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ summary.sessions_count }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Time</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatDuration(summary.total_duration_seconds) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">One-Off Line Items</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(summary.total_expenses_amount) }}</p>
        </div>
        <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-700 dark:bg-emerald-900/20">
            <p class="text-xs uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total Billable</p>
            <p class="mt-2 text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(summary.total_billable_amount) }}</p>
        </div>
    </div>
</template>
