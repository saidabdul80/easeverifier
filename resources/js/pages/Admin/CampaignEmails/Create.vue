<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

type Template = {
    id: number;
    name: string;
    category: string;
    subject: string;
    heading?: string | null;
    body: string;
    cta_label?: string | null;
    cta_url?: string | null;
};

const props = defineProps<{
    templates: Template[];
    customers: Array<{ id: number; name: string; email: string; company_name?: string | null }>;
    counts: { all: number };
    previewCustomer?: {
        name: string;
        email: string;
        company_name?: string | null;
        wallet_balance: number;
    } | null;
    availablePlaceholders: string[];
    appUrl: string;
    supportEmail: string;
    supportName: string;
}>();

const form = useForm({
    template_id: props.templates[0]?.id ?? null,
    recipient_scope: 'all',
    customer_ids: [] as number[],
});

const sampleCustomer = computed(() => props.previewCustomer ?? {
    name: 'Customer Name',
    email: 'customer@example.com',
    company_name: 'Sample Company',
    wallet_balance: 0,
});

const selectedTemplate = computed(() => {
    return props.templates.find((template) => template.id === form.template_id) ?? props.templates[0];
});

const recipientCount = computed(() => {
    return form.recipient_scope === 'selected' ? form.customer_ids.length : props.counts.all;
});

const replacePlaceholders = (value?: string | null) => {
    if (!value) return '';

    const firstName = sampleCustomer.value.name.split(' ')[0] || sampleCustomer.value.name;

    return value
        .replaceAll('{{customer_name}}', sampleCustomer.value.name)
        .replaceAll('{{first_name}}', firstName)
        .replaceAll('{{company_name}}', sampleCustomer.value.company_name || 'your team')
        .replaceAll('{{email}}', sampleCustomer.value.email)
        .replaceAll('{{phone}}', 'N/A')
        .replaceAll('{{joined_date}}', new Date().toLocaleDateString())
        .replaceAll('{{wallet_balance}}', new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(sampleCustomer.value.wallet_balance || 0))
        .replaceAll('{{dashboard_url}}', `${props.appUrl}/customer`)
        .replaceAll('{{services_url}}', `${props.appUrl}/services`)
        .replaceAll('{{pricing_url}}', `${props.appUrl}/pricing`)
        .replaceAll('{{api_docs_url}}', `${props.appUrl}/customer/api/documentation`)
        .replaceAll('{{support_email}}', props.supportEmail)
        .replaceAll('{{support_name}}', props.supportName);
};

watch(() => form.recipient_scope, (scope) => {
    if (scope !== 'selected') {
        form.customer_ids = [];
    }
});

const selectedCustomerItems = computed(() => {
    return props.customers.map((customer) => ({
        title: `${customer.name} (${customer.email})`,
        value: customer.id,
    }));
});

const submit = () => {
    form.post('/admin/campaign-emails');
};
</script>

<template>
    <Head title="New Campaign Email - Admin" />

    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <v-btn variant="text" prepend-icon="mdi-arrow-left" href="/admin/campaign-emails" class="mb-2">
                    Back
                </v-btn>
                <h1 class="text-h4 font-weight-bold mb-1">New Campaign Email</h1>
                <p class="text-body-2 text-grey">Choose a fixed system template, preview it, and send it without editing.</p>
            </div>
        </div>

        <v-row>
            <v-col cols="12" md="5">
                <v-card class="mb-4">
                    <v-card-title>Send Setup</v-card-title>
                    <v-card-text>
                        <v-select
                            v-model="form.template_id"
                            :items="templates"
                            item-title="name"
                            item-value="id"
                            label="Template"
                            variant="outlined"
                            class="mb-4"
                            :error-messages="form.errors.template_id"
                        >
                            <template #item="{ props: itemProps, item }">
                                <v-list-item v-bind="itemProps" :title="item.raw.name" :subtitle="item.raw.subject" />
                            </template>
                        </v-select>

                        <v-radio-group v-model="form.recipient_scope" inline class="mb-4">
                            <v-radio label="All customers" value="all" />
                            <v-radio label="Selected customers" value="selected" />
                        </v-radio-group>

                        <v-autocomplete
                            v-if="form.recipient_scope === 'selected'"
                            v-model="form.customer_ids"
                            :items="selectedCustomerItems"
                            label="Customers"
                            variant="outlined"
                            multiple
                            chips
                            closable-chips
                            class="mb-4"
                            :error-messages="form.errors.customer_ids"
                        />

                        <v-alert type="info" variant="tonal" class="mb-4">
                            This message is system-authored and cannot be edited before sending.
                        </v-alert>

                        <div class="d-flex align-center justify-space-between mb-4">
                            <div>
                                <p class="text-caption text-grey mb-1">Recipients</p>
                                <p class="text-h6 font-weight-bold mb-0">{{ recipientCount }}</p>
                            </div>
                            <v-chip color="primary" variant="outlined">
                                {{ selectedTemplate?.category?.replace('_', ' ') }}
                            </v-chip>
                        </div>

                        <v-btn
                            color="primary"
                            block
                            prepend-icon="mdi-send"
                            :loading="form.processing"
                            @click="submit"
                        >
                            Send Campaign Email
                        </v-btn>
                    </v-card-text>
                </v-card>

                <v-card>
                    <v-card-title>Supported Placeholders</v-card-title>
                    <v-card-text>
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip
                                v-for="placeholder in availablePlaceholders"
                                :key="placeholder"
                                size="small"
                                color="grey-darken-1"
                                variant="outlined"
                            >
                                {{ placeholder }}
                            </v-chip>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="7">
                <v-card class="mb-4">
                    <v-card-title>Template Preview</v-card-title>
                    <v-card-text>
                        <div class="mb-4">
                            <p class="text-caption text-grey mb-1">Subject</p>
                            <p class="font-weight-bold mb-0">{{ replacePlaceholders(selectedTemplate?.subject) }}</p>
                        </div>

                        <div class="rounded-lg pa-6" style="background: linear-gradient(135deg, #f5f8ff 0%, #ffffff 100%); border: 1px solid rgba(11, 99, 246, 0.12);">
                            <p class="text-caption text-primary mb-4">EaseVerifier</p>
                            <h2 class="text-h5 font-weight-bold mb-4">
                                {{ replacePlaceholders(selectedTemplate?.heading) }}
                            </h2>
                            <p class="text-body-1" style="white-space: pre-line; line-height: 1.8;">
                                {{ replacePlaceholders(selectedTemplate?.body) }}
                            </p>

                            <v-btn
                                v-if="selectedTemplate?.cta_label && selectedTemplate?.cta_url"
                                color="primary"
                                class="mt-4"
                                variant="flat"
                            >
                                {{ replacePlaceholders(selectedTemplate?.cta_label) }}
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card>
                    <v-card-title>Template Details</v-card-title>
                    <v-card-text>
                        <v-table density="comfortable">
                            <tbody>
                                <tr>
                                    <td class="font-weight-medium">Template</td>
                                    <td>{{ selectedTemplate?.name }}</td>
                                </tr>
                                <tr>
                                    <td class="font-weight-medium">Category</td>
                                    <td>{{ selectedTemplate?.category?.replace('_', ' ') }}</td>
                                </tr>
                                <tr>
                                    <td class="font-weight-medium">Preview contact</td>
                                    <td>{{ sampleCustomer.name }} ({{ sampleCustomer.email }})</td>
                                </tr>
                                <tr>
                                    <td class="font-weight-medium">Delivery scope</td>
                                    <td>{{ form.recipient_scope === 'selected' ? 'Selected customers only' : 'All active customers' }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </AdminLayout>
</template>
