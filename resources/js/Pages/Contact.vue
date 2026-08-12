<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Banner from '@/KickpushComponents/Banner.vue';
import { ref } from 'vue';
import { toast } from 'vue3-toastify';

const form = ref({
    name: '',
    email: '',
    message: '',
});

async function submitContact() {
    try {
        const response = await axios.post(`/contact/submit`, form.value);
        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            form.name = '';
            form.email = '';
            form.message = '';
        }
    } catch (error) {
        if(error.response.data.errors){

            toast.error(error.response.data.message, {
                autoClose: 1000,
            });
        }
    }
}
</script>

<template>
    <AppLayout title="Contact">
        <Head title="Contact" />
        <Banner
            :header="'Contact Us'"
            :subheader="'We appreciate your feedback!'"
        />
        <div class="flex flex-col px-2 sm:px-4 bg-transparent my-8">
            <div class="w-full max-w-4xl bg-white dark:bg-gray-900 rounded-xl shadow-lg p-8 mx-auto">
                <h1 class="text-3xl font-bold mb-4 text-blue-700 dark:text-blue-300">Feedback time!</h1>

                <p class="mb-4">
                    If you hate the idea, let us know. If you love the idea, let us know. We appreciate all feedback, bug reports, feature requests, or general comments about kickpush.
                    <br />
                    Remember to be gentle if it's criticism!
                </p>
                <form @submit.prevent="submitContact" class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                        <input v-model="form.name" id="name" type="text" required class="w-full rounded-lg border border-gray-300 p-2 text-gray-800 dark:text-gray-900 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input v-model="form.email" id="email" type="email" required class="w-full rounded-lg border border-gray-300 p-2 text-gray-800 dark:text-gray-900 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message</label>
                        <textarea v-model="form.message" id="message" rows="5" required class="w-full rounded-lg border border-gray-300 p-2 text-gray-800 dark:text-gray-900 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition resize-none"></textarea>
                    </div>
                    <button type="submit" class="w-full flex items-center justify-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>