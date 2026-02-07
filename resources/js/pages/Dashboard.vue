<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as ordersIndex } from '@/actions/App/Http/Controllers/OrdersController';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col items-center justify-center gap-4 p-6">
            <p class="text-muted-foreground">Welcome to Bolt Portal</p>
            <Button as-child>
                <Link :href="ordersIndex()">View Orders</Link>
            </Button>
        </div>
    </AppLayout>
</template>
