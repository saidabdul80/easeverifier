<?php

use Symfony\Component\HttpFoundation\BinaryFileResponse;

it('downloads standalone library source as an attachment without authentication', function (string $language, string $filename, string $sourceMarker) {
    $response = $this->get(route('documentation.libraries.download', ['language' => $language]));

    $response->assertOk()
        ->assertDownload($filename)
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($response->baseResponse->getFile()->getRealPath())->toBe(realpath(resource_path('libraries/'.$filename)))
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain($sourceMarker);
})->with([
    ['php', 'EaseVerifierResultEvaluator.php', 'namespace EaseVerifier;'],
    ['javascript', 'easeverifier-result-evaluator.mjs', 'export class ResultEvaluator'],
]);

it('rejects unsupported download names', function (string $language) {
    $this->get('/documentation/libraries/'.$language)->assertNotFound();
})->with(['python', '.env', 'web.php', 'javascript.php']);
