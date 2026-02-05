<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface OrderCountries {
    billing_country_id: number | null;
    billing_country_name: string | null;
    delivery_country_id: number | null;
    delivery_country_name: string | null;
}

interface OrderItem {
    id: number;
    typeId: number;
    itemVariationId: number;
    quantity: string;
    orderItemName: string;
    amounts?: Array<{
        priceGross: number;
        priceNet: number;
        currency: string;
    }>;
}

interface OrderAmount {
    currency: string;
    grossTotal: number;
    netTotal: number;
    vatTotal: number;
}

interface Address {
    id: number;
    name1: string;
    name2: string;
    name3: string;
    address1: string;
    address2: string;
    postalCode: string;
    town: string;
    countryId: number;
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
    sku_count: number;
    has_tablet: boolean;
    charges: number;
    orderItems?: OrderItem[];
    amounts?: OrderAmount[];
    addresses?: Address[];
}

interface KinaraCharge {
    id: number;
    name: string;
    slug: string;
    amount: string;
    tablet_only: boolean;
    charge_type: string;
    is_active: boolean;
}

const props = defineProps<{
    order: Order;
    perOrderCharges: KinaraCharge[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Orders',
        href: '/orders',
    },
    {
        title: `Order #${props.order.id}`,
        href: `/orders/${props.order.id}`,
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

const getItemTypeName = (typeId: number): string => {
    const types: Record<number, string> = {
        1: 'Product',
        2: 'Bundle',
        3: 'Bundle component',
        4: 'Promotional coupon',
        5: 'Gift card',
        6: 'Shipping',
        7: 'Payment surcharge',
        8: 'Gift wrap',
        9: 'Unassigned variation',
        10: 'Deposit',
        11: 'Order',
        12: 'Dunning charge',
        13: 'Set',
        14: 'Set component',
        15: 'Order property',
    };

    return types[typeId] ?? `Type ${typeId}`;
};

const productItems = props.order.orderItems?.filter(
    (item) => item.typeId === 1,
) ?? [];

const otherItems = props.order.orderItems?.filter(
    (item) => item.typeId !== 1,
) ?? [];

const orderAmount = props.order.amounts?.[0];
</script>

<template>
    <Head :title="`Order #${order.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Order #{{ order.id }}</h1>
                    <p class="text-muted-foreground">
                        {{ formatDate(order.createdAt) }}
                    </p>
                </div>
                <Link href="/orders">
                    <Button variant="outline">Back to Orders</Button>
                </Link>
            </div>

            <!-- Order Summary Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Order Type</CardDescription>
                        <CardTitle class="text-xl">
                            {{ getOrderTypeName(order.typeId) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Status</CardDescription>
                        <CardTitle class="text-xl">
                            {{ order.statusName ?? order.statusId }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>SKUs</CardDescription>
                        <CardTitle class="text-xl">
                            {{ order.sku_count }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Kinara Charges</CardDescription>
                        <CardTitle class="text-xl">
                            {{ formatCurrency(order.charges) }}
                            <Badge v-if="order.has_tablet" class="ml-2">
                                Tablet
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <!-- Main Content -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Order Details -->
                <div class="lg:col-span-2 space-y-4">
                    <!-- Products -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Products</CardTitle>
                            <CardDescription>
                                {{ productItems.length }} product(s) in this order
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Variation ID</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Qty</TableHead>
                                        <TableHead>Price</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="item in productItems"
                                        :key="item.id"
                                    >
                                        <TableCell class="font-mono">
                                            {{ item.itemVariationId }}
                                        </TableCell>
                                        <TableCell>
                                            {{ item.orderItemName }}
                                        </TableCell>
                                        <TableCell>
                                            {{ item.quantity }}
                                        </TableCell>
                                        <TableCell>
                                            {{
                                                item.amounts?.[0]
                                                    ? formatCurrency(
                                                          item.amounts[0].priceGross,
                                                          item.amounts[0].currency,
                                                      )
                                                    : '-'
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <!-- Other Items -->
                    <Card v-if="otherItems.length > 0">
                        <CardHeader>
                            <CardTitle>Other Items</CardTitle>
                            <CardDescription>
                                Shipping, fees, and other charges
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Price</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="item in otherItems"
                                        :key="item.id"
                                    >
                                        <TableCell>
                                            <Badge variant="outline">
                                                {{ getItemTypeName(item.typeId) }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {{ item.orderItemName }}
                                        </TableCell>
                                        <TableCell>
                                            {{
                                                item.amounts?.[0]
                                                    ? formatCurrency(
                                                          item.amounts[0].priceGross,
                                                          item.amounts[0].currency,
                                                      )
                                                    : '-'
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <!-- Order Totals -->
                    <Card v-if="orderAmount">
                        <CardHeader>
                            <CardTitle>Order Total</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Net</span>
                                    <span>
                                        {{
                                            formatCurrency(
                                                orderAmount.netTotal,
                                                orderAmount.currency,
                                            )
                                        }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">VAT</span>
                                    <span>
                                        {{
                                            formatCurrency(
                                                orderAmount.vatTotal,
                                                orderAmount.currency,
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="flex justify-between border-t pt-2 font-bold"
                                >
                                    <span>Gross</span>
                                    <span>
                                        {{
                                            formatCurrency(
                                                orderAmount.grossTotal,
                                                orderAmount.currency,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Kinara Charges -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Kinara Charges</CardTitle>
                            <CardDescription>
                                Charges applied to this order
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <div
                                    v-for="charge in perOrderCharges"
                                    :key="charge.id"
                                    class="flex justify-between"
                                    :class="{
                                        'text-muted-foreground':
                                            charge.tablet_only && !order.has_tablet,
                                    }"
                                >
                                    <span class="text-sm">
                                        {{ charge.name }}
                                        <Badge
                                            v-if="charge.tablet_only"
                                            variant="secondary"
                                            class="ml-1 text-xs"
                                        >
                                            Tablet
                                        </Badge>
                                    </span>
                                    <span
                                        v-if="!charge.tablet_only || order.has_tablet"
                                    >
                                        {{ formatCurrency(parseFloat(charge.amount)) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">-</span>
                                </div>
                                <div
                                    class="flex justify-between border-t pt-2 font-bold"
                                >
                                    <span>Total</span>
                                    <span>{{ formatCurrency(order.charges) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Delivery Address -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Delivery Country</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p>{{ order.countries.delivery_country_name ?? 'Unknown' }}</p>
                        </CardContent>
                    </Card>

                    <!-- Billing Address -->
                    <Card v-if="order.countries.billing_country_name">
                        <CardHeader>
                            <CardTitle>Billing Country</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p>{{ order.countries.billing_country_name }}</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
