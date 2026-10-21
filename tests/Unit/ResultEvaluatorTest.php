<?php

use EaseVerifier\ResultEvaluator;

require_once dirname(__DIR__, 2).'/resources/libraries/EaseVerifierResultEvaluator.php';

dataset('portal result cases', function () {
    $fixtures = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/result-evaluator-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($fixtures['cases'] as $case) {
        $input = ['portal' => $fixtures['portal'], 'result' => $fixtures['result']];
        foreach ($case['set'] as $path => $value) {
            $target = &$input;
            foreach (explode('.', $path) as $key) {
                $target = &$target[$key];
            }
            $target = $value;
            unset($target);
        }
        yield $case['name'] => [$input['portal'], $input['result'], $case['expected']];
    }
});

it('returns the appropriate portal outcome without mutating its inputs', function (array $portal, array $result, array $expected) {
    $before = json_encode([$portal, $result], JSON_THROW_ON_ERROR);
    $outcome = ResultEvaluator::evaluate($portal, $result);

    foreach ($expected as $key => $value) {
        expect($outcome[$key])->toBe($value);
    }
    expect($outcome['message'])->toBe(ResultEvaluator::messageFor($outcome['code']))
        ->and($outcome['can_continue'])->toBe($outcome['code'] === 'REQUIREMENTS_MET')
        ->and(json_encode([$portal, $result], JSON_THROW_ON_ERROR))->toBe($before);
    if (in_array($outcome['code'], ['RESULT_PENDING', 'RESULT_FAILED', 'INVALID_RESULT'], true)) {
        expect($outcome['requirements']['rows'])->toBe([])
            ->and($outcome['requirements']['missing_subjects'])->toBe([]);
    }
})->with('portal result cases');

it('exposes standalone normalization and grading helpers', function () {
    expect(ResultEvaluator::normalizeSubject(' General Mathematics '))->toBe('mathematics')
        ->and(ResultEvaluator::normalizeSubject('ICT'))->toBe('computer studies')
        ->and(ResultEvaluator::gradeRank(' c6 '))->toBe(4)
        ->and(ResultEvaluator::gradeRank('P7'))->toBe(3)
        ->and(ResultEvaluator::gradeRank('WITHHELD'))->toBe(0)
        ->and(ResultEvaluator::messageFor('unknown'))->toBe('The result needs school review before continuing.');
});

it('reports invalid rules when requirements are called directly', function () {
    expect(fn () => ResultEvaluator::checkRequirements([], []))->toThrow(InvalidArgumentException::class);
});

dataset('portal subject matching cases', function () {
    $fixtures = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/result-evaluator-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($fixtures['subject_matches'] as $case) {
        yield $case['name'] => [$case];
    }
});

it('matches realistic subject labels and preserves portal subject identities', function (array $case) {
    $match = ResultEvaluator::matchSubject($case['subject'], $case['catalogue'], $case['options']);
    foreach ($case['expected'] as $key => $value) {
        expect($match[$key])->toBe($value);
    }
    if ($match['status'] === 'review') {
        expect($match['subject_id'])->toBeNull();
    }
})->with('portal subject matching cases');

dataset('portal adapter cases', function () {
    $fixtures = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/result-evaluator-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($fixtures['adapters'] as $case) {
        yield $case['name'] => [$case];
    }
});

it('adapts different existing portal structures without mutating the records', function (array $case) {
    $before = json_encode($case, JSON_THROW_ON_ERROR);
    $outcome = ResultEvaluator::evaluate($case['source'], $case['result'], $case['adapter']);
    expect($outcome['code'])->toBe($case['expected']['code'])
        ->and(json_encode($case, JSON_THROW_ON_ERROR))->toBe($before);
    if ($outcome['code'] === 'REQUIREMENTS_MET') {
        expect(array_column($outcome['requirements']['rows'], 'portal_subject_id'))->toHaveCount(5);
    }
    if ($outcome['code'] === 'CONFIGURATION_ERROR') {
        expect($outcome['configuration_errors'])->not->toBeEmpty();
    }
})->with('portal adapter cases');

it('accepts PDO-style objects and callable adapters for custom portal logic', function () {
    $fixtures = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/result-evaluator-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    $source = (object) ['custom_record' => $fixtures['portal']];
    $outcome = ResultEvaluator::evaluate($source, $fixtures['result'], fn (object $record): array => $record->custom_record);
    expect($outcome['code'])->toBe('REQUIREMENTS_MET');

    $outcome = ResultEvaluator::evaluate($fixtures['portal'], ['cache' => $fixtures['result']], [
        'result_adapter' => fn (array $response): array => $response['cache'],
    ]);
    expect($outcome['code'])->toBe('REQUIREMENTS_MET');
});
