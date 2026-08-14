<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    taxSummary: {
        type: Object,
        required: true,
    },
});

function formatInvoiceId(invoiceId) {
    return `INV${invoiceId}`;
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatCurrency(amount) {
    const value = Number(amount || 0);

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
}

function formatPercent(rate) {
    const value = Number(rate || 0);

    return `${value.toFixed(2)}%`;
}

function formatDuration(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;

    return `${hours.toString().padStart(2, '0')}:${minutes
        .toString()
        .padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}
</script>

<template>
    <AppLayout title="Invoice Tax Summary">
        <Head :title="`Tax Summary ${formatInvoiceId(invoice.id)}`" />

        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-4xl space-y-6">
                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">Tax Summary</p>
                            <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatInvoiceId(invoice.id) }}
                            </h1>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Created: {{ formatDateTime(invoice.created_at) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Client: {{ invoice.client ? invoice.client.name : 'Unassigned' }}</p>
                        </div>

                        <Link
                            :href="route('invoices.show', invoice.id)"
                            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition"
                        >
                            Back To Invoice
                        </Link>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Tax Calculation</h2>

                    <div class="mt-5 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Gross Invoice Amount</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(taxSummary.gross_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Income Tax ({{ formatPercent(taxSummary.income_tax_rate) }})</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(taxSummary.income_tax_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Student Loan Tax ({{ formatPercent(taxSummary.student_loan_tax_rate) }})</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(taxSummary.student_loan_tax_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Total Tax</td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(taxSummary.total_tax_amount) }}</td>
                                </tr>
                                <tr class="bg-emerald-50 dark:bg-emerald-900/20">
                                    <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300 font-semibold">Estimated Net After Tax</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(taxSummary.net_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
