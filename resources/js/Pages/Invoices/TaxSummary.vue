<script setup>
import { computed } from 'vue';
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
    currencyConversion: {
        type: Object,
        default: () => null,
    },
});

const invoiceCurrency = computed(() => {
    const code = String(props?.invoice?.client?.currency || 'USD').toUpperCase();
    return /^[A-Z]{3}$/.test(code) ? code : 'USD';
});

const showAllocations = computed(() => props?.invoice?.status === 'paid');
const baseTaxCurrency = computed(() => {
    const code = String(props?.taxSummary?.currency || invoiceCurrency.value).toUpperCase();
    return /^[A-Z]{3}$/.test(code) ? code : invoiceCurrency.value;
});

const convertedTaxCurrency = computed(() => {
    const code = String(props?.currencyConversion?.target_currency || '').toUpperCase();
    return /^[A-Z]{3}$/.test(code) ? code : null;
});

const taxAndLevyItems = computed(() =>
    (props?.taxSummary?.additional_tax_items || []).filter((item) =>
        String(item?.category || '').toLowerCase() !== 'allocation'
    )
);

const allocationItems = computed(() =>
    (props?.taxSummary?.additional_tax_items || []).filter((item) =>
        String(item?.category || '').toLowerCase() === 'allocation'
    )
);

const baseAllocationItems = computed(() => {
    if (!showAllocations.value) {
        return [];
    }

    return allocationItems.value.filter((item) => {
        const itemCurrency = normalizeCurrencyCode(item?.currency) || baseTaxCurrency.value;
        return itemCurrency === baseTaxCurrency.value;
    });
});

const convertedAllocationItems = computed(() => {
    if (!showAllocations.value || !convertedTaxCurrency.value) {
        return [];
    }

    return allocationItems.value.filter((item) => {
        const itemCurrency = normalizeCurrencyCode(item?.currency) || baseTaxCurrency.value;
        return itemCurrency === convertedTaxCurrency.value;
    });
});

const baseAllocationTotal = computed(() =>
    roundCurrency(baseAllocationItems.value.reduce((carry, item) => carry + Number(item?.amount || 0), 0))
);

const convertedAllocationTotal = computed(() =>
    roundCurrency(convertedAllocationItems.value.reduce((carry, item) => carry + allocationAmountForConvertedTable(item), 0))
);

const baseNetAfterAllocations = computed(() =>
    roundCurrency(Number(props?.taxSummary?.net_after_tax_amount || 0) - baseAllocationTotal.value)
);

const convertedNetAfterAllocations = computed(() =>
    roundCurrency(Number(props?.currencyConversion?.net_after_tax_amount_converted || 0) - convertedAllocationTotal.value)
);

function formatInvoiceId(invoiceId) {
    return `INV${invoiceId}`;
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatCurrency(amount, currencyCode = invoiceCurrency.value) {
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

    const code = String(item?.currency || invoiceCurrency.value).toUpperCase();

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

function formatDuration(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;

    return `${hours.toString().padStart(2, '0')}:${minutes
        .toString()
        .padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

function formatRate(value) {
    const rate = Number(value || 0);

    return rate > 0 ? rate.toFixed(6) : '-';
}

function normalizeCurrencyCode(value) {
    const code = String(value || '').toUpperCase();
    return /^[A-Z]{3}$/.test(code) ? code : null;
}

function roundCurrency(value) {
    return Math.round(Number(value || 0) * 100) / 100;
}

function convertAmount(amount) {
    const value = Number(amount || 0);
    const rate = Number(props?.currencyConversion?.rate || 0);

    if (rate <= 0) {
        return value;
    }

    return value * rate;
}

function allocationAmountForConvertedTable(item) {
    const fixedValue = Number(item?.value || 0);
    const valueType = String(item?.value_type || '').toLowerCase();

    if (valueType === 'fixed') {
        return fixedValue;
    }

    return Number(convertAmount(item?.amount || 0));
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
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Subtotal</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(summary.subtotal_amount || taxSummary.gross_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Discount</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-300">-{{ formatCurrency(summary.discount_amount || 0) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Gross Invoice Amount</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(taxSummary.gross_amount) }}</td>
                                </tr>
                                <tr
                                    v-for="(item, index) in taxAndLevyItems"
                                    :key="`additional-tax-item-${item.id ?? 'new'}-${index}`"
                                >
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ item.name }}
                                        <span class="text-xs text-gray-500 dark:text-gray-400">({{ formatCategory(item.category) }} • {{ formatAdditionalTaxValue(item) }})</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(item.amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Total Tax</td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(taxSummary.total_tax_amount) }}</td>
                                </tr>
                                <tr class="bg-emerald-50 dark:bg-emerald-900/20">
                                    <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300 font-semibold">Net Profit After Tax</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(taxSummary.net_after_tax_amount) }}</td>
                                </tr>
                                <tr
                                    v-for="(item, index) in baseAllocationItems"
                                    :key="`allocation-item-${item.id ?? 'new'}-${index}`"
                                >
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ item.name }}
                                        <span class="text-xs text-gray-500 dark:text-gray-400">(Use of net profit • {{ formatAdditionalTaxValue(item) }})</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-amber-700 dark:text-amber-300">{{ formatCurrency(item.amount) }}</td>
                                </tr>
                                <tr v-if="baseAllocationItems.length > 0" class="bg-amber-50 dark:bg-amber-900/20">
                                    <td class="px-4 py-3 text-amber-800 dark:text-amber-200 font-semibold">Total Allocations (Deductions)</td>
                                    <td class="px-4 py-3 text-right font-semibold text-amber-800 dark:text-amber-200">{{ formatCurrency(baseAllocationTotal) }}</td>
                                </tr>
                                <tr v-if="baseAllocationItems.length > 0" class="bg-emerald-50 dark:bg-emerald-900/20">
                                    <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300 font-semibold">Total After Allocations</td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(baseNetAfterAllocations) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Live Currency Conversion (Wise)</h2>

                    <div v-if="currencyConversion && currencyConversion.available" class="mt-5 space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ currencyConversion.source_currency }} to {{ currencyConversion.target_currency }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            1 {{ currencyConversion.source_currency }} = {{ formatRate(currencyConversion.rate) }} {{ currencyConversion.target_currency }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Rate as of {{ formatDateTime(currencyConversion.as_of) }}
                        </p>
                        <p v-if="currencyConversion.is_locked" class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                            This rate is locked from invoice finalization.
                        </p>

                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Subtotal</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(convertAmount(summary.subtotal_amount || taxSummary.gross_amount), currencyConversion.target_currency) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Discount</td>
                                        <td class="px-4 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-300">-{{ formatCurrency(convertAmount(summary.discount_amount || 0), currencyConversion.target_currency) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Gross Invoice Amount</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(currencyConversion.gross_amount_converted, currencyConversion.target_currency) }}</td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in taxAndLevyItems"
                                        :key="`converted-additional-tax-item-${item.id ?? 'new'}-${index}`"
                                    >
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                            {{ item.name }}
                                            <span class="text-xs text-gray-500 dark:text-gray-400">({{ formatCategory(item.category) }} • {{ formatAdditionalTaxValue(item) }})</span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(convertAmount(item.amount), currencyConversion.target_currency) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Total Tax</td>
                                        <td class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(currencyConversion.total_tax_amount_converted, currencyConversion.target_currency) }}</td>
                                    </tr>
                                    <tr class="bg-emerald-50 dark:bg-emerald-900/20">
                                        <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300 font-semibold">Net Profit After Tax</td>
                                        <td class="px-4 py-3 text-right font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(currencyConversion.net_after_tax_amount_converted, currencyConversion.target_currency) }}</td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in convertedAllocationItems"
                                        :key="`converted-allocation-item-${item.id ?? 'new'}-${index}`"
                                    >
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                            {{ item.name }}
                                            <span class="text-xs text-gray-500 dark:text-gray-400">(Use of net profit • {{ formatAdditionalTaxValue(item) }})</span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-amber-700 dark:text-amber-300">{{ formatCurrency(allocationAmountForConvertedTable(item), currencyConversion.target_currency) }}</td>
                                    </tr>
                                    <tr v-if="convertedAllocationItems.length > 0" class="bg-amber-50 dark:bg-amber-900/20">
                                        <td class="px-4 py-3 text-amber-800 dark:text-amber-200 font-semibold">Total Allocations (Deductions)</td>
                                        <td class="px-4 py-3 text-right font-semibold text-amber-800 dark:text-amber-200">{{ formatCurrency(convertedAllocationTotal, currencyConversion.target_currency) }}</td>
                                    </tr>
                                    <tr v-if="convertedAllocationItems.length > 0" class="bg-emerald-50 dark:bg-emerald-900/20">
                                        <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300 font-semibold">Total After Allocations</td>
                                        <td class="px-4 py-3 text-right font-bold text-emerald-800 dark:text-emerald-200">{{ formatCurrency(convertedNetAfterAllocations, currencyConversion.target_currency) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-if="currencyConversion.message" class="text-xs text-gray-500 dark:text-gray-400">
                            {{ currencyConversion.message }}
                        </p>
                    </div>

                    <p v-else class="mt-5 text-sm text-amber-700 dark:text-amber-300">
                        {{ currencyConversion?.message || 'Live conversion is temporarily unavailable.' }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
