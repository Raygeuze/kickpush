<script setup>
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    bank_account_name: props.team.bank_account_name ?? '',
    bank_name: props.team.bank_name ?? '',
    bsb_code: props.team.bsb_code ?? '',
    bank_account_number: props.team.bank_account_number ?? '',
});

const updateTeamPaymentInformation = () => {
    form.put(route('teams.paymentInformation.update', props.team), {
        errorBag: 'updateTeamPaymentInformation',
        preserveScroll: true,
    });
};
</script>

<template>
    <FormSection @submitted="updateTeamPaymentInformation">
        <template #title>
            Payment Information
        </template>

        <template #description>
            Update bank details used in invoice payment instructions for this team.
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="team_bank_account_name" value="Account Name" />
                <TextInput
                    id="team_bank_account_name"
                    v-model="form.bank_account_name"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="organization"
                />
                <InputError :message="form.errors.bank_account_name" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="team_bank_name" value="Bank Name" />
                <TextInput
                    id="team_bank_name"
                    v-model="form.bank_name"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="organization"
                />
                <InputError :message="form.errors.bank_name" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="team_bsb_code" value="BSB Code" />
                <TextInput
                    id="team_bsb_code"
                    v-model="form.bsb_code"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="off"
                />
                <InputError :message="form.errors.bsb_code" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="team_bank_account_number" value="Account Number" />
                <TextInput
                    id="team_bank_account_number"
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
