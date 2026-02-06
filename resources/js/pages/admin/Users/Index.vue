<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { UserPlus, Pencil, UserX, UserCheck, ShieldCheck } from 'lucide-vue-next';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/admin/users';
import { type BreadcrumbItem, type User } from '@/types';

type AdminUser = User & {
    is_admin: boolean;
    deleted_at: string | null;
    two_factor_confirmed_at: string | null;
};

type Props = {
    users: AdminUser[];
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'User Management',
        href: index().url,
    },
];

const handleDeactivate = (user: AdminUser) => {
    if (confirm(`Are you sure you want to deactivate ${user.name}?`)) {
        router.delete(UserController.destroy(user.id).url);
    }
};

const handleRestore = (user: AdminUser) => {
    router.post(UserController.restore(user.id).url);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="User Management" />

        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <Heading
                    title="User Management"
                    description="Manage users and their access"
                />
                <Button as-child>
                    <Link :href="UserController.create().url">
                        <UserPlus class="mr-2 h-4 w-4" />
                        Add User
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Users</CardTitle>
                    <CardDescription>
                        {{ users.length }} user(s) in the system
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="divide-y">
                        <div
                            v-for="user in users"
                            :key="user.id"
                            class="flex items-center justify-between py-4"
                            :class="{ 'opacity-50': user.deleted_at }"
                        >
                            <div class="flex items-center gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ user.name }}</span>
                                        <Badge v-if="user.is_admin" variant="secondary">
                                            <ShieldCheck class="mr-1 h-3 w-3" />
                                            Admin
                                        </Badge>
                                        <Badge v-if="user.deleted_at" variant="destructive">
                                            Deactivated
                                        </Badge>
                                        <Badge v-if="user.two_factor_confirmed_at" variant="outline">
                                            2FA
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ user.email }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="UserController.edit(user.id).url">
                                        <Pencil class="mr-1 h-3 w-3" />
                                        Edit
                                    </Link>
                                </Button>
                                <Button
                                    v-if="user.deleted_at"
                                    variant="outline"
                                    size="sm"
                                    @click="handleRestore(user)"
                                >
                                    <UserCheck class="mr-1 h-3 w-3" />
                                    Restore
                                </Button>
                                <Button
                                    v-else
                                    variant="destructive"
                                    size="sm"
                                    @click="handleDeactivate(user)"
                                >
                                    <UserX class="mr-1 h-3 w-3" />
                                    Deactivate
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
