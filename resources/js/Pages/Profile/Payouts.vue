<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Feed from '@/kickpushComponents/Feed.vue';

defineProps({
    user: Object,
    payouts: Array,
    usersWinningSubmissions: Array,
});


</script>

<template>

    <h1 class="text-2xl font-bold mb-2">Payout History</h1>
    <p class="text-sm mb-4">Here you will see transfers made to you, these typically take around 14 days to process from the day you won. We know, that is way too long!</p>

    <div class="min-w-full bg-white border shadow rounded-lg">
        <table v-if="payouts.length" class="w-full">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b">Date</th>
                    <th class="py-2 px-4 border-b">Amount</th>
                    <th class="py-2 px-4 border-b">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="payout in payouts" :key="payout.id" class="text-center">
                    <td class="py-2 px-4 border-b">{{ new Date(payout.created * 1000).toLocaleDateString() }}</td>
                    <td class="py-2 px-4 border-b">${{ (payout.amount / 100).toFixed(2) }}</td>
                    <td class="py-2 px-4 border-b capitalize">{{ payout.status }}</td>
                </tr>
            </tbody>
        </table>
        <p class="py-2 px-4" v-else>No payment history available.</p>
    </div>
</template>
