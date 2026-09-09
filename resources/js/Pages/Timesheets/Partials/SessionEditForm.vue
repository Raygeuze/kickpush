<script setup>
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
});
</script>

<template>
    <form class="mt-3 grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900 sm:grid-cols-4" @submit.prevent="state.saveEdit(session)">
        <label class="text-xs font-semibold uppercase text-gray-500 sm:col-span-2">
            Task
            <select v-model="state.editForm.task_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                <option v-for="task in tasks" :key="task.id" :value="String(task.id)">{{ task.name }}</option>
            </select>
        </label>
        <label class="text-xs font-semibold uppercase text-gray-500">
            Date
            <input v-model="state.editForm.session_date" type="date" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
        </label>
        <label class="text-xs font-semibold uppercase text-gray-500">
            Duration
            <input v-model="state.editForm.duration" type="text" inputmode="numeric" placeholder="00:00:00" pattern="^\d+(:\d{1,2}){0,2}$" class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
        </label>

        <div class="flex flex-wrap items-center gap-3 sm:col-span-4">
            <button type="submit" class="rounded-lg bg-gray-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60 dark:bg-white dark:text-gray-950" :disabled="state.isBusy(session.id)">Save changes</button>

            <template v-if="session.invoice_id">
                <button type="button" class="text-sm font-semibold text-gray-600 hover:text-gray-950 disabled:opacity-60 dark:text-gray-300 dark:hover:text-white" :disabled="state.isBusy(session.id)" @click="state.detachInvoice(session)">Remove from invoice</button>
            </template>
            <template v-else-if="invoices.length">
                <select v-model="state.editForm.invoice_id" class="rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                    <option value="">Add to draft invoice...</option>
                    <option v-for="invoice in invoices" :key="invoice.id" :value="String(invoice.id)">INV{{ invoice.invoice_number || invoice.id }}</option>
                </select>
                <button type="button" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 disabled:opacity-60 dark:text-emerald-400" :disabled="state.isBusy(session.id) || !state.editForm.invoice_id" @click="state.attachInvoice(session, state.editForm.invoice_id)">Attach</button>
            </template>
        </div>
    </form>
</template>
