<?php

use App\Http\Middleware\McpOAuthContext;
use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\Customer;
use App\Models\CustomerKycSubmission;
use App\Models\CustomerPaygoService;
use App\Models\PaygoVerificationIntent;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Services\Paygo\PaygoVerificationService;
use App\Support\KycQuestionnaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    throw new RuntimeException('KYC tests require sqlite :memory: before Laravel boots.');
}

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    Storage::fake('public');
    Http::preventStrayRequests();
});

function kycCustomer(string $type = 'business'): User
{
    Role::findOrCreate('customer');
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('customer');
    Customer::create(['user_id' => $user->id, 'account_type' => $type, 'company_name' => 'Example Limited', 'api_enabled' => true]);

    return $user->fresh('customer');
}

function kycAdmin(): User
{
    Role::findOrCreate('admin');
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function kycAnswers(): array
{
    $answers = [];
    foreach (KycQuestionnaire::sections(true) as $section) {
        foreach ($section['fields'] as $field) {
            $answers[$field['key']] = match ($field['type']) {
                'boolean' => false,
                'email' => 'contact@example.test',
                'url' => 'https://example.test',
                'select' => 'Technology',
                default => 'Example details',
            };
        }
    }

    return $answers;
}

function kycPayload(): array
{
    return [
        'answers' => kycAnswers(),
        'documents' => [
            'incorporation' => UploadedFile::fake()->create('incorporation.pdf', 10, 'application/pdf'),
            'directors' => UploadedFile::fake()->create('directors.pdf', 10, 'application/pdf'),
        ],
        'declaration' => true,
    ];
}

function pendingKyc(Customer $customer): CustomerKycSubmission
{
    return $customer->kycSubmissions()->create([
        'questions' => KycQuestionnaire::sections(true),
        'answers' => kycAnswers(),
        'documents' => [],
        'submitted_at' => now(),
    ]);
}

it('shows a customer the complete kyc questionnaire without approving them', function () {
    $user = kycCustomer();

    $this->actingAs($user)->get('/customer/kyc')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Customer/Kyc/Index')
            ->where('status', 'not_submitted')->has('sections', 4)->has('documentFields', 4)
            ->where('approvedAt', null)->where('defaults.registered_name', 'Example Limited'));
});

it('stores encrypted kyc answers and private documents as a pending submission', function () {
    $user = kycCustomer();
    $this->actingAs($user)->post('/customer/kyc', kycPayload())->assertSessionHasNoErrors()->assertRedirect('/customer/kyc');
    $submission = CustomerKycSubmission::query()->sole();

    expect($user->customer->fresh()->kyc_approved)->toBeNull()
        ->and($user->customer->fresh()->kycStatus())->toBe('pending')
        ->and($submission->answers['registered_name'])->toBe('Example details')
        ->and($submission->getRawOriginal('answers'))->not->toContain('Example details')
        ->and($submission->getRawOriginal('documents'))->not->toContain('incorporation.pdf');
    Storage::disk('local')->assertExists($submission->documents['incorporation']['path']);
    Storage::disk('public')->assertMissing($submission->documents['incorporation']['path']);
    $this->actingAs($user->fresh())->get('/customer/kyc')->assertInertia(fn (Assert $page) => $page
        ->where('status', 'pending')->where('submission.documents.incorporation.name', 'incorporation.pdf')
        ->missing('submission.documents.incorporation.path'));
});

it('validates required kyc answers declaration and business documents', function () {
    $user = kycCustomer();
    $this->actingAs($user)->post('/customer/kyc', ['answers' => [], 'declaration' => false])
        ->assertSessionHasErrors(['answers.registered_name', 'answers.contact_email', 'documents.incorporation', 'documents.directors', 'declaration']);
    expect(CustomerKycSubmission::count())->toBe(0);
});

it('requires an operating licence when the customer says one is required', function () {
    $user = kycCustomer();
    $payload = kycPayload();
    $payload['answers']['requires_license'] = '1';
    $this->actingAs($user)->post('/customer/kyc', $payload)->assertSessionHasErrors('documents.operating_license');
    expect(CustomerKycSubmission::count())->toBe(0);
});

it('rejects unsupported and oversized kyc uploads', function (string $name, int $size, string $mime) {
    $user = kycCustomer();
    $payload = kycPayload();
    $payload['documents']['incorporation'] = UploadedFile::fake()->create($name, $size, $mime);
    $this->actingAs($user)->post('/customer/kyc', $payload)->assertSessionHasErrors('documents.incorporation');
    expect(CustomerKycSubmission::count())->toBe(0);
})->with([
    'unsupported file' => ['document.exe', 10, 'application/x-msdownload'],
    'oversized file' => ['large.pdf', 10241, 'application/pdf'],
]);

it('lets individuals submit the common questionnaire without business registration documents', function () {
    $user = kycCustomer('individual');
    $this->actingAs($user)->post('/customer/kyc', ['answers' => kycAnswers(), 'declaration' => true])
        ->assertSessionHasNoErrors()->assertRedirect('/customer/kyc');
    expect(CustomerKycSubmission::query()->sole()->status())->toBe('pending');
});

it('prevents a customer from overwriting a pending submission or approving themselves', function () {
    $user = kycCustomer();
    $submission = pendingKyc($user->customer);
    $this->actingAs($user)->post('/customer/kyc', kycPayload())->assertForbidden();
    $this->post('/admin/kyc/'.$submission->id.'/review', ['decision' => 'approve'])->assertForbidden();
    expect(CustomerKycSubmission::count())->toBe(1)->and($user->customer->fresh()->kyc_approved)->toBeNull();
});

it('blocks existing and branch api keys until kyc has been approved', function (string $environment, bool $branchKey, bool $pending) {
    $user = kycCustomer();
    if ($pending) {
        pendingKyc($user->customer);
    }
    $branch = $branchKey ? $user->branches()->create(['name' => 'Branch']) : null;
    $key = ApiKey::generate($user->id, 'KYC Gate', $environment, $branch?->id);
    $balance = (float) $user->wallet->total_balance;

    $this->withHeader('Authorization', 'Bearer '.$key->getBearerToken())->postJson('/api/v1/verify/nin', ['nin' => '11111111111', 'consent' => true])
        ->assertForbidden()->assertJsonPath('error_code', 'KYC_APPROVAL_REQUIRED');
    Http::assertNothingSent();
    expect($key->fresh()->last_used_at)->toBeNull()
        ->and(ApiLog::count())->toBe(0)->and(VerificationRequest::count())->toBe(0)
        ->and((float) $user->wallet->fresh()->total_balance)->toBe($balance);
})->with([
    'live not submitted' => ['live', false, false],
    'sandbox not submitted' => ['test', false, false],
    'live pending' => ['live', false, true],
    'branch pending' => ['live', true, true],
]);

it('blocks api keys whose user has no customer profile', function () {
    $user = User::factory()->create(['is_active' => true]);
    $key = ApiKey::generate($user->id);
    $this->withHeader('Authorization', 'Bearer '.$key->getBearerToken())->getJson('/api/v1/services')
        ->assertForbidden()->assertJsonPath('error_code', 'KYC_APPROVAL_REQUIRED');
});

it('allows admins to approve kyc and immediately unlock existing api keys', function () {
    $user = kycCustomer();
    $submission = pendingKyc($user->customer);
    $admin = kycAdmin();
    $key = ApiKey::generate($user->id);
    $this->actingAs($admin)->get('/admin/kyc')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Kyc/Index')->where('submissions.total', 1));
    $this->get('/admin/kyc/'.$submission->id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Kyc/Show')->where('submission.status', 'pending'));
    $this->post('/admin/kyc/'.$submission->id.'/review', ['decision' => 'approve'])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($user->customer->fresh()->hasApprovedKyc())->toBeTrue()
        ->and($submission->fresh()->reviewed_by)->toBe($admin->id)
        ->and($submission->fresh()->status())->toBe('approved');
    $this->withHeader('Authorization', 'Bearer '.$key->getBearerToken())->getJson('/api/v1/services')->assertOk();
    $this->actingAs($admin)->post('/admin/kyc/'.$submission->id.'/review', ['decision' => 'reject', 'rejection_reason' => 'Changed mind'])
        ->assertSessionHasErrors('decision');
    $this->actingAs($user->fresh())->post('/customer/kyc', kycPayload())->assertForbidden();
});

it('requires rejection feedback and lets customers resubmit without losing review history', function () {
    $user = kycCustomer();
    $this->actingAs($user)->post('/customer/kyc', kycPayload())->assertSessionHasNoErrors();
    $submission = CustomerKycSubmission::query()->sole();
    $admin = kycAdmin();
    $this->actingAs($admin)->post('/admin/kyc/'.$submission->id.'/review', ['decision' => 'reject'])->assertSessionHasErrors('rejection_reason');
    $this->post('/admin/kyc/'.$submission->id.'/review', ['decision' => 'reject', 'rejection_reason' => 'Please correct the registration number.'])->assertSessionHasNoErrors();

    expect($user->customer->fresh()->kycStatus())->toBe('rejected');
    $this->actingAs($user->fresh())->post('/customer/kyc', ['answers' => kycAnswers(), 'declaration' => true])->assertSessionHasNoErrors();
    expect(CustomerKycSubmission::count())->toBe(2)
        ->and($submission->fresh()->rejection_reason)->toBe('Please correct the registration number.')
        ->and($user->customer->fresh()->kycStatus())->toBe('pending');
    $this->actingAs($admin)->post('/admin/kyc/'.$submission->id.'/review', ['decision' => 'approve'])->assertSessionHasErrors('decision');
});

it('restricts kyc document downloads to the owner and admins', function () {
    $user = kycCustomer();
    $other = kycCustomer();
    $this->actingAs($user)->post('/customer/kyc', kycPayload())->assertSessionHasNoErrors();
    $submission = CustomerKycSubmission::query()->sole();
    $customerUrl = '/customer/kyc/'.$submission->id.'/documents/incorporation';
    $adminUrl = '/admin/kyc/'.$submission->id.'/documents/incorporation';
    $this->get($customerUrl)->assertOk()->assertDownload('incorporation.pdf');
    $this->actingAs($other)->get($customerUrl)->assertForbidden();
    $this->get($adminUrl)->assertForbidden();
    $this->actingAs(kycAdmin())->get($adminUrl)->assertOk()->assertDownload('incorporation.pdf');
    $this->get('/admin/kyc/'.$submission->id.'/documents/unknown')->assertNotFound();
});

it('blocks oauth mcp access until kyc approval', function () {
    $user = kycCustomer();
    $request = Request::create('/api/mcp', 'POST');
    $request->setUserResolver(fn () => $user);
    $response = app(McpOAuthContext::class)->handle($request, fn () => response()->json(['unexpected' => true]));
    expect($response->getStatusCode())->toBe(403)
        ->and(json_decode($response->getContent(), true)['error_code'])->toBe('KYC_APPROVAL_REQUIRED');
});

it('rejects live key creation until kyc approval for primary and branch scopes', function (string $status, bool $branchKey) {
    $user = kycCustomer();
    if ($status !== 'not_submitted') {
        $submission = pendingKyc($user->customer);
        if ($status === 'rejected') {
            $submission->update(['reviewed_at' => now(), 'rejection_reason' => 'Please correct your details.']);
        }
    }
    $branch = $branchKey ? $user->branches()->create(['name' => 'Branch']) : null;

    $this->actingAs($user)->postJson('/customer/api/keys', [
        'name' => 'Production key', 'environment' => 'live', 'branch_id' => $branch?->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('environment');

    expect(ApiKey::query()->count())->toBe(0);
})->with([
    'primary not submitted' => ['not_submitted', false],
    'primary pending' => ['pending', false],
    'primary rejected' => ['rejected', false],
    'branch not submitted' => ['not_submitted', true],
    'branch pending' => ['pending', true],
    'branch rejected' => ['rejected', true],
]);

it('allows live key creation for approved customers in primary and branch scopes', function (bool $branchKey) {
    $user = kycCustomer();
    approveCustomerKyc($user);
    $branch = $branchKey ? $user->branches()->create(['name' => 'Branch']) : null;

    $this->actingAs($user)->post('/customer/api/keys', [
        'name' => 'Production key', 'environment' => 'live', 'branch_id' => $branch?->id,
    ])->assertSessionHasNoErrors()->assertSessionHas('newKey');

    $key = ApiKey::query()->sole();
    expect($key->environment)->toBe('live')->and($key->branch_id)->toBe($branch?->id);
})->with(['primary' => [false], 'branch' => [true]]);

it('allows test key creation before kyc approval in primary and branch scopes', function (bool $branchKey) {
    $user = kycCustomer();
    $branch = $branchKey ? $user->branches()->create(['name' => 'Branch']) : null;

    $this->actingAs($user)->post('/customer/api/keys', [
        'name' => 'Sandbox key', 'environment' => 'test', 'branch_id' => $branch?->id,
    ])->assertSessionHasNoErrors()->assertSessionHas('newKey');

    $key = ApiKey::query()->sole();
    expect($key->environment)->toBe('test')->and($key->branch_id)->toBe($branch?->id);
})->with(['primary' => [false], 'branch' => [true]]);

it('keeps kyc approval out of customer mass assignment', function () {
    $user = kycCustomer();
    $user->customer->fill(['kyc_approved' => now()])->save();
    expect($user->customer->fresh()->kyc_approved)->toBeNull();
});

it('blocks public paygo api endpoints when the service owner is awaiting kyc approval', function () {
    $user = kycCustomer();
    $service = VerificationService::create([
        'name' => 'NIN Verification', 'slug' => 'nin', 'default_price' => 100, 'cost_price' => 50, 'is_active' => true,
    ]);
    $paygo = CustomerPaygoService::create([
        'user_id' => $user->id, 'verification_service_id' => $service->id, 'name' => 'KYC Test',
        'public_slug' => 'kyc-test', 'verify_secret_hash' => hash('sha256', 'secret'), 'price' => 150, 'is_active' => true,
    ]);
    $intent = app(PaygoVerificationService::class)->createIntent($paygo, ['nin' => '12345678901']);
    $this->postJson('/api/paygo/kyc-test/verify', ['nin' => '12345678901', 'consent' => true])
        ->assertForbidden()->assertJsonPath('error_code', 'KYC_APPROVAL_REQUIRED');
    $this->getJson('/api/paygo/results/'.$intent->reference)
        ->assertForbidden()->assertJsonPath('error_code', 'KYC_APPROVAL_REQUIRED');
    Http::assertNothingSent();
    expect(PaygoVerificationIntent::query()->sole()->verification_attempts)->toBe(0);
});

function seedKycVerificationHistory(User $user, int $count, array $overrides = []): void
{
    $service = VerificationService::firstOrCreate(['slug' => 'nin'], [
        'name' => 'NIN Verification', 'default_price' => 100, 'cost_price' => 50, 'is_active' => true,
    ]);
    $rows = [];

    for ($index = 0; $index < $count; $index++) {
        $rows[] = array_merge([
            'user_id' => $user->id,
            'verification_service_id' => $service->id,
            'reference' => (string) Str::uuid(),
            'search_parameter' => '12345678901',
            'amount_charged' => 100,
            'status' => 'completed',
            'source' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    VerificationRequest::query()->insert($rows);
}

it('auto approves customers with at least 100 live requests without a kyc submission', function () {
    $this->travelTo(now()->startOfSecond());
    $eligible = kycCustomer();
    $branch = $eligible->branches()->create(['name' => 'Production Branch']);
    seedKycVerificationHistory($eligible, 50, ['source' => 'web']);
    seedKycVerificationHistory($eligible, 50, ['branch_id' => $branch->id]);
    $aboveThreshold = kycCustomer();
    seedKycVerificationHistory($aboveThreshold, 101);
    $belowThreshold = kycCustomer();
    seedKycVerificationHistory($belowThreshold, 99);
    $approved = kycCustomer();
    $approvedAt = now()->subDays(2);
    $approved->customer->forceFill(['kyc_approved' => $approvedAt])->save();
    seedKycVerificationHistory($approved, 100);

    $migration = require database_path('migrations/2026_10_15_093826_auto_approve_customers_with_100_live_requests.php');
    $migration->up();

    expect($eligible->customer->fresh()->kyc_approved->equalTo(now()))->toBeTrue()
        ->and($aboveThreshold->customer->fresh()->hasApprovedKyc())->toBeTrue()
        ->and($belowThreshold->customer->fresh()->kyc_approved)->toBeNull()
        ->and($approved->customer->fresh()->kyc_approved->equalTo($approvedAt))->toBeTrue()
        ->and(CustomerKycSubmission::query()->count())->toBe(0);

    $this->travel(1)->hours();
    $migration->up();
    expect($eligible->customer->fresh()->kyc_approved->equalTo(now()->subHour()))->toBeTrue();
});

it('does not count sandbox free failed or pending requests for automatic kyc approval', function () {
    $free = kycCustomer();
    seedKycVerificationHistory($free, 100, ['amount_charged' => 0]);
    $failed = kycCustomer();
    seedKycVerificationHistory($failed, 100, ['status' => 'failed']);
    $pending = kycCustomer();
    seedKycVerificationHistory($pending, 100, ['status' => 'processing']);
    $sandbox = kycCustomer();
    $provider = ServiceProvider::create([
        'verification_service_id' => VerificationService::where('slug', 'nin')->value('id'),
        'name' => 'Sandbox Provider', 'base_url' => 'https://sandbox.test', 'endpoint' => '/nin', 'environment' => 'test',
    ]);
    seedKycVerificationHistory($sandbox, 100, ['service_provider_id' => $provider->id]);
    $mixed = kycCustomer();
    seedKycVerificationHistory($mixed, 99);
    seedKycVerificationHistory($mixed, 10, ['amount_charged' => 0]);

    $migration = require database_path('migrations/2026_10_15_093826_auto_approve_customers_with_100_live_requests.php');
    $migration->up();

    foreach ([$free, $failed, $pending, $sandbox, $mixed] as $user) {
        expect($user->customer->fresh()->kyc_approved)->toBeNull();
    }
});
