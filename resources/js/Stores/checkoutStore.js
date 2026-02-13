import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { loadStripe } from '@stripe/stripe-js';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';


export const useCheckoutStore = defineStore('checkout', () => {
    const token = ref(null)
    const stripe = ref(null)
    const elements = ref(null)
    const checkout = ref(null)
    const clientSecret = ref(null);
    const status = ref('loading');
    const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_KEY);
    // const paid = ref(false);

    const form = useForm({
        title: '',
        description: '',
        // day_id: props.day.id,
        paid: false,
        errors: [],
        token: null
    });

    const fetchClientSecret = async () => {
        await axios.post('/api/payment/initiate')
        .then(response => {
            form.token = response.data.token
            if (response.data.client_secret) {
                clientSecret.value = response.data.client_secret;
                status.value = 'ready';
            } else {
                throw new Error('Failed to retrieve client secret');
            }
        }).catch(error => {
            // throw error
            status.value = 'failed';
        })
    };

    const initializeCheckout = async () => {
        const stripe = await stripePromise;
        checkout.value = await stripe.initEmbeddedCheckout({
            clientSecret: clientSecret.value,
            onComplete: (session) => submitSubmission(form),
        });
        checkout.value.mount('#checkout');
    };

    //submission related
    const submitSubmission = async (form) => {
        try {
            const response = await axios.post(`/submissions/store`, { ...form });

            if (response.data.flashStatus === 'success'){
                toast.success(response.data.message, {
                    autoClose: 1000,
                });

                form.reset();

                checkout.value.destroy();

                // Hack to close modal after submission
                document.getElementById('closeSubmissionModal').click();
                // emit('close', response.data.submission);

            }
        } catch (error) {
            if(error.response.data.errors){
                form.errors = error.response.data.errors;

                toast.error(error.response.data.message, {
                    autoClose: 1000,
                });
            }
        }
    };

    return { token, stripe, elements, checkout, clientSecret, status, stripePromise, form, fetchClientSecret, initializeCheckout, submitSubmission };
});