<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DialogModal from '@/Components/DialogModal.vue';
import {ref, onMounted} from 'vue';

const emit = defineEmits(['close', 'showLoginModal']);

defineProps({
    showModal: Boolean,
});

const countries = ref([]);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    axios.get('/sanctum/csrf-cookie').then(response => {
        form.post(route('register'), {
            onFinish: () => form.reset('password', 'password_confirmation'),

            onSuccess: () => emit('close'),
        });
    });
};

const getCountries = () => {
    axios.get('/api/countries').then(response => {
        countries.value = response.data;
    });
};

onMounted(() => {
    getCountries();
});

</script>

<template>
    <DialogModal :show="showModal" @close="emit('close')">

        <template #content>
            <div class="flex items-center sm:justify-center">

                <AuthenticationCard>

                    <form @submit.prevent="submit">
                        <div class="flex justify-center items-center mb-4">
                            <AuthenticationCardLogo />
                        </div>
                        <div>
                            <InputLabel for="username" value="Username" />
                            <TextInput
                                id="username"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                autofocus
                                autocomplete="username"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div class="mt-4">
                            <InputLabel for="email" value="Email" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                                autocomplete="username"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div class="mt-4">
                            <InputLabel for="country" value="Country" />
                            <select
                                id="country"
                                v-model="form.country"
                                type="text"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            >
                                <option v-for="country in countries" :key="country.code" :value="country.code">
                                    <div class="flex justify-between">
                                        <span class="font-medium min-w-1/2">{{ country.code }}</span>
                                        <span class="w-1/2 text-right overflow-hidden"> - {{ country.name }}</span>
                                    </div>
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.country" />
                        </div>

                        <div class="mt-4">
                            <InputLabel for="password" value="Password" />
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full"
                                required
                                autocomplete="new-password"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="mt-4">
                            <InputLabel for="password_confirmation" value="Confirm Password" />
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                required
                                autocomplete="new-password"
                            />
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>

                        <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                            <InputLabel for="terms">
                                <div class="flex items-center">
                                    <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />

                                    <div class="ms-2">
                                        I agree to the <a target="_blank" :href="route('terms.show')" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Terms of Service</a> and <a target="_blank" :href="route('policy.show')" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Privacy Policy</a>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.terms" />
                            </InputLabel>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <div @click="emit('showLoginModal')" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Already registered?
                            </div>

                            <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Register
                            </PrimaryButton>
                        </div>
                    </form>
                </AuthenticationCard>
            </div>
        </template>

        <template #footer>
            <div class="flex justify-end">
                <button @click="emit('close')" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
            </div>
        </template>
    </DialogModal>
</template>
