<script setup>
import { Head, Link } from '@inertiajs/vue3';
import WelcomeFeed from '@/KickpushComponents/WelcomeFeed.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    day: Object,
    submissions: Object
});

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <Head title="Welcome" />
    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <!-- <img id="background" class="absolute -left-20 top-0 max-w-[877px]" src="https://laravel.com/assets/img/welcome/background.svg" /> -->
        <div class="relative min-h-screen flex flex-col items-center justify-center">
            <div class="relative w-full">
                <header class="grid grid-cols-2 items-center gap-2 py-6 lg:grid-cols-3 px-6 h-fit top-0 sticky bg-white">
                    <div class="flex lg:justify-center lg:col-start-2">
                        <p class="text-black text-2xl">kickpush!</p>
                    </div>
                    <nav v-if="canLogin" class="flex justify-end">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                        >
                            Dashboard
                        </Link>

                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Log in
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                            >
                                Register
                            </Link>
                        </template>
                    </nav>
                </header>

                <main class="">
                    <div class="bg-blue-400 w-full p-32">
                        <div class="text-center grid gap-4">
                            <h1 class="text-4xl font-bold text-black dark:text-white sm:text-5xl lg:text-6xl">
                                Todays topic is <span class="text-[#FF2D20]">{{day.topic}}</span>
                            </h1>
                            <p class="text-lg leading-7">
                                {{day.description}}
                            </p>
                        </div>
                    </div>

                    <WelcomeFeed 
                        :day="day" 
                        :submissions="submissions.data" 
                    />
                </main>

                <footer class="py-16 text-center text-sm text-black dark:text-white/70">
                    kickpush!
                </footer>
            </div>
        </div>
    </div>
</template>
