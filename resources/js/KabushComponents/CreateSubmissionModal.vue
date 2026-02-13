<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import { ref } from 'vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import Payment from '@/kickpushComponents/Payment.vue';
import InputError from '@/Components/InputError.vue';
import { useCheckoutStore } from '../Stores/checkoutStore';

const checkoutStore = useCheckoutStore();

const emit = defineEmits(['close']);

const props = defineProps({
    day: Object,
    showModal: Boolean,
});

</script>

<template>
    <DialogModal :show="showModal" @close="emit('close'); checkoutStore.checkout.destroy();">
        <!-- <template #title>
            <h2 class="text-2xl font-bold text-blue-700 mb-2">Create Submission</h2>
        </template> -->
        <template #content>
            <div class="max-w-lg mx-auto bg-white/90 rounded-xl shadow-lg p-8">
                <h1 class="text-2xl font-bold text-blue-700 mb-2">Story time</h1>
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" id="title" v-model="checkoutStore.form.title" class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-gray-800 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" :class="checkoutStore.form.errors.title ? 'border-red-500' : ''" required />
                    <InputError v-if="checkoutStore.form.errors.title" class="mt-2" :message="checkoutStore.form.errors.title[0]" />
                </div>
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" 
                        v-model="checkoutStore.form.description" 
                        class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-gray-800 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition resize-none" 
                        :class="checkoutStore.form.errors.description ? 'border-red-500' : ''" 
                        required
                        oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';"
                        ></textarea>
                    <InputError v-if="checkoutStore.form.errors.description" class="mt-2" :message="checkoutStore.form.errors.description[0]" />
                </div>
                <Payment />
                <button v-if="checkoutStore.form.errors.description || checkoutStore.form.errors.title" @click="checkoutStore.submitSubmission(checkoutStore.form); emit('close');" type="submit" class="w-full flex items-center justify-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition mt-4">
                    <i class="fa fa-paper-plane"></i> Submit
                </button>
                
                <!-- used has a hack to close the modal after submission -->
                <button id="closeSubmissionModal" @click="emit('close'); checkoutStore.form.reset();" class="hidden bg-gray-500 text-white px-4 py-2 rounded-lg shadow hover:bg-gray-600 transition">Cancel</button>
            </div>
        </template>
        <!-- <template #footer>
            <div class="flex justify-end">
                <button id="closeSubmissionModal" @click="emit('close'); checkoutStore.form.reset();" class="bg-gray-500 text-white px-4 py-2 rounded-lg shadow hover:bg-gray-600 transition">Cancel</button>
            </div>
        </template> -->
    </DialogModal>
</template>
