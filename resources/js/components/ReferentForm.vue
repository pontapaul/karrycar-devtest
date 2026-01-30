<template>
    <form @submit.prevent="submitForm">
        <Input type="text"
               id="name"
               placeholder="Name"
               v-model="form.name"
               required
               class="mb-2" />

        <Input type="text"
               id="last_name"
               placeholder="Last Name"
               v-model="form.last_name"
               class="mb-2"
               required />

        <Input type="email"
               id="email"
               placeholder="Email"
               v-model="form.email"
               class="mb-2"
               required />

        <Input type="tel"
               id="phone"
               placeholder="Phone"
               v-model="form.phone"
               class="mb-2"
               required />

        <AlertError class="mb-2"
                    v-if="errors?.length"
                    :errors="errors" />

        <Button type="submit"
                variant="outline"
                :disabled="loading">
            {{ loading ? 'Saving...' : 'Save' }}
        </Button>
    </form>
</template>

<script setup lang="ts">
import axios from 'axios';
import { reactive, ref } from 'vue';
import AlertError from '@/components/AlertError.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

const props = defineProps<{
    shipmentId: number | string;
    scope: string;
}>();

const emit = defineEmits<{
    'referent-added': [data: any];
    'error': [error: any];
}>();

const form = reactive({
    name: '',
    last_name: '',
    email: '',
    phone: '',
    scope: props.scope
});

const loading = ref(false);

const errors = ref([]);

const submitForm = async () => {
    loading.value = true;
    try {
        const response = await axios.post(
            `/shipments/${props.shipmentId}/addReferent`,
            form
        );
        emit('referent-added', response.data);
        resetForm();
        //reload page
        window.location.reload();

    } catch (error) {
        errors.value = [error.response.data.message];
        emit('error', error);
    } finally {
        loading.value = false;
    }
};

const resetForm = () => {
    form.name = '';
    form.last_name = '';
    form.email = '';
    form.phone = '';
    errors.value = [];
};
</script>
