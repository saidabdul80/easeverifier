<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ResultVerify\ResultGates\NbaisResult;
use App\Services\ResultVerify\ResultVerificationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultVerificationController extends Controller
{
    public function __construct(
        protected ResultVerificationEngine $resultVerificationEngine
    ) {}

    public function form(Request $request, string $board): JsonResponse
    {
        $apiKey = $request->get('api_key');
        $branch = $request->get('branch');

        $result = $this->resultVerificationEngine
            ->setEnvironment($apiKey)
            ->formData(
                user: $request->user(),
                board: $board,
                source: 'api',
                ipAddress: $request->ip(),
                branch: $branch,
            );

        if ($result->isSuccessful()) {
            $data = $result->getData();

            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $data,
                'response_time' => $result->responseTime,
                'message' => strtoupper($board).' result form loaded successfully',
                'sandbox' => $data['_sandbox'] ?? false,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result->getErrorMessage(),
            'error_code' => $result->errorCode,
        ], $this->statusForError($result->errorCode));
    }

    public function fetch(Request $request, string $board): JsonResponse
    {
        $apiKey = $request->get('api_key');
        $branch = $request->get('branch');

        $result = $this->resultVerificationEngine
            ->setEnvironment($apiKey)
            ->verify(
                user: $request->user(),
                board: $board,
                params: $request->except(['api_key', 'branch']),
                source: 'api',
                ipAddress: $request->ip(),
                branch: $branch,
            );

        if ($result->isSuccessful()) {
            $data = $result->getData();

            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $data,
                'response_time' => $result->responseTime,
                'message' => strtoupper($board).' result fetched successfully',
                'sandbox' => $data['_sandbox'] ?? false,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result->getErrorMessage(),
            'error_code' => $result->errorCode,
        ], $this->statusForError($result->errorCode));
    }

    public function nbaisSchools(Request $request, NbaisResult $nbaisResult): JsonResponse
    {
        $validated = $request->validate([
            'parent_cat' => 'required|string|max:10',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $nbaisResult->fetchSchools($validated['parent_cat']),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
                'error_code' => 'SCHOOL_LOOKUP_FAILED',
            ], 400);
        }
    }

    protected function statusForError(?string $errorCode): int
    {
        return match ($errorCode) {
            'UNSUPPORTED_RESULT_BOARD' => 404,
            'VALIDATION_ERROR' => 422,
            'RESULT_FETCH_DISABLED' => 403,
            'INSUFFICIENT_FUNDS' => 402,
            default => 400,
        };
    }
}
