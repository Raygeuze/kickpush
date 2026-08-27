<script setup>
defineProps({
    canManageNonTimerRecords: {
        type: Boolean,
        default: true,
    },
    isFinalized: {
        type: Boolean,
        default: false,
    },
    isSubmittingExpense: {
        type: Boolean,
        default: false,
    },
    expenses: {
        type: Array,
        default: () => [],
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    isExpenseBusy: {
        type: Function,
        required: true,
    },
    expenseName: {
        type: String,
        default: '',
    },
    expenseAmount: {
        type: [String, Number],
        default: '',
    },
    expenseDescription: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'update:expenseName',
    'update:expenseAmount',
    'update:expenseDescription',
    'addExpense',
    'removeExpense',
]);
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-gray-900 sm:p-8">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">One-Off Line Items</h2>

        <p v-if="isFinalized" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
            This invoice is finalized and cannot be changed.
        </p>

        <div v-if="canManageNonTimerRecords" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <input
                :value="expenseName"
                type="text"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                placeholder="Line item name (optional)"
                :disabled="isFinalized || isSubmittingExpense"
                @input="emit('update:expenseName', $event.target.value)"
            />

            <input
                :value="expenseAmount"
                type="number"
                min="0.01"
                step="0.01"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                placeholder="Amount (USD)"
                :disabled="isFinalized || isSubmittingExpense"
                @input="emit('update:expenseAmount', $event.target.value)"
            />
        </div>

        <textarea
            v-if="canManageNonTimerRecords"
            :value="expenseDescription"
            rows="3"
            class="mt-3 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            placeholder="Description (optional)"
            :disabled="isFinalized || isSubmittingExpense"
            @input="emit('update:expenseDescription', $event.target.value)"
        />

        <button
            v-if="canManageNonTimerRecords"
            type="button"
            class="mt-3 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
            :disabled="isFinalized || isSubmittingExpense"
            @click="emit('addExpense')"
        >
            {{ isSubmittingExpense ? 'Adding Line Item...' : 'Add Line Item' }}
        </button>

        <p v-if="expenses.length === 0" class="mt-5 text-sm text-gray-600 dark:text-gray-300">
            No line items added yet.
        </p>

        <p v-if="!canManageNonTimerRecords" class="mt-3 text-sm text-amber-700 dark:text-amber-300">
            Your role can view line items only.
        </p>

        <div v-else class="mt-5 space-y-3">
            <div
                v-for="expense in expenses"
                :key="expense.id"
                class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ expense.name || 'One-off line item' }}
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-300">
                            {{ expense.description || 'No description provided.' }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            {{ formatCurrency(expense.amount) }}
                        </p>
                        <button
                            v-if="canManageNonTimerRecords"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium text-white transition disabled:opacity-60"
                            :class="isFinalized ? 'cursor-not-allowed bg-gray-500' : 'bg-red-600 hover:bg-red-700'"
                            :disabled="isFinalized || isExpenseBusy(expense.id)"
                            @click="emit('removeExpense', expense.id)"
                        >
                            {{ isExpenseBusy(expense.id) ? 'Removing...' : 'Remove' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
