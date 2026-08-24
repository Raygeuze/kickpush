<script setup>
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Object,
        required: true,
    },
    additionalTaxes: {
        type: Array,
        default: () => [],
    },
});

const additionalTaxCategories = [
    { value: 'tax', label: 'Tax' },
    { value: 'levy', label: 'Levy' },
    { value: 'allocation', label: 'Allocation' },
];

const additionalTaxValueTypes = [
    { value: 'percentage', label: 'Percentage (%)' },
    { value: 'fixed', label: 'Fixed Amount' },
];

const additionalTaxCurrencies = [
    { value: 'USD', label: 'USD' },
    { value: 'NZD', label: 'NZD' },
    { value: 'AUD', label: 'AUD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
    { value: 'CAD', label: 'CAD' },
    { value: 'JPY', label: 'JPY' },
    { value: 'SGD', label: 'SGD' },
    { value: 'INR', label: 'INR' },
    { value: 'CHF', label: 'CHF' },
    { value: 'SEK', label: 'SEK' },
    { value: 'NOK', label: 'NOK' },
    { value: 'DKK', label: 'DKK' },
    { value: 'HKD', label: 'HKD' },
    { value: 'ZAR', label: 'ZAR' },
    { value: 'MXN', label: 'MXN' },
    { value: 'BRL', label: 'BRL' },
    { value: 'CNY', label: 'CNY' },
    { value: 'KRW', label: 'KRW' },
    { value: 'AED', label: 'AED' },
];

const form = useForm({
    additional_taxes: (props.additionalTaxes || []).map((tax, index) => ({
        id: tax.id ?? null,
        name: tax.name ?? '',
        category: tax.category ?? 'tax',
        value_type: tax.value_type ?? 'percentage',
        value: tax.value ?? 0,
        currency: tax.currency ?? 'USD',
        position: Number.isInteger(tax.position) ? tax.position : index,
    })),
});

const addAdditionalTax = () => {
    form.additional_taxes.push({
        id: null,
        name: '',
        category: 'tax',
        value_type: 'percentage',
        value: 0,
        currency: 'USD',
        position: form.additional_taxes.length,
    });
};

const removeAdditionalTax = (index) => {
    form.additional_taxes.splice(index, 1);
    reindexAdditionalTaxes();
};

const reindexAdditionalTaxes = () => {
    form.additional_taxes = form.additional_taxes.map((tax, index) => ({
        ...tax,
        position: index,
    }));
};

const updateTeamAdditionalTaxes = () => {
    reindexAdditionalTaxes();

    form.put(route('teams.additionalTaxes.update', props.team), {
        errorBag: 'updateTeamAdditionalTaxes',
        preserveScroll: true,
    });
};
</script>

<template>
    <FormSection @submitted="updateTeamAdditionalTaxes">
        <template #title>
            Team Additional Tax Items
        </template>

        <template #description>
            Manage taxes, levies, and allocations that apply to this team.
        </template>

        <template #form>
            <div class="col-span-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Additional Tax Items</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">These values are shared across the selected team.</p>
                    </div>

                    <SecondaryButton type="button" @click.prevent="addAdditionalTax">
                        Add Item
                    </SecondaryButton>
                </div>

                <InputError :message="form.errors.additional_taxes" class="mt-2" />

                <div v-if="form.additional_taxes.length === 0" class="mt-4 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-4 text-sm text-gray-500 dark:text-gray-400">
                    No team additional tax items configured.
                </div>

                <div v-else class="mt-4 space-y-4">
                    <div
                        v-for="(tax, index) in form.additional_taxes"
                        :key="`${tax.id ?? 'new'}-${index}`"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 p-4"
                    >
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                            <div class="sm:col-span-4">
                                <InputLabel :for="`additional_tax_name_${index}`" value="Name" />
                                <TextInput
                                    :id="`additional_tax_name_${index}`"
                                    v-model="tax.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="ACC levy"
                                />
                                <InputError :message="form.errors[`additional_taxes.${index}.name`]" class="mt-2" />
                            </div>

                            <div class="sm:col-span-3">
                                <InputLabel :for="`additional_tax_category_${index}`" value="Category" />
                                <select
                                    :id="`additional_tax_category_${index}`"
                                    v-model="tax.category"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option v-for="option in additionalTaxCategories" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors[`additional_taxes.${index}.category`]" class="mt-2" />
                            </div>

                            <div class="sm:col-span-3">
                                <InputLabel :for="`additional_tax_value_type_${index}`" value="Value Type" />
                                <select
                                    :id="`additional_tax_value_type_${index}`"
                                    v-model="tax.value_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option v-for="option in additionalTaxValueTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors[`additional_taxes.${index}.value_type`]" class="mt-2" />
                            </div>

                            <div class="sm:col-span-2">
                                <InputLabel :for="`additional_tax_value_${index}`" :value="tax.value_type === 'percentage' ? 'Rate (%)' : 'Amount'" />
                                <TextInput
                                    :id="`additional_tax_value_${index}`"
                                    v-model="tax.value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors[`additional_taxes.${index}.value`]" class="mt-2" />
                            </div>

                            <div class="sm:col-span-2" v-if="tax.value_type === 'fixed'">
                                <InputLabel :for="`additional_tax_currency_${index}`" value="Currency" />
                                <select
                                    :id="`additional_tax_currency_${index}`"
                                    v-model="tax.currency"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option v-for="option in additionalTaxCurrencies" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors[`additional_taxes.${index}.currency`]" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <SecondaryButton type="button" @click.prevent="removeAdditionalTax(index)">
                                Remove
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>
