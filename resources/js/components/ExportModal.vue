<script setup lang="ts">
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    open: boolean;
    year: number;
    month: number;
    monthLabel: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const warehouseWorkersHours = ref<number>(0);
const warehouseWorkersRate = ref<number>(50);
const inbound = ref<number>(0);
const palletStorage = ref<number>(0);
const returns = ref<number>(0);

const isExporting = ref(false);

const handleExport = async () => {
    isExporting.value = true;

    try {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/orders/export';
        form.style.display = 'none';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const fields = {
            _token: csrfToken,
            year: props.year.toString(),
            month: props.month.toString(),
            warehouse_workers_hours: warehouseWorkersHours.value.toString(),
            warehouse_workers_rate: warehouseWorkersRate.value.toString(),
            inbound: inbound.value.toString(),
            pallet_storage: palletStorage.value.toString(),
            returns: returns.value.toString(),
        };

        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        emit('update:open', false);
    } finally {
        isExporting.value = false;
    }
};

const warehouseTotal = () => {
    return (warehouseWorkersHours.value * warehouseWorkersRate.value).toFixed(2);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle>Export Reference Sheet</DialogTitle>
                <DialogDescription>
                    Export orders for {{ monthLabel }} to Excel. Enter the variable charges below.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-4">
                <h4 class="font-semibold text-sm">Variable Charges</h4>

                <!-- Warehouse Workers -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Warehouse workers</Label>
                    <div class="col-span-3 flex gap-2 items-center">
                        <Input
                            v-model.number="warehouseWorkersHours"
                            type="number"
                            min="0"
                            step="0.5"
                            placeholder="Hours"
                            class="w-24"
                        />
                        <span class="text-sm text-muted-foreground">hrs @</span>
                        <Input
                            v-model.number="warehouseWorkersRate"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="Rate"
                            class="w-20"
                        />
                        <span class="text-sm text-muted-foreground">= {{ warehouseTotal() }}</span>
                    </div>
                </div>

                <!-- Inbound -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Inbound</Label>
                    <div class="col-span-3">
                        <Input
                            v-model.number="inbound"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            class="w-32"
                        />
                    </div>
                </div>

                <!-- Pallet Storage -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Pallet storage</Label>
                    <div class="col-span-3">
                        <Input
                            v-model.number="palletStorage"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            class="w-32"
                        />
                    </div>
                </div>

                <!-- Returns -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Returns</Label>
                    <div class="col-span-3">
                        <Input
                            v-model.number="returns"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            class="w-32"
                        />
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">
                    Cancel
                </Button>
                <Button @click="handleExport" :disabled="isExporting">
                    {{ isExporting ? 'Exporting...' : 'Export to Excel' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
