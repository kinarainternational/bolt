<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <!-- Animated left panel -->
        <div
            class="relative hidden h-full overflow-hidden bg-gradient-to-br from-[#34D186] via-[#2ab878] to-[#1e8a5a] lg:flex"
        >
            <!-- Animated floating circles -->
            <div class="absolute inset-0">
                <div class="animate-float-slow absolute top-[10%] left-[10%] h-64 w-64 rounded-full bg-white/10 blur-xl" />
                <div class="animate-float-medium absolute top-[60%] left-[60%] h-48 w-48 rounded-full bg-white/10 blur-xl" />
                <div class="animate-float-fast absolute top-[30%] left-[70%] h-32 w-32 rounded-full bg-white/5 blur-lg" />
                <div class="animate-float-medium absolute top-[70%] left-[20%] h-40 w-40 rounded-full bg-white/10 blur-xl" />
                <div class="animate-float-slow absolute top-[5%] left-[50%] h-24 w-24 rounded-full bg-white/5 blur-lg" />
            </div>

            <!-- Animated SVG pattern -->
            <svg class="absolute inset-0 h-full w-full opacity-20" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <circle cx="20" cy="20" r="1" fill="white" class="animate-pulse" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>

            <!-- Animated wave SVGs -->
            <svg class="absolute bottom-0 left-0 w-full" viewBox="0 0 1440 320" preserveAspectRatio="none">
                <path
                    class="animate-wave-slow"
                    fill="rgba(255,255,255,0.1)"
                    d="M0,192L48,197.3C96,203,192,213,288,229.3C384,245,480,267,576,250.7C672,235,768,181,864,181.3C960,181,1056,235,1152,234.7C1248,235,1344,181,1392,154.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"
                />
                <path
                    class="animate-wave-medium"
                    fill="rgba(255,255,255,0.05)"
                    d="M0,256L48,240C96,224,192,192,288,181.3C384,171,480,181,576,197.3C672,213,768,235,864,224C960,213,1056,171,1152,165.3C1248,160,1344,192,1392,208L1440,224L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"
                />
            </svg>

            <!-- Floating geometric shapes -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="animate-spin-slow absolute top-[20%] left-[15%] h-16 w-16 rounded-lg border-2 border-white/20" />
                <div class="animate-spin-reverse absolute top-[50%] left-[75%] h-12 w-12 rounded-full border-2 border-white/15" />
                <div class="animate-bounce-slow absolute top-[75%] left-[40%] h-8 w-8 rotate-45 border-2 border-white/20" />
                <div class="animate-spin-slow absolute top-[10%] left-[80%] h-20 w-20 rounded-lg border border-white/10" />
                <div class="animate-bounce-slow absolute top-[40%] left-[25%] h-6 w-6 rounded-full bg-white/10" />
            </div>

        </div>

        <!-- Right panel with form -->
        <div class="flex flex-col justify-between lg:p-8 py-8">
            <div></div>
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-2xl font-bold tracking-tight">
                        Bolt Portal
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="title">
                        {{ title }}
                    </p>
                </div>
                <slot />
            </div>
            <div class="text-center text-sm text-muted-foreground">
                Powered by
                <a
                    href="https://kinarainternational.com/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline underline-offset-4 hover:text-foreground transition-colors"
                >
                    Kinara International
                </a>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes float-slow {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(30px, -30px) scale(1.1); }
}

@keyframes float-medium {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-20px, 20px) scale(1.05); }
}

@keyframes float-fast {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(15px, -15px); }
}

@keyframes wave-slow {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(-25px); }
}

@keyframes wave-medium {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(25px); }
}

@keyframes spin-slow {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes spin-reverse {
    from { transform: rotate(360deg); }
    to { transform: rotate(0deg); }
}

@keyframes bounce-slow {
    0%, 100% { transform: translateY(0) rotate(45deg); }
    50% { transform: translateY(-20px) rotate(45deg); }
}

@keyframes pulse-slow {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
}

.animate-float-slow { animation: float-slow 8s ease-in-out infinite; }
.animate-float-medium { animation: float-medium 6s ease-in-out infinite; }
.animate-float-fast { animation: float-fast 4s ease-in-out infinite; }
.animate-wave-slow { animation: wave-slow 8s ease-in-out infinite; }
.animate-wave-medium { animation: wave-medium 6s ease-in-out infinite; }
.animate-spin-slow { animation: spin-slow 20s linear infinite; }
.animate-spin-reverse { animation: spin-reverse 15s linear infinite; }
.animate-bounce-slow { animation: bounce-slow 3s ease-in-out infinite; }
.animate-pulse-slow { animation: pulse-slow 4s ease-in-out infinite; }
</style>
