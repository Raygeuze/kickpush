<script setup>
import { inject } from 'vue';
import { ref, onMounted, onUnmounted } from 'vue';

const utilities = inject('utilities');

const time = ref(utilities.formatTimeLeft());

defineProps({

});

onMounted(() => {
  setInterval(() => {
    time.value = utilities.formatTimeLeft();
  }, 1000)
});

onUnmounted(() => {
  // Clear interval if needed
  clearInterval();
});

</script>

<template>
    <!-- count down till the end of day UTC -->
    <div class=" text-black font-semibold bg-gray-200 px-6 py-2 rounded-md mr-4 flex items-center gap-2">
        <i class="fa-regular fa-clock"></i>
        <span id="countdown-timer">{{ time }}</span>
    </div>
</template>
