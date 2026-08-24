<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    isPaid: {
        type: Boolean,
        default: false,
    },
    isFinalized: {
        type: Boolean,
        default: false,
    },
    isDeletingInvoice: {
        type: Boolean,
        default: false,
    },
    isFinalizing: {
        type: Boolean,
        default: false,
    },
    isMarkingPaid: {
        type: Boolean,
        default: false,
    },
    isSendingInvoiceEmail: {
        type: Boolean,
        default: false,
    },
    statusMessage: {
        type: String,
        default: '',
    },
    formatInvoiceId: {
        type: Function,
        required: true,
    },
    formatDateTime: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['deleteInvoice', 'finalizeInvoice', 'markInvoicePaid', 'emailInvoiceToClient']);
</script>

<template>
    <div class="rounded-2xl bg-white dark:bg-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice</p>
                <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ formatInvoiceId(invoice.id) }}
                </h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Created: {{ formatDateTime(invoice.created_at) }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Issued: {{ formatDateTime(invoice.issued_at) }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Paid: {{ formatDateTime(invoice.paid_at) }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Client: {{ invoice.client ? invoice.client.name : 'Unassigned' }}
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Financial Year: {{ invoice.financial_year ? invoice.financial_year.label : '-' }}
                </p>
            </div>

            <div class="flex flex-col items-end gap-3">
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                        :class="isPaid ? 'bg-green-700 text-white' : (isFinalized ? 'bg-gray-800 text-white' : 'bg-emerald-100 text-emerald-700')"
                    >
                        {{ isPaid ? 'Paid' : (invoice.status === 'finalized' ? 'Finalized' : 'Draft') }}
                    </span>

                    <button
                        v-if="!isFinalized"
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-600 text-white transition hover:bg-red-700 disabled:opacity-60"
                        :disabled="isDeletingInvoice"
                        title="Delete Invoice"
                        aria-label="Delete Invoice"
                        @click="emit('deleteInvoice')"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 6h18" />
                            <path d="M8 6V4h8v2" />
                            <path d="M19 6l-1 14H6L5 6" />
                            <path d="M10 11v6" />
                            <path d="M14 11v6" />
                        </svg>
                    </button>
                </div>

                <button
                    v-if="!isFinalized"
                    type="button"
                    class="rounded-xl px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                    :class="isFinalized ? 'cursor-not-allowed bg-gray-500' : 'bg-blue-600 hover:bg-blue-700'"
                    :disabled="isFinalized || isFinalizing"
                    @click="emit('finalizeInvoice')"
                >
                    {{ isFinalizing ? 'Finalizing...' : 'Finalize Invoice' }}
                </button>

                <button
                    v-if="invoice.status === 'finalized'"
                    type="button"
                    class="rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-700 disabled:opacity-60"
                    :disabled="isMarkingPaid"
                    @click="emit('markInvoicePaid')"
                >
                    {{ isMarkingPaid ? 'Marking...' : 'Mark As Paid' }}
                </button>

                <a
                    v-if="isFinalized"
                    :href="`/invoices/${invoice.id}/pdf`"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                >
                    Download PDF
                </a>

                <button
                    v-if="invoice.status === 'finalized'"
                    type="button"
                    class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-700 disabled:opacity-60"
                    :disabled="isSendingInvoiceEmail || !invoice.client || !invoice.client.email"
                    @click="emit('emailInvoiceToClient')"
                >
                    {{ isSendingInvoiceEmail ? 'Sending Email...' : 'Email PDF To Client' }}
                </button>

                <p
                    v-if="invoice.status === 'finalized' && (!invoice.client || !invoice.client.email)"
                    class="text-xs text-amber-700 dark:text-amber-300"
                >
                    Add a valid client email to send this invoice by email.
                </p>

                <Link
                    v-if="$page.props.auth.user?.current_team?.is_owner"
                    :href="route('invoices.taxSummary', invoice.id)"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700"
                >
                    Tax Summary
                </Link>
            </div>
        </div>

        <p v-if="invoice.notes" class="mt-4 text-sm text-gray-700 dark:text-gray-200">
            {{ invoice.notes }}
        </p>

        <p v-if="statusMessage" class="mt-4 text-sm text-gray-700 dark:text-gray-200">
            {{ statusMessage }}
        </p>
    </div>
</template>
