<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Banner from '@/kickpushComponents/Banner.vue';
import { Link } from '@inertiajs/vue3';

</script>

<template>
    <AppLayout title="Terms of Service">
        <Head title="Terms of Service" />

        <Banner
            :header="'Terms of Service'"
            :subheader="'Please read these terms of service carefully before using our platform.'"
        />

        <div class="flex flex-col px-2 sm:px-4 bg-transparent my-8">
            <div class="w-full max-w-4xl bg-white dark:bg-gray-900 rounded-xl shadow-lg p-8 mx-auto">
                <h1 class="text-3xl mb-4 text-blue-700 dark:text-blue-300">Terms of Service</h1>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    By accessing or using the kickpush platform, you agree to comply with and be bound by these Terms of Service. If you do not agree to these terms, please do not use our platform.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">User Responsibilities</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    You are responsible for maintaining the confidentiality of your account information and for all activities that occur under your account. You agree to use the platform in accordance with all applicable laws and regulations.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">Content Submission</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    By submitting content to the kickpush platform, you grant us a non-exclusive, worldwide, royalty-free license to use, reproduce, modify, and distribute your content in connection with the platform.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">Limitation of Liability</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    kickpush shall not be liable for any direct, indirect, incidental, special, or consequential damages arising out of or in connection with your use of the platform.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">Changes to Terms</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    We reserve the right to modify these Terms of Service at any time. Your continued use of the platform after any changes constitutes your acceptance of the new terms.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">Contact Us</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    If you have any questions or concerns about these Terms of Service, please <Link :href="'/contact'" class="underline">contact us</Link>.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
