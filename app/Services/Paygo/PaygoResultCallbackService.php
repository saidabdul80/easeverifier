<?php

namespace App\Services\Paygo;

use App\Models\PaygoVerificationIntent;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;

class PaygoResultCallbackService
{
    public function sendResultWebhook(
        PaygoVerificationIntent $intent,
        bool $success,
        ?array $resultData = null,
        ?string $errorMessage = null,
        ?string $errorCode = null
    ): void
    {
        if (! $this->usesWebhookCallback($intent)) {
            return;
        }

        $webhookUrl = $this->webhookUrl($intent);
        $secret = $this->webhookSecret($intent);

        if (blank($webhookUrl) || blank($secret)) {
            $this->updateWebhookMetadata($intent, [
                'webhook_last_attempt_at' => now()->toISOString(),
                'webhook_last_status' => 'skipped',
                'webhook_last_error' => 'Webhook callback is enabled, but URL or secret is missing.',
            ]);

            return;
        }

        $payload = $this->buildWebhookPayload($intent, $success, $resultData, $errorMessage, $errorCode);
        $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), $secret);

        $response = Http::timeout(15)
            ->retry(2, 500)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-EaseVerifier-Event' => $payload['event'],
                'X-EaseVerifier-Reference' => $intent->reference,
                'X-EaseVerifier-Signature' => $signature,
            ])
            ->post($webhookUrl, $payload);

        $this->recordWebhookResponse($intent, $response);
    }

    public function redirectToConfiguredUrl(
        PaygoVerificationIntent $intent,
        bool $successUrl,
        array $query = []
    ): ?RedirectResponse {
        if (! $this->usesRedirectCallback($intent)) {
            return null;
        }

        $url = $successUrl
            ? ($intent->metadata['success_url_snapshot'] ?? $intent->paygoService?->success_url)
            : ($intent->metadata['failure_url_snapshot'] ?? $intent->paygoService?->failure_url);

        if (blank($url)) {
            return null;
        }

        $context = [
            'reference' => $intent->reference,
            'candidate_id' => $intent->metadata['candidate_id'] ?? null,
            'portal_ref' => $intent->metadata['portal_ref'] ?? null,
            'state' => $intent->metadata['portal_state'] ?? null,
        ];

        $queryString = http_build_query(array_filter(
            array_merge($context, $query),
            fn ($value) => $value !== null && $value !== ''
        ));

        return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').$queryString);
    }

    protected function buildWebhookPayload(
        PaygoVerificationIntent $intent,
        bool $success,
        ?array $resultData,
        ?string $errorMessage,
        ?string $errorCode
    ): array {
        $intent->loadMissing(['paygoService.user.customer']);

        return [
            'event' => $success ? 'paygo.result.ready' : 'paygo.result.failed',
            'reference' => $intent->reference,
            'candidate_id' => $intent->metadata['candidate_id'] ?? null,
            'portal_ref' => $intent->metadata['portal_ref'] ?? null,
            'state' => $intent->metadata['portal_state'] ?? null,
            'school_referral_code' => $intent->metadata['referral_code'] ?? $intent->paygoService?->user?->customer?->referral_code,
            'board' => strtoupper((string) $intent->paygoService?->resultBoard()),
            'payment_status' => in_array($intent->status, ['paid', 'used', 'verifying'], true) ? 'paid' : $intent->status,
            'result_status' => $success ? 'ready' : 'failed',
            'lookup_label' => $intent->lookup_label,
            'result' => $resultData,
            'error' => $errorMessage,
            'error_code' => $errorCode,
            'timestamp' => now()->toISOString(),
        ];
    }

    protected function recordWebhookResponse(PaygoVerificationIntent $intent, Response $response): void
    {
        $metadata = [
            'webhook_last_attempt_at' => now()->toISOString(),
            'webhook_last_status' => $response->successful() ? 'delivered' : 'failed',
            'webhook_last_response_code' => $response->status(),
        ];

        if ($response->successful()) {
            $metadata['webhook_delivered_at'] = now()->toISOString();
        } else {
            $metadata['webhook_last_error'] = $response->json('message')
                ?? $response->body();
        }

        $this->updateWebhookMetadata($intent, $metadata);
    }

    protected function updateWebhookMetadata(PaygoVerificationIntent $intent, array $metadata): void
    {
        $intent->update([
            'metadata' => array_merge($intent->metadata ?? [], $metadata),
        ]);
    }

    protected function usesRedirectCallback(PaygoVerificationIntent $intent): bool
    {
        return in_array($intent->metadata['callback_mode'] ?? $intent->paygoService?->callback_mode ?? 'redirect', ['redirect', 'hybrid'], true);
    }

    protected function usesWebhookCallback(PaygoVerificationIntent $intent): bool
    {
        return in_array($intent->metadata['callback_mode'] ?? $intent->paygoService?->callback_mode ?? 'redirect', ['webhook', 'hybrid'], true);
    }

    protected function webhookUrl(PaygoVerificationIntent $intent): ?string
    {
        return $intent->metadata['webhook_url_snapshot']
            ?? $intent->paygoService?->user?->customer?->webhook_url;
    }

    protected function webhookSecret(PaygoVerificationIntent $intent): ?string
    {
        return $intent->paygoService?->webhook_secret;
    }
}
