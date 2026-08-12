<script setup>
import { ref, onMounted } from "vue"
import { loadStripe } from '@stripe/stripe-js';
import { useCheckoutStore } from '../Stores/checkoutStore'

const props = defineProps({

})

const checkoutStore = useCheckoutStore();

onMounted(async () => {
    await checkoutStore.fetchClientSecret();

    if (checkoutStore.status === 'ready') {
        checkoutStore.initializeCheckout();
    }
});

</script>

<template>

    <div v-if="!paid">
        <h2>Complete your purchase</h2>
        <div v-if="status === 'loading'">
            Loading checkout...
        </div>
        <div v-else-if="status === 'success'">
            Payment successful! You can now show a confirmation message.
        </div>
        <div v-else-if="status === 'failed'">
            Payment failed. Please try again.
        </div>
        <div v-else>
            <div id="checkout">
                <!-- The embedded Stripe form will be mounted here -->
            </div>
        </div>
    </div>
    <div v-else>
        <p>Your payment has been received, you are ready to submit!</p>
    </div>
</template>
