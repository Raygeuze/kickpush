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
    isSubmittingLineItem: {
        type: Boolean,
        default: false,
    },
    statusMessage: {
        type: String,
        default: '',
    },
    lineItems: {
        type: Array,
        default: () => [],
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
    isLineItemBusy: {
        type: Function,
        required: true,
    },
    lineItemName: {
        type: String,
        default: '',
    },
    lineItemAmount: {
        type: [String, Number],
        default: '',
    },
    lineItemDescription: {
        type: String,
        default: '',
    },
    lineItemCurrency: {
        type: String,
        default: 'USD',
    },
});

const emit = defineEmits([
    'update:line-item-name',
    'update:line-item-amount',
    'update:line-item-description',
    'add-line-item',
    'remove-line-item',
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
                :value="lineItemName"
                type="text"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                placeholder="Line item name (optional)"
                :disabled="isFinalized || isSubmittingLineItem"
                @input="emit('update:line-item-name', $event.target.value)"
            />

            <input
                :value="lineItemAmount"
                type="number"
                min="0.01"
                step="0.01"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                :placeholder="`Amount (${lineItemCurrency.toUpperCase()})`"
                :disabled="isFinalized || isSubmittingLineItem"
                @input="emit('update:line-item-amount', $event.target.value)"
            />
        </div>

        <textarea
            v-if="canManageNonTimerRecords"
            :value="lineItemDescription"
            rows="3"
            class="mt-3 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            placeholder="Description (optional)"
            :disabled="isFinalized || isSubmittingLineItem"
            @input="emit('update:line-item-description', $event.target.value)"
        />

        <button
            v-if="canManageNonTimerRecords"
            type="button"
            class="mt-3 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
            :disabled="isFinalized || isSubmittingLineItem"
            @click="emit('add-line-item')"
        >
            {{ isSubmittingLineItem ? 'Adding Line Item...' : 'Add Line Item' }}
        </button>

        <p v-if="statusMessage" class="mt-3 text-sm text-gray-700 dark:text-gray-200">
            {{ statusMessage }}
        </p>

        <p v-if="lineItems.length === 0" class="mt-5 text-sm text-gray-600 dark:text-gray-300">
            No line items added yet.
        </p>

        <p v-if="!canManageNonTimerRecords" class="mt-3 text-sm text-amber-700 dark:text-amber-300">
            Your role can view line items only.
        </p>

        <div v-else class="mt-5 space-y-3">
            <div
                v-for="lineItem in lineItems"
                :key="lineItem.id"
                class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ lineItem.name || 'One-off line item' }}
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-300">
                            {{ lineItem.description || 'No description provided.' }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            {{ formatCurrency(lineItem.amount) }}
                        </p>
                        <button
                            v-if="canManageNonTimerRecords"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium text-white transition disabled:opacity-60"
                            :class="isFinalized ? 'cursor-not-allowed bg-gray-500' : 'bg-red-600 hover:bg-red-700'"
                            :disabled="isFinalized || isLineItemBusy(lineItem.id)"
                            @click="emit('remove-line-item', lineItem.id)"
                        >
                            {{ isLineItemBusy(lineItem.id) ? 'Removing...' : 'Remove' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
