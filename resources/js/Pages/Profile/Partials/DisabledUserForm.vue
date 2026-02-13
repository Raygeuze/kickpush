<script setup>
import { ref } from 'vue';
import { useForm, useRemember } from '@inertiajs/vue3';
import ActionSection from '@/Components/ActionSection.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    user: Object,
});

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    reenable_request: props.user.reenable_requested_description ? props.user.reenable_requested_description : '',
});

const submit = () => {
    form.post(route('user.reenable_request'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {

        },
    });
};
</script>

<template>
    <ActionSection>
        <template #title>
            Your account has been disabled
        </template>

        <template #description>
            Someone has always got to take things one step too far huh.
        </template>

        <template #content>
            <div v-if="!user.reenable_requested" class="max-w-xl text-sm text-gray-600">
                If you think this is a mistake and you want to do something about it, meet me at the bike sheds at 2pm!
                <br>
                Or, tell us why you think this is a mistake below.            
            </div>

            <div v-if="user.reenable_requested" class="max-w-xl text-sm text-red-400">
                Oh, so you want more huh? Well we've already got your request, we'll get to it when we please.
                <br>
                In the meantime, go think about what you've done and why you are in this mess!          
            </div>


            <div class="mt-5 flex-col">
                <div class="flex mb-2 border rounded-md shadow-sm" >
                    <textarea class="w-full border-0 rounded text-black" name="reenable_request" id="reenable_request" v-model="form.reenable_request"></textarea>
                </div>
                <div v-if="!user.reenable_requested" class="w-full flex">
                    <PrimaryButton @click="submit()" class="ml-auto" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Save
                    </PrimaryButton>
                </div>
            </div>

        </template>
    </ActionSection>
</template>
