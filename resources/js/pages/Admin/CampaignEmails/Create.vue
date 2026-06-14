<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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

const initialTemplate = props.templates[0];

const form = useForm({
    template_id: initialTemplate?.id ?? null,
    title: initialTemplate?.name ?? '',
    subject: initialTemplate?.subject ?? '',
    heading: initialTemplate?.heading ?? '',
    body: initialTemplate?.body ?? '',
    cta_label: initialTemplate?.cta_label ?? '',
    cta_url: initialTemplate?.cta_url ?? '',
    recipient_scope: 'all',
    customer_ids: [] as number[],
    additional_emails: [] as string[],
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

watch(() => form.template_id, (templateId) => {
    const template = props.templates.find((item) => item.id === templateId);

    if (!template) {
        return;
    }

    form.title = template.name;
    form.subject = template.subject;
    form.heading = template.heading ?? '';
    form.body = template.body;
    form.cta_label = template.cta_label ?? '';
    form.cta_url = template.cta_url ?? '';
});

const selectedCustomerItems = computed(() => {
    return props.customers.map((customer) => ({
        title: `${customer.name} (${customer.email})`,
        value: customer.id,
    }));
});

const additionalEmailInput = computed({
    get: () => form.additional_emails.join(', '),
    set: (value: string) => {
        form.additional_emails = value
            .split(/[,\n]/)
            .map((email) => email.trim())
            .filter(Boolean);
    },
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
                <p class="text-body-2 text-grey">Choose a template, adjust the message, preview it, and send it to your customers.</p>
            </div>
        </div>

        <v-row>
            <v-col cols="12" md="5">
                <v-card class="mb-4">
                    <v-card-title>Template</v-card-title>
                    <v-card-text>
                        <div class="d-flex flex-column ga-3">
                            <button
                                v-for="template in templates"
                                :key="template.id"
                                type="button"
                                class="text-left"
                                style="border:none; background:transparent; padding:0;"
                                @click="form.template_id = template.id"
                            >
                                <v-card
                                    :variant="form.template_id === template.id ? 'flat' : 'outlined'"
                                    :color="form.template_id === template.id ? 'primary' : undefined"
                                >
                                    <v-card-text>
                                        <div class="d-flex align-start">
                                            <div>
                                                <p class="font-weight-bold mb-1" :class="form.template_id === template.id ? 'text-white' : ''">
                                                    {{ template.name }}
                                                </p>
                                                <p class="text-caption mb-2" :class="form.template_id === template.id ? 'text-white' : 'text-grey'">
                                                    {{ template.subject }}
                                                </p>
                                                <v-chip
                                                    size="small"
                                                    :color="form.template_id === template.id ? 'white' : 'primary'"
                                                    :variant="form.template_id === template.id ? 'flat' : 'outlined'"
                                                >
                                                    {{ template.category.replace('_', ' ') }}
                                                </v-chip>
                                            </div>
                                            <v-spacer />
                                            <v-icon :color="form.template_id === template.id ? 'white' : 'primary'">
                                                {{ form.template_id === template.id ? 'mdi-check-circle' : 'mdi-circle-outline' }}
                                            </v-icon>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </button>
                        </div>
                        <div v-if="form.errors.template_id" class="text-error text-caption mt-2">
                            {{ form.errors.template_id }}
                        </div>
                    </v-card-text>
                </v-card>

                <v-card class="mb-4">
                    <v-card-title>Campaign Setup</v-card-title>
                    <v-card-text>
                        <v-text-field
                            v-model="form.title"
                            label="Campaign title"
                            variant="outlined"
                            class="mb-4"
                            :error-messages="form.errors.title"
                        />

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

                        <v-textarea
                            v-model="additionalEmailInput"
                            label="Additional email addresses"
                            hint="Add comma-separated or one email per line. These do not need to exist as customers."
                            persistent-hint
                            variant="outlined"
                            rows="3"
                            class="mb-4"
                            :error-messages="form.errors.additional_emails"
                        />

                        <div class="d-flex align-center justify-space-between mb-4">
                            <div>
                                <p class="text-caption text-grey mb-1">Recipients</p>
                                <p class="text-h6 font-weight-bold mb-0">{{ recipientCount + form.additional_emails.length }}</p>
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
                    <v-card-title>Campaign Content</v-card-title>
                    <v-card-text>
                        <v-text-field
                            v-model="form.subject"
                            label="Email subject"
                            variant="outlined"
                            class="mb-4"
                            :error-messages="form.errors.subject"
                        />

                        <v-text-field
                            v-model="form.heading"
                            label="Email heading"
                            variant="outlined"
                            class="mb-4"
                            :error-messages="form.errors.heading"
                        />

                        <v-textarea
                            v-model="form.body"
                            label="Email body"
                            variant="outlined"
                            rows="8"
                            class="mb-4"
                            :error-messages="form.errors.body"
                        />

                        <v-row>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.cta_label"
                                    label="CTA label"
                                    variant="outlined"
                                    :error-messages="form.errors.cta_label"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.cta_url"
                                    label="CTA URL"
                                    variant="outlined"
                                    :error-messages="form.errors.cta_url"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

                <v-card class="mb-4">
                    <v-card-title>Preview</v-card-title>
                    <v-card-text>
                        <div class="mb-4">
                            <p class="text-caption text-grey mb-1">Subject</p>
                            <p class="font-weight-bold mb-0">{{ replacePlaceholders(form.subject) }}</p>
                        </div>

                        <div class="rounded-lg pa-6" style="background: linear-gradient(135deg, #f5f8ff 0%, #ffffff 100%); border: 1px solid rgba(11, 99, 246, 0.12);">
                            <p class="text-caption text-primary mb-4">EaseVerifier</p>
                            <h2 class="text-h5 font-weight-bold mb-4">
                                {{ replacePlaceholders(form.heading) }}
                            </h2>
                            <p class="text-body-1" style="white-space: pre-line; line-height: 1.8;">
                                {{ replacePlaceholders(form.body) }}
                            </p>

                            <v-btn
                                v-if="form.cta_label && form.cta_url"
                                color="primary"
                                class="mt-4"
                                variant="flat"
                            >
                                {{ replacePlaceholders(form.cta_label) }}
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
                                <tr>
                                    <td class="font-weight-medium">Extra emails</td>
                                    <td>{{ form.additional_emails.length }}</td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </AdminLayout>
</template>
