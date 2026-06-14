<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

defineProps<{
    templates: Array<{
        id: number;
        key: string;
        name: string;
        category: string;
        subject: string;
        heading?: string | null;
        body: string;
        cta_label?: string | null;
    }>;
    campaigns: {
        data: Array<{
            id: number;
            title: string;
            recipient_scope: string;
            subject: string;
            status: string;
            total_recipients: number;
            sent_count: number;
            failed_count: number;
            sent_at?: string | null;
            created_at?: string | null;
            template?: { name: string; category: string } | null;
            admin?: { name: string } | null;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: {
        total_campaigns: number;
        total_sent: number;
        total_failed: number;
        customer_count: number;
    };
}>();

const formatDate = (value?: string | null) => value ? new Date(value).toLocaleString() : '-';

const statusColor = (status: string) => {
    if (status === 'sent') return 'success';
    if (status === 'partial') return 'warning';
    if (status === 'failed') return 'error';

    return 'grey';
};

const scopeLabel = (scope: string) => scope === 'selected' ? 'Selected customers' : 'All customers';
</script>

<template>
    <Head title="Campaign Emails - Admin" />

    <AdminLayout :user="$page.props.auth.user">
        <div class="d-flex align-center mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold mb-1">Campaign Emails</h1>
                <p class="text-body-2 text-grey">Send fixed system templates to all customers or a selected list.</p>
            </div>
            <v-spacer />
            <v-btn color="primary" prepend-icon="mdi-send-outline" href="/admin/campaign-emails/create">
                New Campaign
            </v-btn>
        </div>

        <v-row class="mb-2">
            <v-col cols="12" md="3">
                <v-card>
                    <v-card-text>
                        <p class="text-caption text-grey mb-2">Total Campaigns</p>
                        <p class="text-h4 font-weight-bold mb-0">{{ stats.total_campaigns }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="3">
                <v-card>
                    <v-card-text>
                        <p class="text-caption text-grey mb-2">Emails Sent</p>
                        <p class="text-h4 font-weight-bold text-success mb-0">{{ stats.total_sent }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="3">
                <v-card>
                    <v-card-text>
                        <p class="text-caption text-grey mb-2">Delivery Failures</p>
                        <p class="text-h4 font-weight-bold text-error mb-0">{{ stats.total_failed }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="3">
                <v-card color="primary">
                    <v-card-text class="text-white">
                        <p class="text-caption opacity-80 mb-2">Reachable Customers</p>
                        <p class="text-h4 font-weight-bold mb-0">{{ stats.customer_count }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-card class="mb-6">
            <v-card-title>System Templates</v-card-title>
            <v-card-text>
                <v-row>
                    <v-col v-for="template in templates" :key="template.id" cols="12" md="6" lg="4">
                        <v-card variant="outlined" class="h-100">
                            <v-card-text>
                                <div class="d-flex align-center mb-3">
                                    <div>
                                        <p class="font-weight-bold mb-1">{{ template.name }}</p>
                                        <p class="text-caption text-grey mb-0">{{ template.subject }}</p>
                                    </div>
                                    <v-spacer />
                                    <v-chip size="small" color="primary" variant="outlined">
                                        {{ template.category.replace('_', ' ') }}
                                    </v-chip>
                                </div>

                                <p class="text-body-2 text-grey-darken-1 mb-3" style="min-height: 72px;">
                                    {{ template.body.slice(0, 140) }}{{ template.body.length > 140 ? '...' : '' }}
                                </p>

                                <div class="d-flex align-center">
                                    <v-icon size="18" color="primary" class="mr-2">mdi-email-outline</v-icon>
                                    <span class="text-caption text-grey">
                                        {{ template.cta_label || 'No CTA button' }}
                                    </span>
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <v-card>
            <v-card-title>Recent Campaigns</v-card-title>
            <v-card-text>
                <v-table density="comfortable">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Recipients</th>
                            <th>Status</th>
                            <th>Template</th>
                            <th>Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="campaign in campaigns.data" :key="campaign.id">
                            <td>
                                <div>
                                    <p class="font-weight-medium mb-1">{{ campaign.title }}</p>
                                    <p class="text-caption text-grey mb-0">{{ campaign.subject }}</p>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <p class="mb-1">{{ scopeLabel(campaign.recipient_scope) }}</p>
                                    <p class="text-caption text-grey mb-0">
                                        {{ campaign.sent_count }}/{{ campaign.total_recipients }} delivered
                                        <span v-if="campaign.failed_count">, {{ campaign.failed_count }} failed</span>
                                    </p>
                                </div>
                            </td>
                            <td>
                                <v-chip :color="statusColor(campaign.status)" size="small">
                                    {{ campaign.status }}
                                </v-chip>
                            </td>
                            <td>
                                <div>
                                    <p class="mb-1">{{ campaign.template?.name || '-' }}</p>
                                    <p class="text-caption text-grey mb-0">{{ campaign.template?.category?.replace('_', ' ') || '' }}</p>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <p class="mb-1">{{ formatDate(campaign.sent_at || campaign.created_at) }}</p>
                                    <p class="text-caption text-grey mb-0">By {{ campaign.admin?.name || '-' }}</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!campaigns.data.length">
                            <td colspan="5" class="text-center py-6 text-grey">
                                No campaign emails have been sent yet.
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card-text>
        </v-card>
    </AdminLayout>
</template>
