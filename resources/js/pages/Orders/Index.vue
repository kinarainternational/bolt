<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import ExportModal from '@/components/ExportModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface OrderCountries {
    billing_country_id: number | null;
    billing_country_name: string | null;
    delivery_country_id: number | null;
    delivery_country_name: string | null;
}

interface Order {
    id: number;
    typeId: number;
    statusId: number;
    statusName?: string;
    createdAt: string;
    updatedAt: string;
    plentyId: number;
    countries: OrderCountries;
    total_quantity: number;
    tablet_count: number;
    charges: number;
    amounts?: Array<{
        currency: string;
        grossTotal: number;
        netTotal: number;
    }>;
}

interface GroupedOrders {
    country_name: string;
    country_id: number | null;
    orders: Order[];
    order_count: number;
    total_gross: number;
    total_quantity: number;
    total_tablets: number;
    total_charges: number;
    currency: string;
}

interface AvailableMonth {
    value: string;
    label: string;
}

interface Filters {
    year: number;
    month: number;
}

interface KinaraCharge {
    id: number;
    name: string;
    slug: string;
    amount: string;
    calculation_basis: string;
    charge_type: string;
    is_active: boolean;
}

interface ApiError {
    message: string;
    type: string;
    retryable: boolean;
}

const props = defineProps<{
    groupedOrders: GroupedOrders[];
    totalOrders: number;
    filters: Filters;
    availableMonths: AvailableMonth[];
    perOrderCharges: KinaraCharge[];
    monthlyCharges: KinaraCharge[];
    monthlyTotal: number;
    error: ApiError | null;
}>();

const selectedMonth = ref(
    `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}`,
);

const expandedCountries = ref<Set<string>>(new Set());

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Orders',
        href: '/orders',
    },
];

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatCurrency = (amount: number, currency: string = 'EUR'): string => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: currency,
    }).format(amount);
};

const getOrderTypeName = (typeId: number): string => {
    const types: Record<number, string> = {
        1: 'Sales Order',
        2: 'Delivery',
        3: 'Returns',
        4: 'Credit Note',
        5: 'Warranty',
        6: 'Repair',
        7: 'Offer',
        8: 'Advance Order',
        9: 'Multi-Order',
        10: 'Multi Credit Note',
        11: 'Multi Delivery',
        12: 'Reorder',
        13: 'Partial Delivery',
        14: 'Subscription',
        15: 'Redistribution',
    };

    return types[typeId] ?? `Type ${typeId}`;
};

const getCalculationBasisLabel = (basis: string): string => {
    const labels: Record<string, string> = {
        flat: 'per order',
        per_item: 'per item',
        per_additional_item: 'per extra item',
        per_tablet: 'per tablet',
    };
    return labels[basis] ?? basis;
};

const toggleCountry = (countryName: string) => {
    if (expandedCountries.value.has(countryName)) {
        expandedCountries.value.delete(countryName);
    } else {
        expandedCountries.value.add(countryName);
    }
};

const isExpanded = (countryName: string): boolean => {
    return expandedCountries.value.has(countryName);
};

const onMonthChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const [year, month] = target.value.split('-');

    router.get(
        '/orders',
        { year, month },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const currentMonthLabel = computed(() => {
    const found = props.availableMonths.find(
        (m) => m.value === selectedMonth.value,
    );
    return found?.label ?? selectedMonth.value;
});

const totalOrderCharges = computed(() => {
    return props.groupedOrders.reduce(
        (sum, group) => sum + group.total_charges,
        0,
    );
});

const grandTotal = computed(() => {
    return totalOrderCharges.value + props.monthlyTotal;
});

const showExportModal = ref(false);
const isRetrying = ref(false);
const flashError = ref<string | null>(null);

const page = usePage();

watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.error) {
            flashError.value = flash.error;
            setTimeout(() => {
                flashError.value = null;
            }, 10000);
        }
    },
    { immediate: true },
);

const retryFetch = () => {
    isRetrying.value = true;
    router.reload({
        preserveState: false,
        onFinish: () => {
            isRetrying.value = false;
        },
    });
};
</script>

<template>
    <Head title="Orders by Country" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header with Filter -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Orders by Country</h1>
                    <p class="text-muted-foreground">
                        {{ totalOrders }} orders in {{ currentMonthLabel }}
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label for="month-filter" class="text-sm font-medium">
                            Month:
                        </label>
                        <select
                            id="month-filter"
                            v-model="selectedMonth"
                            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            @change="onMonthChange"
                        >
                            <option
                                v-for="month in availableMonths"
                                :key="month.value"
                                :value="month.value"
                            >
                                {{ month.label }}
                            </option>
                        </select>
                    </div>
                    <Button
                        v-if="!error && totalOrders > 0"
                        @click="showExportModal = true"
                    >
                        Export Reference Sheet
                    </Button>
                </div>
            </div>

            <!-- Flash Error Alert (from export failures, etc.) -->
            <Card v-if="flashError" class="border-destructive bg-destructive/10">
                <CardContent class="flex items-center justify-between py-4">
                    <div class="flex items-center gap-3">
                        <svg
                            class="h-5 w-5 text-destructive"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <p class="font-medium text-destructive">
                            {{ flashError }}
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" @click="flashError = null">
                        Dismiss
                    </Button>
                </CardContent>
            </Card>

            <!-- API Error Alert -->
            <Card v-if="error" class="border-destructive bg-destructive/10">
                <CardContent class="flex items-center justify-between py-4">
                    <div class="flex items-center gap-3">
                        <svg
                            class="h-5 w-5 text-destructive"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <div>
                            <p class="font-medium text-destructive">
                                {{ error.message }}
                            </p>
                            <p
                                v-if="error.retryable"
                                class="text-sm text-muted-foreground"
                            >
                                This may be a temporary issue. Please try again.
                            </p>
                        </div>
                    </div>
                    <Button
                        v-if="error.retryable"
                        variant="outline"
                        :disabled="isRetrying"
                        @click="retryFetch"
                    >
                        <svg
                            v-if="isRetrying"
                            class="mr-2 h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                        </svg>
                        {{ isRetrying ? 'Retrying...' : 'Retry' }}
                    </Button>
                </CardContent>
            </Card>

            <!-- Summary Cards -->
            <div v-if="!error" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Total Orders</CardDescription>
                        <CardTitle class="text-3xl">
                            {{ totalOrders }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Countries</CardDescription>
                        <CardTitle class="text-3xl">
                            {{ groupedOrders.length }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Order Charges</CardDescription>
                        <CardTitle class="text-3xl">
                            {{ formatCurrency(totalOrderCharges) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Grand Total</CardDescription>
                        <CardTitle class="text-3xl">
                            {{ formatCurrency(grandTotal) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <!-- Charges Reference -->
            <div v-if="!error" class="grid gap-4 md:grid-cols-2">
                <!-- Per-Order Charges -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Per-Order Charges</CardTitle>
                        <CardDescription>Applied to each order</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div
                                v-for="charge in perOrderCharges"
                                :key="charge.id"
                                class="flex items-center justify-between"
                            >
                                <span class="text-sm">
                                    {{ charge.name }}
                                    <Badge
                                        variant="secondary"
                                        class="ml-2 text-xs"
                                    >
                                        {{ getCalculationBasisLabel(charge.calculation_basis) }}
                                    </Badge>
                                </span>
                                <span class="font-medium">
                                    {{ formatCurrency(parseFloat(charge.amount)) }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Monthly Fixed Charges -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">
                            Monthly Fixed Charges
                        </CardTitle>
                        <CardDescription>
                            Fixed fees for {{ currentMonthLabel }} (excluded from
                            8%)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div
                                v-for="charge in monthlyCharges"
                                :key="charge.id"
                                class="flex items-center justify-between"
                            >
                                <span class="text-sm">{{ charge.name }}</span>
                                <span class="font-medium">
                                    {{ formatCurrency(parseFloat(charge.amount)) }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between border-t pt-2 mt-2"
                            >
                                <span class="text-sm font-medium">Total</span>
                                <span class="font-bold">
                                    {{ formatCurrency(monthlyTotal) }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Grouped Orders by Country -->
            <div v-if="!error" class="space-y-4">
                <Card
                    v-for="group in groupedOrders"
                    :key="group.country_name"
                >
                    <Collapsible :open="isExpanded(group.country_name)">
                        <CollapsibleTrigger as-child>
                            <CardHeader
                                class="cursor-pointer hover:bg-muted/50 transition-colors"
                                @click="toggleCountry(group.country_name)"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div>
                                            <CardTitle class="text-lg">
                                                {{ group.country_name }}
                                            </CardTitle>
                                            <CardDescription>
                                                {{ group.order_count }}
                                                {{
                                                    group.order_count === 1
                                                        ? 'order'
                                                        : 'orders'
                                                }}
                                                &middot;
                                                {{ group.total_quantity }} items
                                                &middot;
                                                {{ group.total_tablets }} tablets
                                                &middot;
                                                {{
                                                    formatCurrency(
                                                        group.total_charges,
                                                    )
                                                }}
                                            </CardDescription>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge>
                                            {{ group.order_count }}
                                        </Badge>
                                        <svg
                                            :class="[
                                                'h-4 w-4 transition-transform',
                                                isExpanded(group.country_name)
                                                    ? 'rotate-180'
                                                    : '',
                                            ]"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <polyline
                                                points="6 9 12 15 18 9"
                                            ></polyline>
                                        </svg>
                                    </div>
                                </div>
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Order ID</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Qty</TableHead>
                                            <TableHead>Tablets</TableHead>
                                            <TableHead>Charges</TableHead>
                                            <TableHead>Created</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="order in group.orders"
                                            :key="order.id"
                                        >
                                            <TableCell class="font-medium">
                                                <Link
                                                    :href="`/orders/${order.id}`"
                                                    class="text-primary hover:underline"
                                                >
                                                    {{ order.id }}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {{
                                                        getOrderTypeName(
                                                            order.typeId,
                                                        )
                                                    }}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {{
                                                    order.statusName ??
                                                    order.statusId
                                                }}
                                            </TableCell>
                                            <TableCell>
                                                {{ order.total_quantity }}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    v-if="order.tablet_count > 0"
                                                    variant="default"
                                                >
                                                    {{ order.tablet_count }}
                                                </Badge>
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                >
                                                    -
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {{
                                                    formatCurrency(order.charges)
                                                }}
                                            </TableCell>
                                            <TableCell>
                                                {{ formatDate(order.createdAt) }}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <Card v-if="groupedOrders.length === 0">
                    <CardContent class="py-8 text-center text-muted-foreground">
                        No orders found for {{ currentMonthLabel }}.
                    </CardContent>
                </Card>
            </div>
        </div>

        <ExportModal
            v-model:open="showExportModal"
            :year="filters.year"
            :month="filters.month"
            :month-label="currentMonthLabel"
        />
    </AppLayout>
</template>
