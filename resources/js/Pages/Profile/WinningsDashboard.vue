<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Payouts from '@/Pages/Profile/Payouts.vue';

defineProps({
    user: Object,
    usersTotalWinnings: Number,
    hasFreshWin: Boolean,
    transfers: Array,
    payouts: Array,
    usersWinningSubmissions: Array,
});


</script>

<template>
    <AppLayout title="Payouts Dashboard">
    <div class="z-10 sticky top-0 lg:mt-12 mx-auto flex-col lg:flex w-full max-w-2xl p-6 lg:max-w-7xl bg-blue-400 bg-gradient-to-r from-blue-400 to-blue-600 lg:rounded-xl">
        <div class="flex-col lg:flex lg:flex-row">
            <div>
                <h1 class="text-2xl lg:text-4xl font-extrabold text-white">Payouts Dashboard</h1>
                <p class="text-white">Here you can get updates on your wins and payment history</p>
            </div>
            <p class="ml-auto items-center my-auto h-fit w-fit bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm">
                <i class="fa fa-trophy mr-1"></i> ${{ usersTotalWinnings.toFixed(2) }}
            </p>
        </div>
    </div>


        <div class="max-w-6xl mx-auto p-4">

            <div v-if="hasFreshWin" class="bg-green-300 border-2 rounded p-4 mb-8 shadow">
                <h2 class="text-lg">Congratulations! You've won!</h2>
                <p>It usually takes around 14 days for funds to process to your bank account.</p>
                <p v-if="!user.can_accept_payouts">You need to update your payout details so we can pay you! <Link :href="route('profile.paymentsDetails')" class="underline">Payment details</Link>.</p>
            </div>

            <h1 class="text-2xl font-bold mb-2">In progress</h1>
            <p class="text-sm mb-4">Here you will see your winning submissions that are in processing</p>

            <div v-for="submission in usersWinningSubmissions" :key="submission.id" class="mb-8 p-6 border border-gray-300 rounded-xl bg-white/80 shadow flex flex-col gap-2">
                <div class="flex sm:flex-row sm:items-center gap-2 sm:gap-6">
                    <div class="flex flex-col">
                        <Link :href="route('days.show', submission.day.id)" class="text-lg text-blue-600">
                            Day {{ submission.day.id }} 
                            <span class="text-sm text-gray-500">
                                {{ new Date(submission.day.date).toLocaleDateString() }}
                            </span>
                        </Link>
                        <p class="">{{ submission.title }}</p>
                    </div>
                    <span class="ml-auto items-center my-auto bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm">
                        <i class="fa fa-trophy mr-1"></i>
                        ${{ submission.day.prize_pool.total ?? '0.00' }}
                    </span>
                </div>
                <p class="text-gray-700">
                    <span v-if="submission.day.transfer_complete" class="text-green-600">Processing (roughly 7 days left!)</span>
                    <span v-else-if="!user.can_accept_payouts" class="text-red-600">Pending Payout Details</span>
                    <span v-else class="text-yellow-600">Pending (waiting for funds to land with us before we can process them to you)</span>
                </p>
            </div>
            <p v-if="usersWinningSubmissions.length === 0">You have no winning submissions yet. Keep trying!</p>

            <Payouts :payouts="payouts" />

        </div>
        
    </AppLayout>
</template>
