<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
</script>

<template>
    <AuthBase
        title="Registration Disabled"
        description="Public registration is not available"
    >
        <Head title="Register" />

        <div class="flex flex-col gap-6 text-center">
            <p class="text-muted-foreground">
                User registration is disabled. Only administrators can create new user accounts.
            </p>
            <p class="text-muted-foreground">
                If you need an account, please contact your administrator.
            </p>
            <div class="text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                >
                    Log in
                </TextLink>
            </div>
        </div>
    </AuthBase>
</template>
