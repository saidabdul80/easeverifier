<?php

use Illuminate\Support\Facades\Http;

function documentationExampleInputs(): array
{
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/result-evaluator-cases.json')), true, 512, JSON_THROW_ON_ERROR);

    return [
        'portal' => json_encode($fixture['portal'], JSON_THROW_ON_ERROR),
        'result' => json_encode($fixture['result'], JSON_THROW_ON_ERROR),
        'adapter' => '{}',
    ];
}

it('runs the downloaded PHP evaluator for a public example without calling external services', function () {
    Http::fake();

    $this->postJson(route('documentation.libraries.run'), documentationExampleInputs())
        ->assertOk()
        ->assertJsonPath('runtime', 'PHP')
        ->assertJsonPath('version', '1.1.0')
        ->assertJsonPath('outcome.code', 'REQUIREMENTS_MET')
        ->assertJsonPath('outcome.can_continue', true);

    Http::assertNothingSent();
});

it('shows actual name and grade failures from the supplied data', function (string $scenario, string $expected) {
    $inputs = documentationExampleInputs();
    $result = json_decode($inputs['result'], true);
    if ($scenario === 'name') {
        $result['data']['candidate']['name'] = 'Chinedu Okafor';
    } else {
        foreach ($result['data']['subjects'] as &$subject) {
            $subject['grade'] = 'F9';
        }
    }
    $inputs['result'] = json_encode($result);

    $this->postJson(route('documentation.libraries.run'), $inputs)
        ->assertOk()->assertJsonPath('outcome.code', $expected)
        ->assertJsonPath('outcome.can_continue', false);
})->with([['name', 'OWNERSHIP_MISMATCH'], ['grade', 'GRADES_BELOW_REQUIREMENT']]);

it('returns configuration errors as an evaluator outcome', function () {
    $inputs = documentationExampleInputs();
    $inputs['portal'] = '{"student":{"name":"Amina Ibrahim"}}';

    $this->postJson(route('documentation.libraries.run'), $inputs)
        ->assertOk()->assertJsonPath('outcome.code', 'CONFIGURATION_ERROR')
        ->assertJsonPath('outcome.can_autofill', false);
});

it('rejects invalid, oversized, nonobject, or missing input', function (string $field, mixed $value) {
    $inputs = documentationExampleInputs();
    if ($value === null) {
        unset($inputs[$field]);
    } else {
        $inputs[$field] = $value;
    }

    $this->postJson(route('documentation.libraries.run'), $inputs)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['portal', '{broken'],
    ['result', '<?php echo "not executable";'],
    ['adapter', '[1,2]'],
    ['portal', 'null'],
    ['result', 'true'],
    ['adapter', null],
    ['portal', json_encode(['name' => str_repeat('a', 20001)])],
    ['adapter', str_repeat('{"nested":', 35).'{}'.str_repeat('}', 35)],
]);

it('limits repeated server example runs', function () {
    $inputs = documentationExampleInputs();
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])
            ->postJson(route('documentation.libraries.run'), $inputs)->assertOk();
    }
    $this->postJson(route('documentation.libraries.run'), $inputs)->assertTooManyRequests();
});
