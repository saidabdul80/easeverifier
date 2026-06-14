<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\CampaignEmailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampaignEmailController extends Controller
{
    public function __construct(
        protected CampaignEmailService $campaignEmailService,
    ) {
    }

    public function index()
    {
        $templates = EmailTemplate::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (EmailTemplate $template) => [
                'id' => $template->id,
                'key' => $template->key,
                'name' => $template->name,
                'category' => $template->category,
                'subject' => $template->subject,
                'heading' => $template->heading,
                'body' => $template->body,
                'cta_label' => $template->cta_label,
                'cta_url' => $template->cta_url,
            ])
            ->values();

        $campaigns = EmailCampaign::with(['template:id,name,category', 'admin:id,name'])
            ->withCount([
                'recipients',
                'recipients as delivered_recipients_count' => fn ($query) => $query->where('status', 'sent'),
                'recipients as failed_recipients_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->latest()
            ->paginate(12)
            ->through(fn (EmailCampaign $campaign) => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'recipient_scope' => $campaign->recipient_scope,
                'subject' => $campaign->subject,
                'status' => $campaign->status,
                'total_recipients' => $campaign->total_recipients,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'sent_at' => optional($campaign->sent_at)?->toIso8601String(),
                'created_at' => optional($campaign->created_at)?->toIso8601String(),
                'template' => $campaign->template ? [
                    'name' => $campaign->template->name,
                    'category' => $campaign->template->category,
                ] : null,
                'admin' => $campaign->admin ? [
                    'name' => $campaign->admin->name,
                ] : null,
            ]);

        return Inertia::render('Admin/CampaignEmails/Index', [
            'templates' => $templates,
            'campaigns' => $campaigns,
            'stats' => [
                'total_campaigns' => EmailCampaign::count(),
                'total_sent' => EmailCampaign::sum('sent_count'),
                'total_failed' => EmailCampaign::sum('failed_count'),
                'customer_count' => User::query()
                    ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'customer'))
                    ->where('is_active', true)
                    ->count(),
            ],
        ]);
    }

    public function create()
    {
        $templates = EmailTemplate::active()
            ->orderBy('sort_order')
            ->get();

        $customers = User::query()
            ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'customer'))
            ->where('is_active', true)
            ->with('customer')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_name' => $user->customer?->company_name,
            ]);

        $previewCustomer = User::query()
            ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'customer'))
            ->where('is_active', true)
            ->with(['customer', 'wallet'])
            ->orderBy('name')
            ->first();

        return Inertia::render('Admin/CampaignEmails/Create', [
            'templates' => $templates,
            'customers' => $customers,
            'counts' => [
                'all' => $customers->count(),
            ],
            'previewCustomer' => $previewCustomer ? [
                'name' => $previewCustomer->name,
                'email' => $previewCustomer->email,
                'company_name' => $previewCustomer->customer?->company_name,
                'wallet_balance' => $previewCustomer->wallet?->balance ?? 0,
            ] : null,
            'availablePlaceholders' => [
                '{{customer_name}}',
                '{{first_name}}',
                '{{company_name}}',
                '{{email}}',
                '{{phone}}',
                '{{joined_date}}',
                '{{wallet_balance}}',
                '{{dashboard_url}}',
                '{{services_url}}',
                '{{pricing_url}}',
                '{{api_docs_url}}',
                '{{support_email}}',
                '{{support_name}}',
            ],
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'supportEmail' => (string) config('mail.from.address'),
            'supportName' => (string) config('mail.from.name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_id' => ['required', Rule::exists('email_templates', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'recipient_scope' => ['required', Rule::in(['all', 'selected'])],
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => ['integer', 'exists:users,id'],
            'additional_emails' => ['nullable', 'array'],
            'additional_emails.*' => ['email'],
        ]);

        $selectedCustomerIds = array_values($validated['customer_ids'] ?? []);
        $additionalEmails = collect($validated['additional_emails'] ?? [])
            ->map(fn (string $email) => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (
            $validated['recipient_scope'] === 'selected'
            && empty($selectedCustomerIds)
            && empty($additionalEmails)
        ) {
            throw ValidationException::withMessages([
                'customer_ids' => 'Select at least one customer or enter at least one email address.',
            ]);
        }

        $template = EmailTemplate::findOrFail($validated['template_id']);
        $recipients = $this->campaignEmailService->getRecipients(
            $validated['recipient_scope'],
            $selectedCustomerIds,
            $additionalEmails,
        );

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'customer_ids' => 'No valid recipients matched this selection.',
            ]);
        }

        $campaign = EmailCampaign::create([
            'admin_user_id' => $request->user()->id,
            'email_template_id' => $template->id,
            'title' => $validated['title'],
            'recipient_scope' => $validated['recipient_scope'],
            'selected_customer_ids' => $validated['recipient_scope'] === 'selected' ? $selectedCustomerIds : null,
            'additional_emails' => $additionalEmails ?: null,
            'subject' => $validated['subject'],
            'heading' => $validated['heading'] ?? null,
            'body' => $validated['body'],
            'cta_label' => $validated['cta_label'] ?? null,
            'cta_url' => $validated['cta_url'] ?? null,
            'total_recipients' => $recipients->count(),
            'status' => 'sending',
        ]);

        $this->campaignEmailService->sendCampaign($campaign, $recipients);

        return redirect()->route('admin.campaign-emails.index')
            ->with('success', 'Campaign email sent successfully.');
    }
}
