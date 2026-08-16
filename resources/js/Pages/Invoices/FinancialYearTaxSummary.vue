<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    financialYearStart: {
        type: Number,
        required: true,
    },
    financialYearLabel: {
        type: String,
        required: true,
    },
    periodStart: {
        type: String,
        required: true,
    },
    periodEnd: {
        type: String,
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
    convertedTaxSummary: {
        type: Object,
        default: () => ({
            total_invoices: 0,
            converted_invoices: 0,
            missing_rate_invoices: 0,
            groups: [],
        }),
    },
});

function formatCurrency(amount, currencyCode = 'USD') {
    const value = Number(amount || 0);
    const code = /^[A-Z]{3}$/.test(String(currencyCode || '').toUpperCase())
        ? String(currencyCode).toUpperCase()
        : 'USD';

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: code,
        }).format(value);
    } catch (error) {
        return `${code} ${value.toFixed(2)}`;
    }
}

function formatPercent(rate) {
    const value = Number(rate || 0);

    return `${value.toFixed(2)}%`;
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
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

function openFinancialYear(startYear) {
    router.get(route('invoices.financialYearTaxSummary'), {
        financial_year_start: startYear,
    });
}
</script>

<template>
    <AppLayout title="Financial Year Tax Summary">
        <Head :title="`NZ Financial Year Tax Summary ${financialYearLabel}`" />

        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-4xl space-y-6">
                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400">New Zealand Financial Year</p>
                            <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ financialYearLabel }}</h1>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                Period: {{ formatDate(periodStart) }} to {{ formatDate(periodEnd) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-xl bg-gray-200 dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-700 transition"
                                @click="openFinancialYear(financialYearStart - 1)"
                            >
                                Previous Year
                            </button>
                            <button
                                type="button"
                                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition"
                                @click="openFinancialYear(financialYearStart + 1)"
                            >
                                Next Year
                            </button>
                            <a
                                href="/invoices"
                                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition"
                            >
                                Back To Invoices
                            </a>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Year Summary</h2>
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoices</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ summary.invoice_count }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Sessions</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ summary.sessions_count }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Tracked Time</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ formatDuration(summary.total_duration_seconds) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Tax Calculation</h2>

                    <div class="mt-5 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Billable Time</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(summary.billable_time_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Expenses</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(summary.total_expenses_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Gross Amount</td>
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

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Converted Totals (Stored Invoice Rates)</h2>

                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Amalgamated from each invoice using the conversion rate recorded on that invoice at finalization time.
                    </p>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoices In Year</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ convertedTaxSummary.total_invoices }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Converted Invoices</p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ convertedTaxSummary.converted_invoices }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Missing Stored Rate</p>
                            <p class="mt-2 text-2xl font-bold text-amber-700 dark:text-amber-300">{{ convertedTaxSummary.missing_rate_invoices }}</p>
                        </div>
                    </div>

                    <div v-if="(convertedTaxSummary.groups || []).length > 0" class="mt-5 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/60">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Target Currency</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Invoices</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Gross Amount</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Total Tax</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Net After Tax</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="group in convertedTaxSummary.groups" :key="group.target_currency">
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ group.target_currency }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ group.invoice_count }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(group.gross_amount_converted, group.target_currency) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(group.total_tax_amount_converted, group.target_currency) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(group.net_amount_converted, group.target_currency) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="mt-5 text-sm text-amber-700 dark:text-amber-300">
                        No invoices in this financial year have a stored conversion snapshot yet.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
