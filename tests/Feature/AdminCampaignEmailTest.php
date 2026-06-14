<?php

use App\Mail\CampaignEmailMail;
use App\Models\Customer;
use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

function createCampaignUser(string $role, array $attributes = []): User
{
    Role::findOrCreate($role);

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function createCampaignCustomer(array $userAttributes = [], array $customerAttributes = []): User
{
    $user = createCampaignUser('customer', $userAttributes);

    Customer::create(array_merge([
        'user_id' => $user->id,
        'company_name' => 'Acme Limited',
    ], $customerAttributes));

    return $user->fresh(['customer', 'wallet']);
}

it('loads the campaign email composer with seeded templates', function () {
    $admin = createCampaignUser('admin');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.campaign-emails.create'));

    $response->assertOk();

    expect(EmailTemplate::count())->toBe(10)
        ->and(EmailTemplate::where('key', 'support_check_in')->exists())->toBeTrue();
});

it('sends a fixed template campaign to all active customers', function () {
    Mail::fake();

    $admin = createCampaignUser('admin');
    $activeA = createCampaignCustomer(['name' => 'Alpha Customer', 'email' => 'alpha@example.com']);
    $activeB = createCampaignCustomer(['name' => 'Beta Customer', 'email' => 'beta@example.com']);
    $inactive = createCampaignCustomer(['name' => 'Dormant Customer', 'email' => 'dormant@example.com', 'is_active' => false]);
    $template = EmailTemplate::where('key', 'support_check_in')->firstOrFail();

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.campaign-emails.store'), [
            'template_id' => $template->id,
            'recipient_scope' => 'all',
        ]);

    $response->assertRedirect(route('admin.campaign-emails.index'));

    Mail::assertSent(CampaignEmailMail::class, 2);
    Mail::assertSent(CampaignEmailMail::class, fn (CampaignEmailMail $mail) => $mail->hasTo($activeA->email));
    Mail::assertSent(CampaignEmailMail::class, fn (CampaignEmailMail $mail) => $mail->hasTo($activeB->email));
    Mail::assertNotSent(CampaignEmailMail::class, fn (CampaignEmailMail $mail) => $mail->hasTo($inactive->email));

    $campaign = EmailCampaign::firstOrFail();

    expect($campaign->title)->toBe('Support Check-in')
        ->and($campaign->recipient_scope)->toBe('all')
        ->and($campaign->total_recipients)->toBe(2)
        ->and($campaign->sent_count)->toBe(2)
        ->and($campaign->failed_count)->toBe(0)
        ->and($campaign->status)->toBe('sent');

    $this->assertDatabaseCount('email_campaign_recipients', 2);
});

it('sends a fixed template campaign to selected customers only', function () {
    Mail::fake();

    $admin = createCampaignUser('admin');
    $selected = createCampaignCustomer(['name' => 'Chosen Customer', 'email' => 'chosen@example.com']);
    $notSelected = createCampaignCustomer(['name' => 'Other Customer', 'email' => 'other@example.com']);
    $template = EmailTemplate::where('key', 'welcome_onboard')->firstOrFail();

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.campaign-emails.store'), [
            'template_id' => $template->id,
            'recipient_scope' => 'selected',
            'customer_ids' => [$selected->id],
        ]);

    $response->assertRedirect(route('admin.campaign-emails.index'));

    Mail::assertSent(CampaignEmailMail::class, 1);
    Mail::assertSent(CampaignEmailMail::class, fn (CampaignEmailMail $mail) => $mail->hasTo($selected->email));
    Mail::assertNotSent(CampaignEmailMail::class, fn (CampaignEmailMail $mail) => $mail->hasTo($notSelected->email));

    $campaign = EmailCampaign::firstOrFail();

    expect($campaign->recipient_scope)->toBe('selected')
        ->and($campaign->selected_customer_ids)->toBe([$selected->id])
        ->and($campaign->sent_count)->toBe(1);
});
