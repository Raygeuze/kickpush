<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    businessExpenses: {
        type: Array,
        default: () => [],
    },
});

const businessExpenses = ref(props.businessExpenses || []);
const form = ref({
    name: '',
    description: '',
    amount: '',
    incurred_on: '',
    tax_deductible: false,
    deductible_percentage: 100,
    receipt: null,
});
const isSubmitting = ref(false);
const isUpdating = ref(false);
const removingExpenseIds = ref([]);
const editingExpenseId = ref(null);
const editForm = ref({
    name: '',
    description: '',
    amount: '',
    incurred_on: '',
    tax_deductible: false,
    deductible_percentage: 100,
    receipt: null,
});
const statusMessage = ref('');

function formatCurrency(amount) {
    const value = Number(amount || 0);

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

async function createBusinessExpense() {
    if (isSubmitting.value) {
        return;
    }

    if (!form.value.amount || Number(form.value.amount) <= 0) {
        statusMessage.value = 'Enter an amount greater than 0.';
        return;
    }

    isSubmitting.value = true;

    try {
        const payload = new FormData();
        payload.append('name', form.value.name || '');
        payload.append('description', form.value.description || '');
        payload.append('amount', String(Number(form.value.amount)));
        payload.append('incurred_on', form.value.incurred_on || '');
        payload.append('tax_deductible', form.value.tax_deductible ? '1' : '0');
        payload.append(
            'deductible_percentage',
            form.value.tax_deductible ? String(Number(form.value.deductible_percentage || 0)) : ''
        );

        if (form.value.receipt) {
            payload.append('receipt', form.value.receipt);
        }

        const response = await axios.post('/business-expenses', payload);

        if (response.data?.business_expense) {
            businessExpenses.value = [response.data.business_expense, ...businessExpenses.value];
        }

        statusMessage.value = response.data?.message || 'Business expense added.';
        form.value = {
            name: '',
            description: '',
            amount: '',
            incurred_on: '',
            tax_deductible: false,
            deductible_percentage: 100,
            receipt: null,
        };
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to add business expense.';
    } finally {
        isSubmitting.value = false;
    }
}

function onCreateReceiptSelected(event) {
    const file = event.target?.files?.[0] ?? null;
    form.value.receipt = file;
}

function startEdit(expense) {
    editingExpenseId.value = expense.id;
    editForm.value = {
        name: expense.name || '',
        description: expense.description || '',
        amount: Number(expense.amount || 0),
        incurred_on: expense.incurred_on || '',
        tax_deductible: Boolean(expense.tax_deductible),
        deductible_percentage: Number(expense.deductible_percentage ?? 100),
        receipt: null,
    };
    statusMessage.value = '';
}

function cancelEdit() {
    editingExpenseId.value = null;
    editForm.value = {
        name: '',
        description: '',
        amount: '',
        incurred_on: '',
        tax_deductible: false,
        deductible_percentage: 100,
        receipt: null,
    };
}

function onEditReceiptSelected(event) {
    const file = event.target?.files?.[0] ?? null;
    editForm.value.receipt = file;
}

async function saveEdit(expenseId) {
    if (isUpdating.value) {
        return;
    }

    if (!editForm.value.amount || Number(editForm.value.amount) <= 0) {
        statusMessage.value = 'Enter an amount greater than 0.';
        return;
    }

    isUpdating.value = true;

    try {
        const payload = new FormData();
        payload.append('name', editForm.value.name || '');
        payload.append('description', editForm.value.description || '');
        payload.append('amount', String(Number(editForm.value.amount)));
        payload.append('incurred_on', editForm.value.incurred_on || '');
        payload.append('tax_deductible', editForm.value.tax_deductible ? '1' : '0');
        payload.append(
            'deductible_percentage',
            editForm.value.tax_deductible ? String(Number(editForm.value.deductible_percentage || 0)) : ''
        );

        if (editForm.value.receipt) {
            payload.append('receipt', editForm.value.receipt);
        }

        const response = await axios.post(`/business-expenses/${expenseId}`, payload);
        const updatedExpense = response.data?.business_expense;

        if (updatedExpense) {
            businessExpenses.value = businessExpenses.value.map((expense) => {
                if (expense.id === expenseId) {
                    return updatedExpense;
                }

                return expense;
            });
        }

        statusMessage.value = response.data?.message || 'Business expense updated.';
        cancelEdit();
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to update business expense.';
    } finally {
        isUpdating.value = false;
    }
}

function isRemoving(expenseId) {
    return removingExpenseIds.value.includes(expenseId);
}

async function removeExpense(expenseId) {
    if (isRemoving(expenseId)) {
        return;
    }

    if (!window.confirm('Remove this business expense?')) {
        return;
    }

    removingExpenseIds.value.push(expenseId);

    try {
        const response = await axios.delete(`/business-expenses/${expenseId}`);

        businessExpenses.value = businessExpenses.value.filter((expense) => expense.id !== expenseId);

        if (editingExpenseId.value === expenseId) {
            cancelEdit();
        }

        statusMessage.value = response.data?.message || 'Business expense removed.';
    } catch (error) {
        statusMessage.value = error?.response?.data?.message || 'Failed to remove business expense.';
    } finally {
        removingExpenseIds.value = removingExpenseIds.value.filter((id) => id !== expenseId);
    }
}
</script>

<template>
    <AppLayout title="Business Expenses">
        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-5xl rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Business Expenses</h1>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Track non-invoice business costs and mark tax deductible entries.
                        </p>
                    </div>

                    <Link :href="route('invoices.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        Back to Invoices
                    </Link>
                </div>

                <p v-if="statusMessage" class="mt-4 text-sm text-gray-700 dark:text-gray-300">
                    {{ statusMessage }}
                </p>

                <div class="mt-6 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Business Expense</h2>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            placeholder="Name (optional)"
                        />
                        <input
                            v-model="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            placeholder="Amount"
                        />
                        <input
                            v-model="form.incurred_on"
                            type="date"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        />
                        <label class="inline-flex items-center gap-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                            <input v-model="form.tax_deductible" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            Tax deductible
                        </label>
                        <input
                            v-if="form.tax_deductible"
                            v-model="form.deductible_percentage"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            placeholder="Deductible percentage"
                        />
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                            @change="onCreateReceiptSelected"
                        />
                    </div>

                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="mt-3 w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                        placeholder="Description (optional)"
                    />

                    <button
                        type="button"
                        class="mt-3 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition disabled:opacity-60"
                        :disabled="isSubmitting"
                        @click="createBusinessExpense"
                    >
                        {{ isSubmitting ? 'Saving...' : 'Add Expense' }}
                    </button>
                </div>

                <div class="mt-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Expense List</h2>
                    <p v-if="businessExpenses.length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        No business expenses yet.
                    </p>

                    <div v-else class="mt-3 space-y-3">
                        <div
                            v-for="expense in businessExpenses"
                            :key="expense.id"
                            class="rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div v-if="editingExpenseId !== expense.id" class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ expense.name || 'Business expense' }}
                                    </p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                        Date: {{ formatDate(expense.incurred_on || expense.created_at) }}
                                    </p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                        {{ expense.description || 'No description provided.' }}
                                    </p>
                                    <p v-if="expense.receipt_url" class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                        Receipt:
                                        <a :href="expense.receipt_url" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            {{ expense.receipt_original_name || 'View receipt' }}
                                        </a>
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ formatCurrency(expense.amount) }}
                                    </p>
                                    <p
                                        class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="expense.tax_deductible ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                    >
                                        {{ expense.tax_deductible ? 'Tax deductible' : 'Not tax deductible' }}
                                    </p>
                                    <p v-if="expense.tax_deductible" class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                        Deductible: {{ Number(expense.deductible_percentage || 0).toFixed(2) }}%
                                    </p>
                                    <div class="mt-2 flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700 transition"
                                            title="Edit expense"
                                            aria-label="Edit expense"
                                            @click="startEdit(expense)"
                                        >
                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M12 20h9" />
                                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-600 text-white hover:bg-red-700 transition disabled:opacity-60"
                                            :disabled="isRemoving(expense.id)"
                                            title="Remove expense"
                                            aria-label="Remove expense"
                                            @click="removeExpense(expense.id)"
                                        >
                                            <span v-if="isRemoving(expense.id)" class="text-[10px] font-semibold">...</span>
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

                            <div v-else class="space-y-3">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <input
                                        v-model="editForm.name"
                                        type="text"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                        placeholder="Name (optional)"
                                    />
                                    <input
                                        v-model="editForm.amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                        placeholder="Amount"
                                    />
                                    <input
                                        v-model="editForm.incurred_on"
                                        type="date"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                    />
                                    <label class="inline-flex items-center gap-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                                        <input v-model="editForm.tax_deductible" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        Tax deductible
                                    </label>
                                    <input
                                        v-if="editForm.tax_deductible"
                                        v-model="editForm.deductible_percentage"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                        placeholder="Deductible percentage"
                                    />
                                    <input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white sm:col-span-2"
                                        @change="onEditReceiptSelected"
                                    />
                                </div>

                                <textarea
                                    v-model="editForm.description"
                                    rows="3"
                                    class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white"
                                    placeholder="Description (optional)"
                                />

                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition disabled:opacity-60"
                                        :disabled="isUpdating"
                                        @click="saveEdit(expense.id)"
                                    >
                                        {{ isUpdating ? 'Saving...' : 'Save Changes' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                        :disabled="isUpdating"
                                        @click="cancelEdit"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
