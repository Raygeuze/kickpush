<script setup>
const props = defineProps({
    canManageNonTimerRecords: {
        type: Boolean,
        default: true,
    },
    discountType: {
        type: String,
        default: '',
    },
    discountValue: {
        type: Number,
        default: 0,
    },
    isFinalized: {
        type: Boolean,
        default: false,
    },
    isSavingDiscount: {
        type: Boolean,
        default: false,
    },
    discountAmount: {
        type: Number,
        default: 0,
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['update:discountType', 'update:discountValue', 'save']);

function onTypeChange(event) {
    emit('update:discountType', event.target.value || '');
}

function onValueChange(event) {
    emit('update:discountValue', Number(event.target.value || 0));
}
</script>

<template>
    <div class="mt-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice Discount</p>
        <div class="mt-2 grid grid-cols-1 items-end gap-3 sm:grid-cols-[180px_1fr_auto]">
            <select
                :value="discountType"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                :disabled="!canManageNonTimerRecords || isFinalized || isSavingDiscount"
                @change="onTypeChange"
            >
                <option value="">No discount</option>
                <option value="percentage">Percentage</option>
                <option value="fixed">Fixed amount</option>
            </select>

            <input
                :value="discountValue"
                type="number"
                min="0"
                :max="discountType === 'percentage' ? 100 : undefined"
                step="0.01"
                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                :placeholder="discountType === 'percentage' ? 'Percent (0-100)' : 'Amount'"
                :disabled="!canManageNonTimerRecords || isFinalized || isSavingDiscount || !discountType"
                @input="onValueChange"
            />

            <button
                v-if="canManageNonTimerRecords"
                type="button"
                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                :disabled="isFinalized || isSavingDiscount"
                @click="emit('save')"
            >
                {{ isSavingDiscount ? 'Saving...' : 'Save Discount' }}
            </button>
        </div>

        <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
            Applied discount: {{ formatCurrency(discountAmount || 0) }}
        </p>
        <p v-if="!canManageNonTimerRecords" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
            Your role can view invoice discount details only.
        </p>
    </div>
</template>
