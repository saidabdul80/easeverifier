<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    customer: {
        id: number;
        name: string;
        email: string;
        company: string | null;
        account_type: string;
    };
    submission: {
        id: number;
        status: string;
        submitted_at: string;
        reviewed_at: string | null;
        reviewer: string | null;
        rejection_reason: string | null;
        questions: {
            title: string;
            fields: { key: string; label: string; type: string }[];
        }[];
        answers: Record<string, string | boolean | null>;
        documents: Record<string, { name: string; label: string; url: string }>;
    };
}>();
const form = useForm({ decision: 'approve', rejection_reason: '' });
const rejectDialog = ref(false);
const approveDialog = ref(false);
const statusColor = computed(
    () =>
        ({ pending: 'warning', approved: 'success', rejected: 'error' })[
            props.submission.status
        ] || 'grey',
);
const formatAnswer = (value: unknown, type: string) =>
    type === 'boolean' && (value === true || value === 1 || value === '1')
        ? 'Yes'
        : type === 'boolean' &&
            (value === false || value === 0 || value === '0')
          ? 'No'
          : value || 'Not provided';
const review = (decision: 'approve' | 'reject') => {
    if (form.processing) return;
    form.decision = decision;
    form.post(`/admin/kyc/${props.submission.id}/review`, {
        preserveScroll: true,
        onSuccess: () => {
            rejectDialog.value = false;
            approveDialog.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`KYC Review - ${customer.name}`" />
    <AdminLayout :user="$page.props.auth.user">
        <v-btn
            href="/admin/kyc"
            variant="text"
            prepend-icon="mdi-arrow-left"
            class="mb-3"
            >KYC Reviews</v-btn
        >
        <div class="review-heading">
            <div>
                <h1>KYC Review</h1>
                <p class="text-body-2">
                    {{ customer.company || customer.name }}
                    <span class="text-grey">/ {{ customer.email }}</span>
                </p>
                <p class="text-caption text-grey mt-1">
                    Submitted
                    {{ new Date(submission.submitted_at).toLocaleString() }}
                </p>
            </div>
            <v-chip :color="statusColor" variant="tonal" size="small">{{
                submission.status
            }}</v-chip>
        </div>
        <v-alert
            v-if="form.errors.decision"
            type="error"
            variant="tonal"
            class="mb-5"
            >{{ form.errors.decision }}</v-alert
        >
        <v-alert
            v-if="submission.reviewed_at"
            :type="submission.status === 'approved' ? 'success' : 'warning'"
            variant="tonal"
            class="mb-5"
            >Reviewed by {{ submission.reviewer || 'Admin' }} on
            {{ new Date(submission.reviewed_at).toLocaleString() }}.<span
                v-if="submission.rejection_reason"
            >
                {{ submission.rejection_reason }}</span
            ></v-alert
        >
        <div class="review-body">
            <section
                v-for="section in submission.questions"
                :key="section.title"
                class="review-section"
            >
                <h2>{{ section.title }}</h2>
                <dl class="answer-grid">
                    <div v-for="field in section.fields" :key="field.key">
                        <dt>{{ field.label }}</dt>
                        <dd>
                            {{
                                formatAnswer(
                                    submission.answers[field.key],
                                    field.type,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </section>
            <section class="review-section">
                <h2>Documents</h2>
                <div class="document-list">
                    <div
                        v-for="(document, key) in submission.documents"
                        :key="key"
                    >
                        <p class="text-body-2 font-weight-medium">
                            {{ document.label }}
                        </p>
                        <v-btn
                            :href="document.url"
                            variant="text"
                            size="small"
                            prepend-icon="mdi-download"
                            class="document-download"
                            >{{ document.name }}</v-btn
                        >
                    </div>
                    <p
                        v-if="!Object.keys(submission.documents).length"
                        class="text-body-2 text-grey"
                    >
                        No documents provided.
                    </p>
                </div>
            </section>
        </div>
        <div v-if="submission.status === 'pending'" class="review-actions">
            <v-btn
                variant="outlined"
                color="error"
                prepend-icon="mdi-close-circle-outline"
                :disabled="form.processing"
                @click="rejectDialog = true"
                >Request changes</v-btn
            ><v-btn
                color="primary"
                prepend-icon="mdi-check-circle-outline"
                :disabled="form.processing"
                @click="approveDialog = true"
                >Approve KYC</v-btn
            >
        </div>
        <v-dialog v-model="approveDialog" max-width="440"
            ><v-card title="Approve KYC?"
                ><v-card-text
                    >Approve {{ customer.company || customer.name }} for
                    customer API access.</v-card-text
                ><v-card-actions
                    ><v-spacer /><v-btn
                        :disabled="form.processing"
                        @click="approveDialog = false"
                        >Cancel</v-btn
                    ><v-btn
                        color="primary"
                        :loading="form.processing"
                        @click="review('approve')"
                        >Approve</v-btn
                    ></v-card-actions
                ></v-card
            ></v-dialog
        >
        <v-dialog v-model="rejectDialog" max-width="520"
            ><v-card title="Request changes"
                ><v-card-text
                    ><v-textarea
                        v-model="form.rejection_reason"
                        label="Reason for rejection"
                        :error-messages="form.errors.rejection_reason"
                        variant="outlined"
                        rows="4"
                        maxlength="2000"
                        counter /></v-card-text
                ><v-card-actions
                    ><v-spacer /><v-btn
                        :disabled="form.processing"
                        @click="rejectDialog = false"
                        >Cancel</v-btn
                    ><v-btn
                        color="error"
                        :loading="form.processing"
                        :disabled="!form.rejection_reason.trim()"
                        @click="review('reject')"
                        >Send for correction</v-btn
                    ></v-card-actions
                ></v-card
            ></v-dialog
        >
    </AdminLayout>
</template>

<style scoped>
.review-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}
.review-heading h1 {
    font-size: 1.5rem;
    margin: 0 0 6px;
}
.review-heading p {
    overflow-wrap: anywhere;
}
.review-body {
    background: #fff;
    padding: 0 24px;
    border-top: 1px solid #dfe4e9;
}
.review-section {
    display: grid;
    grid-template-columns: 180px minmax(0, 1fr);
    gap: 24px;
    padding: 24px 0;
    border-bottom: 1px solid #e3e7ec;
}
.review-section h2 {
    font-size: 0.95rem;
    margin: 0;
    color: #344054;
}
.answer-grid,
.document-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
    margin: 0;
}
dt {
    font-size: 0.8rem;
    color: #657080;
    margin-bottom: 6px;
}
dd {
    font-size: 0.9rem;
    margin: 0;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}
.review-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 24px 0;
}
.document-download {
    max-width: 100%;
    height: auto;
    min-height: 32px;
}
.document-download :deep(.v-btn__content) {
    white-space: normal;
    overflow-wrap: anywhere;
    text-align: left;
}
@media (max-width: 960px) {
    .review-section {
        grid-template-columns: minmax(0, 1fr);
    }
}
@media (max-width: 600px) {
    .review-body {
        padding: 0 16px;
    }
    .answer-grid,
    .document-list {
        grid-template-columns: minmax(0, 1fr);
    }
    .review-actions {
        flex-wrap: wrap;
    }
}
</style>
