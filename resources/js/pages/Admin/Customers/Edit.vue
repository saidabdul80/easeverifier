<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

const props = defineProps<{
    user: { name: string; email: string };
    customer: any;
}>();

const form = useForm({
    name: props.customer.name,
    email: props.customer.email,
    phone: props.customer.phone || '',
    is_active: props.customer.is_active,
    company_name: props.customer.customer?.company_name || '',
    business_type: props.customer.customer?.business_type || '',
    address: props.customer.customer?.address || '',
    city: props.customer.customer?.city || '',
    state: props.customer.customer?.state || '',
});

const businessTypes = ['Fintech', 'Banking', 'Insurance', 'E-commerce', 'Healthcare', 'Education', 'Government', 'Other'];
const nigerianStates = ['Lagos', 'Abuja', 'Kano', 'Rivers', 'Oyo', 'Kaduna', 'Ogun', 'Enugu', 'Delta', 'Anambra', 'Other'];

const submit = () => {
    form.put(`/admin/customers/${props.customer.id}`);
};
</script>

<template>
    <Head :title="`Edit ${customer.name} - Admin`" />
    <AdminLayout :user="$page.props.auth.user">
        <div class="mb-6">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" :href="`/admin/customers/${customer.id}`" class="mb-2">Back to Customer</v-btn>
            <h1 class="text-h4 font-weight-bold mb-1">Edit Customer</h1>
            <p class="text-body-2 text-grey">Update customer information</p>
        </div>

        <v-card max-width="800">
            <v-card-text>
                <v-form @submit.prevent="submit">
                    <h3 class="text-subtitle-1 font-weight-bold mb-4">Account Information</h3>
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.name" label="Full Name *" variant="outlined" :error-messages="form.errors.name" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.email" label="Email Address *" type="email" variant="outlined" :error-messages="form.errors.email" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.phone" label="Phone Number" variant="outlined" :error-messages="form.errors.phone" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-switch v-model="form.is_active" label="Account Active" color="primary" />
                        </v-col>
                    </v-row>

                    <v-divider class="my-6" />

                    <h3 class="text-subtitle-1 font-weight-bold mb-4">Business Information</h3>
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.company_name" label="Company Name" variant="outlined" :error-messages="form.errors.company_name" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-select v-model="form.business_type" label="Business Type" :items="businessTypes" variant="outlined" :error-messages="form.errors.business_type" />
                        </v-col>
                        <v-col cols="12">
                            <v-textarea v-model="form.address" label="Address" variant="outlined" rows="2" :error-messages="form.errors.address" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field v-model="form.city" label="City" variant="outlined" :error-messages="form.errors.city" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-select v-model="form.state" label="State" :items="nigerianStates" variant="outlined" :error-messages="form.errors.state" />
                        </v-col>
                    </v-row>

                    <div class="d-flex justify-end ga-3 mt-6">
                        <v-btn variant="outlined" :href="`/admin/customers/${customer.id}`">Cancel</v-btn>
                        <v-btn type="submit" color="primary" :loading="form.processing">Update Customer</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </AdminLayout>
</template>

