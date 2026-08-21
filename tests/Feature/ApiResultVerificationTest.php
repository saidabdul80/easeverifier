<?php

use App\Models\ApiKey;
use App\Models\CustomerServicePricing;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Services\ResultVerify\ResultGates\NabtebResult;
use App\Services\ResultVerify\ResultGates\NbaisResult;
use App\Services\ResultVerify\ResultGates\NecoEVerify;
use App\Services\ResultVerify\ResultGates\NECOResult;
use App\Services\ResultVerify\ResultGates\WAECResult;
use App\Services\ResultVerify\ResultInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createResultApiUser(float $balance = 100): User
{
    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $user->wallet()->create([
        'balance' => $balance,
        'bonus_balance' => 0,
    ]);

    return $user;
}

function createResultService(string $slug, float $price): VerificationService
{
    return VerificationService::updateOrCreate(
        ['slug' => $slug],
        [
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'description' => 'Result verification test service',
            'default_price' => $price,
            'cost_price' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ],
    );
}

it('charges the configured customer price when loading WAEC form metadata', function () {
    $user = createResultApiUser(100);
    $service = createResultService('waec-result-form', 15);

    CustomerServicePricing::create([
        'user_id' => $user->id,
        'verification_service_id' => $service->id,
        'price' => 7,
        'is_active' => true,
    ]);

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->getJson('/api/v1/results/waec/form');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.board', 'WAEC')
        ->assertJsonPath('sandbox', false);

    expect((float) $user->wallet()->first()->fresh()->balance)->toBe(93.0);

    $request = VerificationRequest::where('verification_service_id', $service->id)->first();
    expect($request)->not->toBeNull()
        ->and((float) $request->amount_charged)->toBe(7.0)
        ->and($request->status)->toBe('completed')
        ->and($request->request_data['action'])->toBe('form');
});

it('charges the fetch service independently from the form service', function () {
    $user = createResultApiUser(100);
    createResultService('waec-result-form', 5);
    $fetchService = createResultService('waec-result-fetch', 30);

    CustomerServicePricing::create([
        'user_id' => $user->id,
        'verification_service_id' => $fetchService->id,
        'price' => 23,
        'is_active' => true,
    ]);

    app()->instance(WAECResult::class, new class implements ResultInterface
    {
        public function formFields(): array
        {
            return [
                ['name' => 'txtExamNumber', 'label' => 'Examination Number', 'type' => 'text', 'required' => true],
                ['name' => 'ExamYear', 'label' => 'Examination Year', 'type' => 'select', 'required' => true],
                ['name' => 'ExamType', 'label' => 'Examination Type', 'type' => 'select', 'required' => true],
                ['name' => 'txtPIN', 'label' => 'PIN', 'type' => 'text', 'required' => true],
                ['name' => 'txtCardSerialNo', 'label' => 'Card Serial Number', 'type' => 'text', 'required' => true],
            ];
        }

        public function fetchResult(array $params): string
        {
            return '<html>result</html>';
        }

        public function parseResult(string $html): array
        {
            return [
                'status' => 'success',
                'candidate' => ['name' => 'Jane Candidate', 'exam_number' => '1234567890'],
                'subjects' => [['subject' => 'MATHEMATICS', 'grade' => 'A1', 'score' => null]],
                'overall' => null,
            ];
        }
    });

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/results/waec/fetch', [
        'txtExamNumber' => '1234567890',
        'ExamYear' => '2025',
        'ExamType' => 'MAY/JUN',
        'txtPIN' => '123456789012',
        'txtCardSerialNo' => 'WRN123456789',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.board', 'WAEC')
        ->assertJsonPath('data.candidate.name', 'Jane Candidate');

    expect((float) $user->wallet()->first()->fresh()->balance)->toBe(77.0);

    expect(VerificationRequest::where('verification_service_id', $fetchService->id)->count())->toBe(1)
        ->and(VerificationRequest::whereHas('verificationService', fn ($query) => $query->where('slug', 'waec-result-form'))->count())->toBe(0);

    $request = VerificationRequest::where('verification_service_id', $fetchService->id)->first();
    expect((float) $request->amount_charged)->toBe(23.0)
        ->and($request->request_data['action'])->toBe('fetch')
        ->and($request->request_data['parameters']['txtPIN'])->toBe('***REDACTED***');
});

it('does not charge sandbox API keys for NECO form or fetch', function () {
    $user = createResultApiUser(100);
    createResultService('neco-result-form', 10);
    createResultService('neco-result-fetch', 25);

    $apiKey = ApiKey::generate($user->id, 'Sandbox', 'test');

    $formResponse = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->getJson('/api/v1/results/neco/form');

    $fetchResponse = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/results/neco/fetch', [
        'exam_year' => '2025',
        'exam_type' => 'ssce_int',
        'reg_no' => '1234567890',
        'token' => '123456789012',
    ]);

    $formResponse->assertOk()->assertJsonPath('sandbox', true);
    $fetchResponse->assertOk()->assertJsonPath('sandbox', true);

    expect((float) $user->wallet()->first()->fresh()->balance)->toBe(100.0)
        ->and(VerificationRequest::count())->toBe(2)
        ->and((float) VerificationRequest::sum('amount_charged'))->toBe(0.0);
});

it('parses NBAIS html result into candidate and result payloads', function () {
    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <div class="result-sheet">
        <div><span class="sheet-label">Candidate Name</span><span class="sheet-value">SAYYID ABDULLAHI</span></div>
        <div><span class="sheet-label">Exam Number</span><span class="sheet-value">481634346OS</span></div>
        <div><span class="sheet-label">Exam Type</span><span class="sheet-value">SAISSC</span></div>
        <div><span class="sheet-label">Month/Year</span><span class="sheet-value">Nov/Dec - 2022</span></div>
        <div><span class="sheet-label">Centre Number</span><span class="sheet-value">OS001</span></div>
        <div><span class="sheet-label">Centre Name</span><span class="sheet-value">ISLAMIC INSTITUTE OF NIGERIA, EDE</span></div>
        <div><span class="sheet-label">Gender</span><span class="sheet-value">Male</span></div>
        <div><span class="sheet-label">DOB</span><span class="sheet-value">2006-03-14</span></div>
    </div>
    <img src="/storage/photos/481634346OS.jpg" alt="Candidate Photo" class="student-photo">
    <img src="/qrcode/481634346OS.png" alt="QR Code">
    <table class="results">
        <thead><tr><th>S/N</th><th>Subject</th><th>Grade</th><th>Remark</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>ISLAMIC STUDIES</td><td>B3</td><td>GOOD</td></tr>
            <tr><td>2</td><td>MATHEMATICS</td><td>A1</td><td>EXCELLENT</td></tr>
        </tbody>
    </table>
    <div class="notes-container">
        <ol><li>The Result is provisional and not transferable</li></ol>
    </div>
</body>
</html>
HTML;

    $parsed = app(NbaisResult::class)->parseResult($html);

    expect($parsed['status'])->toBe('success')
        ->and($parsed['candidate']['name'])->toBe('SAYYID ABDULLAHI')
        ->and($parsed['candidate']['exam_number'])->toBe('481634346OS')
        ->and($parsed['candidate']['centre_number'])->toBe('OS001')
        ->and($parsed['candidate']['centre_name'])->toBe('ISLAMIC INSTITUTE OF NIGERIA, EDE')
        ->and($parsed['candidate']['gender'])->toBe('Male')
        ->and($parsed['candidate']['date_of_birth'])->toBe('2006-03-14')
        ->and($parsed['candidate']['passport'])->toBe('https://resultchecker.nbais.com.ng/storage/photos/481634346OS.jpg')
        ->and($parsed['result']['qr_code'])->toBe('https://resultchecker.nbais.com.ng/qrcode/481634346OS.png')
        ->and($parsed['result']['exam_label'])->toBe('Nov/Dec - 2022')
        ->and($parsed['result']['subjects'])->toHaveCount(2)
        ->and($parsed['result']['subjects'][0])->toMatchArray([
            'subject' => 'ISLAMIC STUDIES',
            'grade' => 'B3',
            'remark' => 'GOOD',
        ]);
});

it('charges NBAIS fetch separately and returns candidate plus result data', function () {
    $user = createResultApiUser(100);
    createResultService('nbais-result-form', 4);
    $fetchService = createResultService('nbais-result-fetch', 35);

    CustomerServicePricing::create([
        'user_id' => $user->id,
        'verification_service_id' => $fetchService->id,
        'price' => 19,
        'is_active' => true,
    ]);

    app()->instance(NbaisResult::class, new class extends NbaisResult
    {
        public function fetchResult(array $params): string
        {
            return '<html>NBAIS result</html>';
        }

        public function parseResult(string $html): array
        {
            return [
                'status' => 'success',
                'candidate' => [
                    'name' => 'NBAIS Candidate',
                    'exam_number' => '481634346OS',
                ],
                'result' => [
                    'board' => 'NBAIS',
                    'subjects' => [
                        ['subject' => 'ARABIC LANGUAGE', 'grade' => 'B3', 'remark' => 'GOOD', 'score' => 'GOOD'],
                    ],
                ],
                'subjects' => [
                    ['subject' => 'ARABIC LANGUAGE', 'grade' => 'B3', 'remark' => 'GOOD', 'score' => 'GOOD'],
                ],
                'overall' => null,
            ];
        }
    });

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/results/nbais/fetch', [
        'year' => '2022',
        'month' => 'Nov/Dec',
        'exam_no' => '481634346OS',
        'pin' => '123456789012',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.board', 'NBAIS')
        ->assertJsonPath('data.candidate.name', 'NBAIS Candidate')
        ->assertJsonPath('data.result.board', 'NBAIS')
        ->assertJsonPath('data.result.subjects.0.subject', 'ARABIC LANGUAGE');

    expect((float) $user->wallet()->first()->fresh()->balance)->toBe(81.0);

    $request = VerificationRequest::where('verification_service_id', $fetchService->id)->first();
    expect($request)->not->toBeNull()
        ->and((float) $request->amount_charged)->toBe(19.0)
        ->and($request->search_parameter)->toBe('481634346OS')
        ->and($request->request_data['action'])->toBe('fetch');
});

it('requires NBAIS form data for both result lookup stages', function () {
    $fields = collect(app(NbaisResult::class)->formFields())->keyBy('name');

    expect($fields)->toHaveKeys([
        'year',
        'month',
        'exam_no',
        'pin',
    ])
        ->and($fields)->not->toHaveKeys(['parent_cat', 'sub_cat', 'month-select', 'exam_type', 'serial'])
        ->and($fields['pin']['required'])->toBeTrue();
});

it('uses the new NBAIS check and pin session flow', function () {
    $gateway = new class extends NbaisResult
    {
        public array $calls = [];

        protected function request(string $url, string $method, ?array $payload, array $headers, string $cookieJar): string
        {
            $this->calls[] = compact('url', 'method', 'payload');

            return match (count($this->calls)) {
                1 => '<form action="/check" method="POST"><input type="hidden" name="_token" value="csrf-check"></form>',
                2 => '<h2>Candidate Found</h2><form action="/pin" method="POST"><input type="hidden" name="_token" value="csrf-pin"><input name="pin"></form>',
                3 => '<html><body><div class="sheet-label">Candidate Name</div><div class="sheet-value">Candidate</div><table class="results"><tr><th>Subject</th><th>Grade</th></tr><tr><td>ARABIC LANGUAGE</td><td>A1</td></tr></table></body></html>',
                default => throw new RuntimeException('Unexpected NBAIS request'),
            };
        }
    };

    $html = $gateway->fetchResult([
        'exam_no' => '481634346OS',
        'year' => '2022',
        'month' => 'Nov/Dec',
        'pin' => '123456789012',
        'parent_cat' => 'ignored',
        'sub_cat' => 'ignored',
        'exam_type' => 'ignored',
        'serial' => 'ignored',
    ]);

    expect($html)->toContain('table class="results"')
        ->and($gateway->calls)->toHaveCount(3)
        ->and($gateway->calls[0]['method'])->toBe('GET')
        ->and($gateway->calls[0]['url'])->toEndWith('/check')
        ->and($gateway->calls[1]['method'])->toBe('POST')
        ->and($gateway->calls[1]['url'])->toEndWith('/check')
        ->and($gateway->calls[1]['payload'])->toBe([
            'exam_no' => '481634346OS',
            'year' => '2022',
            'month' => 'Nov/Dec',
            '_token' => 'csrf-check',
            'website' => '',
        ])
        ->and($gateway->calls[2]['method'])->toBe('POST')
        ->and($gateway->calls[2]['url'])->toEndWith('/pin')
        ->and($gateway->calls[2]['payload'])->toBe([
            'pin' => '123456789012',
            '_token' => 'csrf-pin',
            'website' => '',
        ]);
});

it('returns a clear NBAIS internal second stage error when a pin form is returned', function () {
    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <form action="validate-pin.php" method="POST">
        <input type="hidden" name="exam_no" value="481634346OS">
        <input type="text" name="pin" value="">
    </form>
</body>
</html>
HTML;

    $parsed = app(NbaisResult::class)->parseResult($html);

    expect($parsed['status'])->toBe('error')
        ->and($parsed['code'])->toBe('NBAIS_SECOND_STAGE_NOT_COMPLETED')
        ->and($parsed['message'])->toContain('PIN validation page');
});

it('maps NBAIS portal notification errors into structured errors', function () {
    $parsed = app(NbaisResult::class)->parseResult('<script>ecertNotify("Card Limit Reached", "error")</script>');

    expect($parsed['status'])->toBe('error')
        ->and($parsed['code'])->toBe('CARD_LIMIT_REACHED')
        ->and($parsed['message'])->toBe('Card Limit Reached');
});

it('maps html error pages for WAEC and NECO into structured errors', function () {
    $waec = app(WAECResult::class)->parseResult('<html><body><div class="alert alert-danger">Invalid card details supplied.</div></body></html>');
    $neco = app(NECOResult::class)->parseResult('<html><body><h3>Invalid token supplied.</h3></body></html>');

    expect($waec['status'])->toBe('error')
        ->and($waec['code'])->toBe('INVALID_PIN')
        ->and($waec['message'])->toBe('Invalid card details.')
        ->and($neco['status'])->toBe('error')
        ->and($neco['code'])->toBe('INVALID_PIN')
        ->and($neco['message'])->toBe('Invalid token supplied.');
});

it('parses NECO e-Verify successful JSON into candidate and subject details', function () {
    $json = <<<'JSON'
{
    "status": "200",
    "message": "Result verification successful.",
    "details": {
        "trackingId": "5c597e41-1264-4412-92da-cedcec838891",
        "candidateNo": "30231645GF",
        "candidateName": "ISHAQ KHADIJAT ALIYU",
        "sex": "FEMALE",
        "passport": "",
        "schoolNumber": "0050114",
        "school": "NADAWA INTERNATIONAL COLLEGE, MARABA GUMAU",
        "receiptNumber": "311479699568",
        "dateOfBirth": "",
        "requestingInstitution": "API",
        "stateOfOrigin": "",
        "requestedBy": "Online Service",
        "requestTimeStamp": "2026-06-27T00:00:00+01:00",
        "numberOfSubjects": 9,
        "examYear": "2013",
        "examType": "SSCE Internal",
        "results": [
            {"code": "101", "subject": "English Language", "grade": "C5"},
            {"code": "501", "subject": "Mathematics", "grade": "C5"},
            {"code": "104", "subject": "Hausa Language", "grade": "C6"},
            {"code": "203", "subject": "Islamic Studies", "grade": "C6"},
            {"code": "302", "subject": "Economics", "grade": "C6"},
            {"code": "502", "subject": "Biology", "grade": "C6"},
            {"code": "503", "subject": "Chemistry", "grade": "C5"},
            {"code": "505", "subject": "Physics", "grade": "D7"},
            {"code": "701", "subject": "Agricultural Science", "grade": "C6"}
        ]
    }
}
JSON;

    $parsed = app(NecoEVerify::class)->parseResult($json);

    expect($parsed['status'])->toBe('success')
        ->and($parsed['candidate']['candidate_name'])->toBe('ISHAQ KHADIJAT ALIYU')
        ->and($parsed['candidate']['exam_number'])->toBe('30231645GF')
        ->and($parsed['candidate']['exam_year'])->toBe('2013')
        ->and($parsed['candidate']['exam_type'])->toBe('SSCE Internal')
        ->and($parsed['candidate']['centre'])->toBe('NADAWA INTERNATIONAL COLLEGE, MARABA GUMAU')
        ->and($parsed['candidate']['tracking_id'])->toBe('5c597e41-1264-4412-92da-cedcec838891')
        ->and($parsed['subjects'])->toHaveCount(9)
        ->and($parsed['subjects'][0])->toMatchArray([
            'code' => '101',
            'subject' => 'English Language',
            'grade' => 'C5',
            'score' => null,
        ])
        ->and($parsed['result']['raw']['numberOfSubjects'])->toBe(9);
});

it('defines NABTEB eWorld form fields from the live checker flow', function () {
    $fields = collect(app(NabtebResult::class)->formFields())->keyBy('name');

    expect($fields)->toHaveKeys([
        'candid',
        'examtype',
        'examyear',
        'serial',
        'pin',
    ])
        ->and($fields['examtype']['options'])->toContain(['value' => '01', 'label' => 'MAY/JUN'])
        ->and($fields['examtype']['options'])->toContain(['value' => '07', 'label' => 'Common Entrance'])
        ->and($fields['examyear']['options'][0])->toBe(['value' => '2025', 'label' => '2025'])
        ->and($fields['serial']['required'])->toBeTrue()
        ->and($fields['pin']['required'])->toBeTrue();
});

it('parses NABTEB html result into candidate and result payloads', function () {
    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <table>
        <tr><td>Candidate Name</td><td>JOHN NABTEB</td></tr>
        <tr><td>Candidate Number</td><td>38001178</td></tr>
        <tr><td>Exam Type</td><td>MAY/JUN</td></tr>
        <tr><td>Exam Year</td><td>2024</td></tr>
        <tr><td>Centre Name</td><td>FEDERAL TECHNICAL COLLEGE</td></tr>
    </table>
    <table>
        <tr><th>Subject</th><th>Grade</th><th>Remark</th></tr>
        <tr><td>ENGLISH LANGUAGE</td><td>B3</td><td>GOOD</td></tr>
        <tr><td>MATHEMATICS</td><td>A1</td><td>EXCELLENT</td></tr>
    </table>
</body>
</html>
HTML;

    $parsed = app(NabtebResult::class)->parseResult($html);

    expect($parsed['status'])->toBe('success')
        ->and($parsed['candidate']['name'])->toBe('JOHN NABTEB')
        ->and($parsed['candidate']['exam_number'])->toBe('38001178')
        ->and($parsed['result']['board'])->toBe('NABTEB')
        ->and($parsed['result']['subjects'])->toHaveCount(2)
        ->and($parsed['result']['subjects'][1])->toMatchArray([
            'subject' => 'MATHEMATICS',
            'grade' => 'A1',
            'remark' => 'EXCELLENT',
        ]);
});

it('parses NABTEB eWorld result pages that use pass grade codes', function () {
    $html = <<<'HTML'
<!doctype html>
<html>
<body>
    <table>
        <tr><td>NABTEBeWorld NABTEB - Results Disclaimer : The results given below are correct at the time of release of the results. The Board and its agents accept no responsibility thereafter for errors or omissions caused as a result of their transmission via the Internet.</td></tr>
        <tr><td>NABTEB - Results</td></tr>
        <tr><td>Disclaimer : The results given below are correct at the time of release of the results. The Board and its agents accept no responsibility thereafter for errors or omissions caused as a result of their transmission via the Internet.</td></tr>
        <tr><td>Candidate's Details</td></tr>
        <tr><td>Candidate Number</td><td>13123006</td></tr>
        <tr><td>Candidate Name</td><td>TEST NABTEB CANDIDATE</td></tr>
        <tr><td>Type of Examination</td><td>NOVEMBER/DECEMBER, 2021</td></tr>
        <tr><td>Trade Name</td><td>GENERAL EDUCATION</td></tr>
        <tr><td>Examination Centre</td><td>TEST CENTRE</td></tr>
        <tr><td>Card Details</td></tr>
        <tr><td>Card use</td><td>4 of 5</td></tr>
        <tr><td>Results</td></tr>
        <tr><td>TRADE RELATED</td></tr>
        <tr><td>COMMERCE</td><td>P7</td></tr>
        <tr><td>GOVERNMENT</td><td>P7</td></tr>
        <tr><td>GENERAL EDUCATION</td></tr>
        <tr><td>ENGLISH LANGUAGE</td><td>C4</td></tr>
        <tr><td>MATHEMATICS</td><td>A1</td></tr>
        <tr><td>ECONOMICS</td><td>C5</td></tr>
        <tr><td>LITERATURE-IN-ENGLISH</td><td>C5</td></tr>
        <tr><td>INFORMATION AND COMMUNICATIONS TECHNOLOGY</td><td>C4</td></tr>
    </table>
</body>
</html>
HTML;

    $parsed = app(NabtebResult::class)->parseResult($html);

    expect($parsed['status'])->toBe('success')
        ->and($parsed['candidate']['name'])->toBe('TEST NABTEB CANDIDATE')
        ->and($parsed['candidate']['exam_number'])->toBe('13123006')
        ->and($parsed['candidate']['exam_year'])->toBe('2021')
        ->and($parsed['candidate']['exam_type'])->toBe('NOVEMBER/DECEMBER, 2021')
        ->and($parsed['result']['subjects'])->toHaveCount(7)
        ->and($parsed['result']['subjects'][0])->toMatchArray([
            'subject' => 'COMMERCE',
            'grade' => 'P7',
        ]);
});

it('maps NABTEB html error responses into structured errors', function () {
    $parsed = app(NabtebResult::class)->parseResult('<html><head><title>Length Required</title></head><body><h2>Length Required</h2><p>HTTP Error 411. The request must be chunked or have a content length.</p></body></html>');

    expect($parsed['status'])->toBe('error')
        ->and($parsed['code'])->toBe('UNKNOWN_ERROR')
        ->and($parsed['message'])->toBe('Length Required');
});

it('charges NABTEB fetch separately and returns candidate plus result data', function () {
    $user = createResultApiUser(100);
    createResultService('nabteb-result-form', 4);
    $fetchService = createResultService('nabteb-result-fetch', 35);

    CustomerServicePricing::create([
        'user_id' => $user->id,
        'verification_service_id' => $fetchService->id,
        'price' => 21,
        'is_active' => true,
    ]);

    app()->instance(NabtebResult::class, new class extends NabtebResult
    {
        public function fetchResult(array $params): string
        {
            return '<html>NABTEB result</html>';
        }

        public function parseResult(string $html): array
        {
            return [
                'status' => 'success',
                'candidate' => [
                    'name' => 'NABTEB Candidate',
                    'exam_number' => '38001178',
                ],
                'result' => [
                    'board' => 'NABTEB',
                    'subjects' => [
                        ['subject' => 'COMMERCE', 'grade' => 'B3', 'remark' => 'GOOD', 'score' => 'GOOD'],
                    ],
                ],
                'subjects' => [
                    ['subject' => 'COMMERCE', 'grade' => 'B3', 'remark' => 'GOOD', 'score' => 'GOOD'],
                ],
                'overall' => null,
            ];
        }
    });

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/results/nabteb/fetch', [
        'candid' => '38001178',
        'examtype' => '01',
        'examyear' => '2024',
        'serial' => 'N123456789',
        'pin' => '012345678912',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.board', 'NABTEB')
        ->assertJsonPath('data.candidate.name', 'NABTEB Candidate')
        ->assertJsonPath('data.result.board', 'NABTEB')
        ->assertJsonPath('data.result.subjects.0.subject', 'COMMERCE');

    expect((float) $user->wallet()->first()->fresh()->balance)->toBe(79.0);

    $request = VerificationRequest::where('verification_service_id', $fetchService->id)->first();
    expect($request)->not->toBeNull()
        ->and((float) $request->amount_charged)->toBe(21.0)
        ->and($request->search_parameter)->toBe('38001178')
        ->and($request->request_data['parameters']['pin'])->toBe('***REDACTED***')
        ->and($request->request_data['parameters']['serial'])->toBe('***REDACTED***');
});
