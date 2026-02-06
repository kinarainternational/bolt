<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ShieldOff, UserX, UserCheck } from 'lucide-vue-next';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, edit } from '@/routes/admin/users';
import { type BreadcrumbItem, type User } from '@/types';

type AdminUser = User & {
    is_admin: boolean;
    deleted_at: string | null;
    two_factor_confirmed_at: string | null;
};

type Props = {
    user: AdminUser;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'User Management',
        href: index().url,
    },
    {
        title: 'Edit User',
        href: edit(props.user.id).url,
    },
];

const handleDeactivate = () => {
    if (confirm(`Are you sure you want to deactivate ${props.user.name}?`)) {
        router.delete(UserController.destroy(props.user.id).url);
    }
};

const handleRestore = () => {
    router.post(UserController.restore(props.user.id).url);
};

const handleResetTwoFactor = () => {
    if (confirm(`Are you sure you want to reset 2FA for ${props.user.name}? They will need to set it up again.`)) {
        router.post(UserController.resetTwoFactor(props.user.id).url);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${user.name}`" />

        <div class="space-y-6 p-6">
            <Heading
                :title="`Edit ${user.name}`"
                description="Update user information and access"
            />

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- User Details Form -->
                <Card>
                    <CardHeader>
                        <CardTitle>User Details</CardTitle>
                        <CardDescription>
                            Update the user's basic information
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            v-bind="UserController.update.form(user.id)"
                            class="space-y-6"
                            v-slot="{ errors, processing, recentlySuccessful }"
                        >
                            <div class="grid gap-2">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    required
                                    autocomplete="name"
                                    placeholder="Full name"
                                    :default-value="user.name"
                                />
                                <InputError :message="errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    placeholder="Email address"
                                    :default-value="user.email"
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <Separator />

                            <div class="grid gap-2">
                                <Label for="password">New Password (optional)</Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="Leave blank to keep current"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation">Confirm New Password</Label>
                                <Input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="Confirm new password"
                                />
                            </div>

                            <Separator />

                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="is_admin"
                                    name="is_admin"
                                    :value="true"
                                    :default-checked="user.is_admin"
                                />
                                <Label for="is_admin" class="cursor-pointer">
                                    Grant admin privileges
                                </Label>
                            </div>

                            <div class="flex items-center gap-4">
                                <Button :disabled="processing">
                                    Save Changes
                                </Button>
                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p v-show="recentlySuccessful" class="text-sm text-neutral-600">
                                        Saved.
                                    </p>
                                </Transition>
                            </div>
                        </Form>
                    </CardContent>
                </Card>

                <!-- User Actions -->
                <div class="space-y-6">
                    <!-- Two-Factor Authentication -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Two-Factor Authentication</CardTitle>
                            <CardDescription>
                                Manage the user's 2FA settings
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">Status</p>
                                    <Badge v-if="user.two_factor_confirmed_at" variant="default">
                                        Enabled
                                    </Badge>
                                    <Badge v-else variant="outline">
                                        Not enabled
                                    </Badge>
                                </div>
                                <Button
                                    v-if="user.two_factor_confirmed_at"
                                    variant="outline"
                                    @click="handleResetTwoFactor"
                                >
                                    <ShieldOff class="mr-2 h-4 w-4" />
                                    Reset 2FA
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Account Status -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Account Status</CardTitle>
                            <CardDescription>
                                Activate or deactivate this user account
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">Status</p>
                                    <Badge v-if="user.deleted_at" variant="destructive">
                                        Deactivated
                                    </Badge>
                                    <Badge v-else variant="default">
                                        Active
                                    </Badge>
                                </div>
                                <Button
                                    v-if="user.deleted_at"
                                    variant="outline"
                                    @click="handleRestore"
                                >
                                    <UserCheck class="mr-2 h-4 w-4" />
                                    Restore User
                                </Button>
                                <Button
                                    v-else
                                    variant="destructive"
                                    @click="handleDeactivate"
                                >
                                    <UserX class="mr-2 h-4 w-4" />
                                    Deactivate User
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Back to list -->
                    <Button variant="outline" as-child class="w-full">
                        <Link :href="index().url">
                            Back to User List
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
