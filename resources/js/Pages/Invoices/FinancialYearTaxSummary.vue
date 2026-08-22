<script setup>
import { computed } from 'vue';
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
    convertedTaxSummary: {
        type: Object,
        default: () => ({
            target_currency: 'USD',
            total_invoices: 0,
            converted_invoices: 0,
            missing_rate_invoices: 0,
            paid_project_total_invoice_count: 0,
            unpaid_project_total_invoice_count: 0,
            uninvoiced_project_total_invoice_count: 0,
            overdue_project_total_invoice_count: 0,
            billable_time_amount_converted: 0,
            total_expenses_amount_converted: 0,
            subtotal_amount_converted: 0,
            total_discount_amount_converted: 0,
            gross_amount_converted: 0,
            total_business_expenses_amount_converted: 0,
            deductible_business_expenses_amount_converted: 0,
            taxable_amount_converted: 0,
            additional_tax_items: [],
            total_tax_before_deductible_expenses_amount_converted: 0,
            total_tax_after_deductible_expenses_amount_converted: 0,
            tax_savings_from_deductible_expenses_amount_converted: 0,
            total_tax_amount_converted: 0,
            net_after_tax_amount_converted: 0,
            allocation_total_converted: 0,
            total_deductions_amount_converted: 0,
            net_amount_converted: 0,
            project_totals_converted: [],
            unpaid_project_totals_converted: [],
            uninvoiced_project_totals_converted: [],
            overdue_project_totals_converted: [],
        }),
    },
});

const convertedTaxCurrency = computed(() => {
    const code = String(props?.convertedTaxSummary?.target_currency || 'USD').toUpperCase();
    return /^[A-Z]{3}$/.test(code) ? code : 'USD';
});

const taxAndLevyItems = computed(() =>
    (props?.convertedTaxSummary?.additional_tax_items || []).filter((item) =>
        String(item?.category || '').toLowerCase() !== 'allocation'
    )
);

const projectTotals = computed(() => {
    const totals = props?.convertedTaxSummary?.project_totals_converted;
    return Array.isArray(totals) ? totals : [];
});

const unpaidProjectTotals = computed(() => {
    const totals = props?.convertedTaxSummary?.unpaid_project_totals_converted;
    return Array.isArray(totals) ? totals : [];
});

const uninvoicedProjectTotals = computed(() => {
    const totals = props?.convertedTaxSummary?.uninvoiced_project_totals_converted;
    return Array.isArray(totals) ? totals : [];
});

const overdueProjectTotals = computed(() => {
    const totals = props?.convertedTaxSummary?.overdue_project_totals_converted;
    return Array.isArray(totals) ? totals : [];
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

function formatAdditionalTaxValue(item) {
    if ((item?.value_type || '') === 'percentage') {
        return formatPercent(item?.value || 0);
    }

    const code = String(item?.currency || convertedTaxCurrency.value || 'USD').toUpperCase();

    return formatCurrency(item?.value || 0, code);
}

function formatCategory(value) {
    const normalized = String(value || '').toLowerCase();

    if (normalized === 'levy') {
        return 'Levy';
    }

    if (normalized === 'allocation') {
        return 'Allocation';
    }

    return 'Tax';
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

                    <div class="mt-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Paid Per Project Totals</h3>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            Calculated from {{ Number(convertedTaxSummary.paid_project_total_invoice_count || 0) }} invoices.
                        </p>

                        <div v-if="projectTotals.length > 0" class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/60">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Project</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Sessions</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Tracked Time</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Billable Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr
                                        v-for="project in projectTotals"
                                        :key="`fy-year-summary-project-total-${project.project_id ?? 'unassigned'}-${project.project_name}`"
                                    >
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ project.project_name || 'Unassigned Project' }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ Number(project.sessions_count || 0) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatDuration(project.total_duration_seconds || 0) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(project.billable_time_amount_converted || 0, convertedTaxCurrency) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-else class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            No paid invoice sessions were found for this financial year, so there are no per-project totals yet.
                        </p>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Sent</h3>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            Calculated from {{ Number(convertedTaxSummary.unpaid_project_total_invoice_count || 0) }} invoices.
                        </p>

                        <div v-if="unpaidProjectTotals.length > 0" class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/60">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Project</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Sessions</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Tracked Time</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr
                                        v-for="project in unpaidProjectTotals"
                                        :key="`fy-year-summary-unpaid-project-total-${project.project_id ?? 'unassigned'}-${project.project_name}`"
                                    >
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ project.project_name || 'Unassigned Project' }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ Number(project.sessions_count || 0) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatDuration(project.total_duration_seconds || 0) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(project.billable_time_amount_converted || 0, convertedTaxCurrency) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-else class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            No finalized unpaid invoice sessions were found for this financial year.
                        </p>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">In Progress</h3>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            Calculated from {{ Number(convertedTaxSummary.uninvoiced_project_total_invoice_count || 0) }} invoices.
                        </p>

                        <div v-if="uninvoicedProjectTotals.length > 0" class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/60">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Project</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Sessions</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Tracked Time</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr
                                        v-for="project in uninvoicedProjectTotals"
                                        :key="`fy-year-summary-uninvoiced-project-total-${project.project_id ?? 'unassigned'}-${project.project_name}`"
                                    >
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ project.project_name || 'Unassigned Project' }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ Number(project.sessions_count || 0) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatDuration(project.total_duration_seconds || 0) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(project.billable_time_amount_converted || 0, convertedTaxCurrency) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-else class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            No draft invoice sessions were found for this financial year.
                        </p>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Overdue</h3>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            Calculated from {{ Number(convertedTaxSummary.overdue_project_total_invoice_count || 0) }} invoices.
                        </p>

                        <div v-if="overdueProjectTotals.length > 0" class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/60">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Project</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Sessions</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Tracked Time</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr
                                        v-for="project in overdueProjectTotals"
                                        :key="`fy-year-summary-overdue-project-total-${project.project_id ?? 'unassigned'}-${project.project_name}`"
                                    >
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ project.project_name || 'Unassigned Project' }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ Number(project.sessions_count || 0) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatDuration(project.total_duration_seconds || 0) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(project.billable_time_amount_converted || 0, convertedTaxCurrency) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-else class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            No overdue invoice sessions were found for this financial year.
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Tax Calculation ({{ convertedTaxCurrency }})</h2>

                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Amalgamated from each invoice in its own source currency, then converted to your country currency.
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

                    <div class="mt-5 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr class="bg-gray-50 dark:bg-gray-800/60">
                                    <td colspan="2" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Revenue</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Subtotal</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(convertedTaxSummary.subtotal_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Discount</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-300">-{{ formatCurrency(convertedTaxSummary.total_discount_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Billable Time</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(convertedTaxSummary.billable_time_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Line Items</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(convertedTaxSummary.total_expenses_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Gross Amount</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(convertedTaxSummary.gross_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr class="bg-gray-50 dark:bg-gray-800/60">
                                    <td colspan="2" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Business Expenses</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Total Business Expenses</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(convertedTaxSummary.total_business_expenses_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Tax Deductible Business Expenses</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-300">{{ formatCurrency(convertedTaxSummary.deductible_business_expenses_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Taxable Amount</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(convertedTaxSummary.taxable_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr class="bg-gray-50 dark:bg-gray-800/60">
                                    <td colspan="2" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Taxes</td>
                                </tr>
                                <tr
                                    v-for="(item, index) in taxAndLevyItems"
                                    :key="`financial-year-additional-tax-item-${item.id ?? 'new'}-${index}`"
                                >
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ item.name }}
                                        <span class="text-xs text-gray-500 dark:text-gray-400">({{ formatCategory(item.category) }} • {{ formatAdditionalTaxValue(item) }})</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(item.amount, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Tax Before Deductible Expenses</td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(convertedTaxSummary.total_tax_before_deductible_expenses_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Tax Savings From Deductible Expenses</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-300">{{ formatCurrency(convertedTaxSummary.tax_savings_from_deductible_expenses_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Total Tax (After Deductible Expenses)</td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(convertedTaxSummary.total_tax_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr class="bg-gray-50 dark:bg-gray-800/60">
                                    <td colspan="2" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Net</td>
                                </tr>
                                <tr class="bg-emerald-50 dark:bg-emerald-900/20">
                                    <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300 font-semibold">Net Profit After Tax</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(convertedTaxSummary.net_after_tax_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Total Allocations (Deductions)</td>
                                    <td class="px-4 py-3 text-right font-semibold text-amber-700 dark:text-amber-300">{{ formatCurrency(convertedTaxSummary.allocation_total_converted, convertedTaxCurrency) }}</td>
                                </tr>
                                <tr class="bg-emerald-50 dark:bg-emerald-900/20">
                                    <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300 font-semibold">Total After Allocations</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(convertedTaxSummary.net_amount_converted, convertedTaxCurrency) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="convertedTaxSummary.converted_invoices === 0" class="mt-5 text-sm text-amber-700 dark:text-amber-300">
                        No invoices in this financial year could be converted to your country currency yet.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
