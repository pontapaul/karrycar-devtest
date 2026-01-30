<template>

    <Head title="Shipments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <h1 class="text-2xl font-semibold leading-tight">
                Shipment #{{ shipment.id }}:
                {{ shipment.from }} -> {{ shipment.to }}
            </h1>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <h2 class="text-xl font-semibold mb-2">General Information</h2>
                    <p><strong>ID:</strong> {{ shipment.id }}</p>
                    <p><strong>TEAM:</strong> {{ shipment.team.name }}</p>
                    <p><strong>From:</strong> {{ shipment.from }}</p>
                    <p><strong>To:</strong> {{ shipment.to }}</p>
                </div>
            </div>

            <hr>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <h2 class="text-xl font-semibold mb-2">Start Referents</h2>
                    <ul class="list-disc list-inside">
                        <li v-for="referent in shipment.referents.filter(r => r.pivot.scope === 'start')"
                            :key="referent.id">
                            {{ referent.name }}
                            <br>
                            ({{ referent.email }})
                        </li>
                    </ul>
                </div>
                <div>
                    <ReferentForm :shipment-id="shipment.id" scope="start" />
                </div>
            </div>

            <hr>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <h2 class="text-xl font-semibold mb-2">End Referents</h2>
                    <ul class="list-disc list-inside">
                        <li v-for="referent in shipment.referents.filter(r => r.pivot.scope === 'end')"
                            :key="referent.id">
                            {{ referent.name }}
                            <br>
                            ({{ referent.email }})
                        </li>
                    </ul>
                </div>
                <div>
                    <ReferentForm :shipment-id="shipment.id" scope="end" />
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReferentForm from '@/components/ReferentForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const props = defineProps({
    shipment: Object,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Shipments',
        href: '/shipments',
    },
    {
        title: props.shipment.from + ' -> ' + props.shipment.to,
        href: '',
    }
];


</script>
