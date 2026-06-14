<?php

namespace App\Services;

use App\Mail\CampaignEmailMail;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class CampaignEmailService
{
    public function getRecipients(
        string $recipientScope,
        ?array $customerIds = null,
        ?array $additionalEmails = null,
    ): Collection
    {
        $query = User::query()
            ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'customer'))
            ->where('is_active', true)
            ->with(['customer', 'wallet'])
            ->orderBy('name');

        if ($recipientScope === 'selected') {
            $query->whereIn('id', $customerIds ?? []);
        }

        $customerRecipients = $query->get()->map(fn (User $user) => [
            'user' => $user,
            'email' => $user->email,
        ]);

        $manualRecipients = collect($additionalEmails ?? [])
            ->filter()
            ->map(fn (string $email) => [
                'user' => null,
                'email' => strtolower(trim($email)),
            ]);

        return $customerRecipients
            ->concat($manualRecipients)
            ->unique(fn (array $recipient) => strtolower($recipient['email']))
            ->values();
    }

    public function sendCampaign(EmailCampaign $campaign, Collection $recipients): EmailCampaign
    {
        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipientData) {
            $user = $recipientData['user'];
            $email = $recipientData['email'];

            $recipient = EmailCampaignRecipient::create([
                'email_campaign_id' => $campaign->id,
                'user_id' => $user?->id,
                'email' => $email,
                'status' => 'pending',
            ]);

            try {
                Mail::to($email)->send(new CampaignEmailMail(
                    $this->buildPayload($campaign, $user, $email)
                ));

                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $sentCount++;
            } catch (Throwable $exception) {
                $recipient->update([
                    'status' => 'failed',
                    'failure_reason' => Str::limit($exception->getMessage(), 1000),
                ]);

                $failedCount++;
            }
        }

        $campaign->update([
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'status' => $sentCount > 0 && $failedCount === 0 ? 'sent' : ($sentCount > 0 ? 'partial' : 'failed'),
            'sent_at' => now(),
        ]);

        return $campaign->fresh(['template', 'admin', 'recipients']);
    }

    public function buildPayload(EmailCampaign $campaign, ?User $user, string $email): array
    {
        return [
            'subject' => $this->replacePlaceholders($campaign->subject, $user, $email),
            'heading' => $this->replacePlaceholders($campaign->heading, $user, $email),
            'body' => $this->replacePlaceholders($campaign->body, $user, $email),
            'cta_label' => $this->replacePlaceholders($campaign->cta_label, $user, $email),
            'cta_url' => $this->replacePlaceholders($campaign->cta_url, $user, $email),
            'support_email' => config('mail.from.address'),
            'support_name' => config('mail.from.name'),
        ];
    }

    public function replacePlaceholders(?string $value, ?User $user, string $email): ?string
    {
        if ($value === null) {
            return null;
        }

        $fallbackName = Str::before($email, '@') ?: $email;
        $customerName = $user?->name ?: $fallbackName;
        $firstName = trim(Str::before($customerName, ' ')) ?: $customerName;
        $companyName = $user?->customer?->company_name ?: 'your team';
        $walletBalance = 'NGN ' . number_format((float) ($user?->wallet?->balance ?? 0), 0);

        return strtr($value, [
            '{{customer_name}}' => $customerName,
            '{{first_name}}' => $firstName,
            '{{company_name}}' => $companyName,
            '{{email}}' => $email,
            '{{phone}}' => $user?->phone ?: 'N/A',
            '{{joined_date}}' => optional($user?->created_at)->format('M j, Y') ?: '',
            '{{dashboard_url}}' => url('/customer'),
            '{{services_url}}' => url('/services'),
            '{{pricing_url}}' => url('/pricing'),
            '{{api_docs_url}}' => url('/customer/api/documentation'),
            '{{wallet_balance}}' => $walletBalance,
            '{{support_email}}' => (string) config('mail.from.address'),
            '{{support_name}}' => (string) config('mail.from.name'),
        ]);
    }
}
