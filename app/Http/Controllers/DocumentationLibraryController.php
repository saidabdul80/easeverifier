<?php

namespace App\Http\Controllers;

use App\Http\Requests\RunDocumentationLibraryRequest;
use EaseVerifier\ResultEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentationLibraryController extends Controller
{
    public function run(RunDocumentationLibraryRequest $request): JsonResponse
    {
        $inputs = [];
        foreach ($request->validated() as $field => $json) {
            try {
                $value = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages([$field => 'Use JSON with fewer than 32 nested levels.']);
            }

            if (! is_array($value) || ! str_starts_with(ltrim($json), '{')) {
                throw ValidationException::withMessages([$field => 'Use a JSON object for this input.']);
            }

            $inputs[$field] = $value;
        }

        require_once resource_path('libraries/EaseVerifierResultEvaluator.php');

        return response()->json([
            'outcome' => ResultEvaluator::evaluate($inputs['portal'], $inputs['result'], $inputs['adapter']),
            'runtime' => 'PHP',
            'version' => ResultEvaluator::VERSION,
        ])->header('Cache-Control', 'no-store');
    }

    public function download(string $language): BinaryFileResponse
    {
        $files = [
            'php' => 'EaseVerifierResultEvaluator.php',
            'javascript' => 'easeverifier-result-evaluator.mjs',
        ];

        abort_unless(isset($files[$language]), 404);

        return response()->download(resource_path('libraries/'.$files[$language]), $files[$language], [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
