<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { loadConnectAndInitialize } from '@stripe/connect-js';
import { onMounted } from 'vue';


defineProps({
    sessions: Array,
});


const fetchClientSecret = async () => {
  // Fetch the AccountSession client secret
  const response = await axios.get('/api/account_session');
  if (response.status !== 200) {
    // Handle errors on the client side here
    const {error} = response.data;
    console.error('An error occurred: ', error);
    document.querySelector('#error').removeAttribute('hidden');
    return undefined;
  } else {
    const {client_secret: clientSecret} = response.data;
    document.querySelector('#error').setAttribute('hidden', '');
    return clientSecret;
  }
}

const stripeConnectInstance = loadConnectAndInitialize({
    // This is your test publishable API key.
    publishableKey: import.meta.env.VITE_STRIPE_KEY,
    fetchClientSecret: fetchClientSecret,
  });


const onboardingComponent = stripeConnectInstance.create("account-onboarding");
onMounted(() => {

    onboardingComponent.setCollectionOptions({
        requirements: {
            exclude: [
                'business_details', 
                'business_type', 

                'business_profile', 
                'business_profile.url',
                'business_profile.mcc',
                'business_profile.product_description',
            ],
        },
    });

    const container = document.getElementById("payments-dashboard");
    container.appendChild(onboardingComponent);
});

</script>

<template>
    <AppLayout title="Profile">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Payment Details
            </h2>
        </template>

        <div id="payments-dashboard" class="max-w-6xl mx-auto p-4"></div>
        <div id="error" hidden>Something went wrong!</div>
    </AppLayout>
</template>
