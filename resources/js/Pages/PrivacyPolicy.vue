<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Banner from '@/CustomComponents/Banner.vue';

</script>

<template>
    <AppLayout title="Privacy Policy">
        <Head title="Privacy Policy" />

        <Banner
            :header="'Privacy Policy'"
            :subheader="'Your privacy is important to us. This policy explains how we handle your personal information.'"
        />

        <div class="flex flex-col px-2 sm:px-4 bg-transparent my-8">
            <div class="w-full max-w-4xl bg-white dark:bg-gray-900 rounded-xl shadow-lg p-8 mx-auto">
                <!-- <h1 class="text-3xl mb-4 text-blue-700 dark:text-blue-300">Privacy Policy</h1> -->
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    At kickpush, we are committed to protecting your privacy. This Privacy Policy outlines how we collect, use, and safeguard your personal information when you use our platform.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">Information We Collect</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    We may collect personal information such as your name, email address, and payment details when you register an account or make a purchase on our platform.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">How We Use Your Information</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    We use your personal information to provide and improve our services, process payments, and communicate with you about your account and our platform.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">Data Security</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    We implement appropriate security measures to protect your personal information from unauthorized access, alteration, disclosure, or destruction.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">Your Rights</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    You have the right to access, correct, or delete your personal information. You can also opt-out of receiving promotional communications from us at any time.
                </p>
                <h2 class="text-2xl mb-4 text-blue-600 dark:text-blue-400">Contact Us</h2>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    If you have any questions or concerns about our Privacy Policy, please contact us at privacy@kickpush.com.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
