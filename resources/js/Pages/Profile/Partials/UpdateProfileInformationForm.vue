<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    user: Object,
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

function currencyForCountry(countryCode) {
    const value = String(countryCode || '').toUpperCase();
    const map = {
        NZ: 'NZD',
        AU: 'AUD',
        US: 'USD',
        CA: 'CAD',
        GB: 'GBP',
        JP: 'JPY',
        SG: 'SGD',
        IN: 'INR',
        CH: 'CHF',
        SE: 'SEK',
        NO: 'NOK',
        DK: 'DKK',
        HK: 'HKD',
        ZA: 'ZAR',
        MX: 'MXN',
        BR: 'BRL',
        CN: 'CNY',
        KR: 'KRW',
        AE: 'AED',
        IE: 'EUR',
        FR: 'EUR',
        DE: 'EUR',
        ES: 'EUR',
        IT: 'EUR',
        NL: 'EUR',
        PT: 'EUR',
        BE: 'EUR',
        AT: 'EUR',
        FI: 'EUR',
        GR: 'EUR',
        LU: 'EUR',
    };

    return map[value] || 'USD';
}

const defaultAdditionalTaxCurrency = currencyForCountry(props.user.country);

const profileAdditionalTaxes = Array.isArray(props.user.additional_taxes)
    ? props.user.additional_taxes
    : [];

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    bank_account_name: props.user.bank_account_name ?? '',
    bank_name: props.user.bank_name ?? '',
    bsb_code: props.user.bsb_code ?? '',
    bank_account_number: props.user.bank_account_number ?? '',
    additional_taxes: profileAdditionalTaxes.map((tax, index) => ({
        id: tax.id ?? null,
        name: tax.name ?? '',
        category: tax.category ?? 'tax',
        value_type: tax.value_type ?? 'percentage',
        value: tax.value ?? 0,
        currency: tax.currency ?? defaultAdditionalTaxCurrency,
        position: Number.isInteger(tax.position) ? tax.position : index,
    })),
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    reindexAdditionalTaxes();

    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};

const addAdditionalTax = () => {
    form.additional_taxes.push({
        id: null,
        name: '',
        category: 'tax',
        value_type: 'percentage',
        value: 0,
        currency: defaultAdditionalTaxCurrency,
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
</script>

<template>
    <FormSection @submitted="updateProfileInformation">
        <template #title>
            Profile Information
        </template>

        <template #description>
            Update your account's profile information and email address.
        </template>

        <template #form>
            <!-- Profile Photo -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input
                    id="photo"
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    @change="updatePhotoPreview"
                >

                <InputLabel for="photo" value="Photo" />

                <!-- Current Profile Photo -->
                <div v-show="! photoPreview" class="mt-2">
                    <img :src="user.profile_photo_url" :alt="user.name" class="rounded-full size-20 object-cover">
                </div>

                <!-- New Profile Photo Preview -->
                <div v-show="photoPreview" class="mt-2">
                    <span
                        class="block rounded-full size-20 bg-cover bg-no-repeat bg-center"
                        :style="'background-image: url(\'' + photoPreview + '\');'"
                    />
                </div>

                <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewPhoto">
                    Select A New Photo
                </SecondaryButton>

                <SecondaryButton
                    v-if="user.profile_photo_path"
                    type="button"
                    class="mt-2"
                    @click.prevent="deletePhoto"
                >
                    Remove Photo
                </SecondaryButton>

                <InputError :message="form.errors.photo" class="mt-2" />
            </div>

            <!-- Name -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="name" value="Username" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <!-- Email -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="username"
                />
                <InputError :message="form.errors.email" class="mt-2" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <p class="text-sm mt-2">
                        Your email address is unverified.

                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            @click.prevent="sendEmailVerification"
                        >
                            Click here to re-send the verification email.
                        </Link>
                    </p>

                    <div v-show="verificationLinkSent" class="mt-2 font-medium text-sm text-green-600">
                        A new verification link has been sent to your email address.
                    </div>
                </div>
            </div>

            <div class="col-span-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Additional Tax Items</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Add custom taxes, levies, or allocations as a percent or fixed amount.</p>
                    </div>

                    <SecondaryButton type="button" @click.prevent="addAdditionalTax">
                        Add Item
                    </SecondaryButton>
                </div>

                <InputError :message="form.errors.additional_taxes" class="mt-2" />

                <div v-if="form.additional_taxes.length === 0" class="mt-4 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-4 text-sm text-gray-500 dark:text-gray-400">
                    No additional tax items configured.
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

            <!-- Payment Information -->
            <div class="col-span-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Payment Information</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">These bank details will be used as invoice payment instructions.</p>
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="bank_account_name" value="Account Name" />
                <TextInput
                    id="bank_account_name"
                    v-model="form.bank_account_name"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="organization"
                />
                <InputError :message="form.errors.bank_account_name" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="bank_name" value="Bank Name" />
                <TextInput
                    id="bank_name"
                    v-model="form.bank_name"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="organization"
                />
                <InputError :message="form.errors.bank_name" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="bsb_code" value="BSB Code" />
                <TextInput
                    id="bsb_code"
                    v-model="form.bsb_code"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="off"
                />
                <InputError :message="form.errors.bsb_code" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="bank_account_number" value="Account Number" />
                <TextInput
                    id="bank_account_number"
                    v-model="form.bank_account_number"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="off"
                />
                <InputError :message="form.errors.bank_account_number" class="mt-2" />
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
