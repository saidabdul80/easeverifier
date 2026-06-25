<?php

namespace App\Services\ResultVerify;

interface ResultInterface
{
    /**
     * Structured field definitions the frontend uses to build the form dynamically.
     * Each item: { name, label, type, required, options?: [{value, label}] }
     */
    public function formFields(): array;

    /**
     * Fetch the raw HTML result page from the external board.
     */
    public function fetchResult(array $params): string;

    /**
     * Parse raw HTML from the board into a structured result array.
     *
     * Success:  ['status'=>'success', 'candidate'=>[...], 'subjects'=>[...], 'overall'=>?string]
     * Error:    ['status'=>'error',   'code'=>string,      'message'=>string]
     */
    public function parseResult(string $html): array;
}
