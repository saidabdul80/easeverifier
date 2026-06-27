<?php

namespace App\Services\ResultVerify\ResultGates;

use App\Services\ResultVerify\ResultInterface;
use RuntimeException;

class NecoEVerify implements ResultInterface
{
    public function formFields(): array
    {
        return [
            //all commented not needed, also token is same as payRef
            [
                'name' => 'token',
                'label' => 'Verification Token / RRR',
                'type' => 'text',
                'required' => true,
            ],
            // [
            //     'name' => 'phone',
            //     'label' => 'Payer Phone Number',
            //     'type' => 'tel',
            //     'required' => true,
            // ],
            // [
            //     'name' => 'email',
            //     'label' => 'Payer Email',
            //     'type' => 'email',
            //     'required' => true,
            // ],
            // [
            //     'name' => 'payref',
            //     'label' => 'Payment Reference (fallback)',
            //     'type' => 'text',
            //     'required' => false,
            // ],
            [
                'name' => 'examno',
                'label' => 'Examination Number',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'exam_year',
                'label' => 'Examination Year',
                'type' => 'select',
                'required' => true,
                'options' => $this->yearOptions(2000),
            ],
            [
                'name' => 'exam_type',
                'label' => 'Examination Type',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'SSCEInt', 'label' => 'SSCE Internal'],
                    ['value' => 'SSCEExt', 'label' => 'SSCE External'],
                ],
            ],
        ];
    }

    public function fetchResult(array $params): string
    {
       
        $baseUrl = rtrim((string) 'https://everify.neco.gov.ng');
        $bearerToken = trim((string) config('services.neco_everify.bearer_token', ''));
        $timeout = $this->boundedTimeout((int) config('services.neco_everify.timeout', 20));

        if ($bearerToken === '') {
            throw new RuntimeException('NECO e-Verify bearer token is not configured.');
        }

        $dataPayload = [
            'token' => trim((string) ($params['token'] ?? '')),
            'payref' => trim((string) ($params['token'] ?? '')),
            'examno' => trim((string) ($params['examno'] ?? $params['exam_number'] ?? '')),
            'exam_year' => (string)($params['exam_year'] ?? 0),
            'exam_type' => $this->normalizeExamType((string) ($params['exam_type'] ?? '')),
        ];

        $result = $this->postJson($baseUrl . '/api_core/single', $dataPayload, $bearerToken, $timeout);

        return $result['response'];
    }

    private function postJson(string $url, array $payload, string $bearerToken, int $timeout): array
    {
        $headers = [];
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Connection: keep-alive';


        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS =>  json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new RuntimeException('NECO e-Verify did not respond before the request timeout. Please try again shortly.');
        }

        if ($error) {
            throw new RuntimeException("cURL error: {$error}");
        }

        if ($response === false || trim((string) $response) === '') {
            throw new RuntimeException("Empty response from NECO e-Verify. HTTP status {$status}.");
        }

        if ($status >= 500) {
            throw new RuntimeException("HTTP error {$status} from NECO e-Verify.");
        }

        return [
            'status' => (int) $status,
            'response' => (string) $response,
        ];
    }

    private function responseLooksLikeProviderError(string $response, int $status): bool
    {
        if ($status >= 400) {
            return true;
        }

        $decoded = $this->decodeJson($response);
        if (!is_array($decoded)) {
            $message = strtolower(trim($response));

            return str_contains($message, 'error')
                || str_contains($message, 'could not')
                || str_contains($message, 'failed')
                || str_contains($message, 'unable');
        }

        if (isset($decoded['status']) && is_numeric($decoded['status']) && (int) $decoded['status'] >= 400) {
            return true;
        }

        if (isset($decoded['status_code']) && is_numeric($decoded['status_code']) && (int) $decoded['status_code'] >= 400) {
            return true;
        }

        return !empty($decoded['error']);
    }

    private function decodeJson(string $response): ?array
    {
        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function boundedTimeout(int $configuredTimeout): int
    {
        if ($configuredTimeout <= 0) {
            return 20;
        }

        return min($configuredTimeout, 20);
    }

    private function normalizeExamType(string $examType): string
    {
        $value = trim($examType);
        $compact = strtoupper(str_replace([' ', '-', '_'], '', $value));

        return match ($compact) {
            'SSCEINTERNAL', 'SSCEINT', 'INTERNAL' => 'SSCEInt',
            'SSCEEXTERNAL', 'SSCEEXT', 'EXTERNAL' => 'SSCEExt',
            default => $value,
        };
    }

    public function parseResult(string $html): array
    {
        $decoded = json_decode($html, true);

        if (!is_array($decoded)) {
            return [
                'status' => 'error',
                'code' => 'UNREADABLE_RESPONSE',
                'message' => $this->extractHtmlErrorMessage($html) ?: 'NECO e-Verify returned an unreadable response.',
            ];
        }

        if ($this->isErrorResponse($decoded)) {
            return [
                'status' => 'error',
                'code' => $this->errorCode($decoded),
                'message' => $this->errorMessage($decoded),
            ];
        }

        if ($this->isSuccessfulNecoEVerifyResponse($decoded)) {
            return $this->parseSuccessfulNecoEVerifyResponse($decoded);
        }

        $payload = $this->payload($decoded);
        $candidate = $this->candidate($payload, $decoded);
        $subjects = $this->subjects($payload, $decoded);

        if (!$subjects) {
            return [
                'status' => 'error',
                'code' => 'RESULT_NOT_FOUND',
                'message' => 'No subject results were found in the NECO e-Verify response.',
            ];
        }

        return [
            'status' => 'success',
            'candidate' => $candidate,
            'subjects' => $subjects,
            'overall' => null,
            'result' => [
                'raw' => $payload,
            ],
        ];
    }

    private function isSuccessfulNecoEVerifyResponse(array $decoded): bool
    {
        return isset($decoded['details'])
            && is_array($decoded['details'])
            && isset($decoded['details']['results'])
            && is_array($decoded['details']['results']);
    }

    private function parseSuccessfulNecoEVerifyResponse(array $decoded): array
    {
        $details = $decoded['details'];
        $subjects = $this->subjects($details, $decoded);

        if (!$subjects) {
            return [
                'status' => 'error',
                'code' => 'RESULT_NOT_FOUND',
                'message' => 'No subject results were found in the NECO e-Verify response.',
            ];
        }

        $candidate = [
            'name' => $details['candidateName'] ?? null,
            'candidate_name' => $details['candidateName'] ?? null,
            'exam_number' => $details['candidateNo'] ?? null,
            'exam_year' => $details['examYear'] ?? null,
            'exam_type' => $details['examType'] ?? null,
            'centre' => $details['school'] ?? null,
            'sex' => $details['sex'] ?? null,
            'passport' => $details['passport'] ?? null,
            'school_number' => $details['schoolNumber'] ?? null,
            'receipt_number' => $details['receiptNumber'] ?? null,
            'date_of_birth' => $details['dateOfBirth'] ?? null,
            'state_of_origin' => $details['stateOfOrigin'] ?? null,
            'tracking_id' => $details['trackingId'] ?? null,
            'requesting_institution' => $details['requestingInstitution'] ?? null,
            'requested_by' => $details['requestedBy'] ?? null,
            'request_timestamp' => $details['requestTimeStamp'] ?? null,
        ];

        return [
            'status' => 'success',
            'candidate' => $candidate,
            'subjects' => $subjects,
            'overall' => null,
            'result' => [
                'tracking_id' => $details['trackingId'] ?? null,
                'receipt_number' => $details['receiptNumber'] ?? null,
                'school_number' => $details['schoolNumber'] ?? null,
                'number_of_subjects' => $details['numberOfSubjects'] ?? count($subjects),
                'requesting_institution' => $details['requestingInstitution'] ?? null,
                'requested_by' => $details['requestedBy'] ?? null,
                'request_timestamp' => $details['requestTimeStamp'] ?? null,
                'raw' => $details,
            ],
        ];
    }

    private function isErrorResponse(array $decoded): bool
    {
        $status = $decoded['status'] ?? $decoded['status_code'] ?? null;

        if (is_numeric($status) && (int) $status >= 400) {
            return true;
        }

        if (($decoded['success'] ?? true) === false) {
            return true;
        }

        if (!empty($decoded['error'])) {
            return true;
        }

        $message = strtolower($this->errorMessage($decoded));

        return str_contains($message, 'error')
            || str_contains($message, 'invalid')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'forbidden')
            || str_contains($message, 'not found')
            || str_contains($message, 'bad request');
    }

    private function errorCode(array $decoded): string
    {
        $status = (int) ($decoded['status'] ?? $decoded['status_code'] ?? 0);
        $message = strtolower($this->errorMessage($decoded));

        if ($status === 401 || str_contains($message, 'auth') || str_contains($message, 'token')) {
            return 'AUTHENTICATION_ERROR';
        }

        if ($status === 403 || str_contains($message, 'access') || str_contains($message, 'forbidden')) {
            return 'AUTHORIZATION_ERROR';
        }

        if ($status === 400 || str_contains($message, 'invalid') || str_contains($message, 'validation')) {
            return 'VALIDATION_ERROR';
        }

        if (str_contains($message, 'not found') || str_contains($message, 'no result')) {
            return 'RESULT_NOT_FOUND';
        }

        return 'UNKNOWN_ERROR';
    }

    private function errorMessage(array $decoded): string
    {
        foreach (['message', 'error', 'detail', 'info'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                return $decoded[$key];
            }
        }

        if (isset($decoded['errors']) && is_array($decoded['errors'])) {
            return collect($decoded['errors'])->flatten()->filter()->implode(' ');
        }

        return 'NECO e-Verify returned an error.';
    }

    private function payload(array $decoded): array
    {
        $payload = $decoded['details']
            ?? $decoded['data']
            ?? $decoded['result']
            ?? $decoded['results']
            ?? $decoded;

        if (is_string($payload)) {
            $json = json_decode($payload, true);
            $payload = is_array($json) ? $json : ['details' => $payload];
        }

        if (is_array($payload) && array_is_list($payload) && isset($payload[0]) && is_array($payload[0])) {
            return $payload[0];
        }

        return is_array($payload) ? $payload : [];
    }

    private function candidate(array $payload, array $decoded): array
    {
        $source = array_merge($decoded, $payload);

        return [
            'name' => $this->firstValue($source, ['candidate_name', 'candidateName', 'name', 'full_name', 'fullname']),
            'candidate_name' => $this->firstValue($source, ['candidate_name', 'candidateName', 'name', 'full_name', 'fullname']),
            'exam_number' => $this->firstValue($source, ['examno', 'candidateNo', 'exam_no', 'exam_number', 'examination_number', 'candidate_no']),
            'exam_year' => $this->firstValue($source, ['exam_year', 'examYear', 'year']),
            'exam_type' => $this->firstValue($source, ['exam_type', 'examType', 'type']),
            'centre' => $this->firstValue($source, ['centre', 'center', 'school', 'school_name']),
            'sex' => $this->firstValue($source, ['sex', 'gender']),
            'passport' => $this->firstValue($source, ['passport']),
            'school_number' => $this->firstValue($source, ['schoolNumber', 'school_number']),
            'receipt_number' => $this->firstValue($source, ['receiptNumber', 'receipt_number']),
            'date_of_birth' => $this->firstValue($source, ['dateOfBirth', 'date_of_birth', 'dob']),
            'state_of_origin' => $this->firstValue($source, ['stateOfOrigin', 'state_of_origin']),
            'tracking_id' => $this->firstValue($source, ['trackingId', 'tracking_id']),
            'requesting_institution' => $this->firstValue($source, ['requestingInstitution', 'requesting_institution']),
            'requested_by' => $this->firstValue($source, ['requestedBy', 'requested_by']),
            'request_timestamp' => $this->firstValue($source, ['requestTimeStamp', 'request_timestamp']),
        ];
    }

    private function subjects(array $payload, array $decoded): array
    {
        $subjectPayload = $this->firstArray($payload, ['subjects', 'subject_results', 'result', 'results', 'grades'])
            ?? $this->firstArray($decoded, ['subjects', 'subject_results', 'result', 'results', 'grades'])
            ?? [];

        if ($subjectPayload === [] && array_is_list($payload)) {
            $subjectPayload = $payload;
        }

        if (isset($subjectPayload['subjects']) && is_array($subjectPayload['subjects'])) {
            $subjectPayload = $subjectPayload['subjects'];
        }

        $subjects = [];

        foreach ($subjectPayload as $key => $row) {
            if (is_string($row)) {
                $subjects[] = [
                    'subject' => is_string($key) ? $key : $row,
                    'grade' => is_string($key) ? $row : null,
                    'score' => null,
                ];
                continue;
            }

            if (!is_array($row)) {
                continue;
            }

            $subject = $this->firstValue($row, ['subject', 'subject_name', 'subjectName', 'course', 'name']);
            $grade = $this->firstValue($row, ['grade', 'result', 'score_grade', 'scoreGrade']);
            $score = $this->firstValue($row, ['score', 'mark', 'marks']);

            if (!$subject && is_string($key)) {
                $subject = $key;
            }

            if ($subject || $grade || $score) {
                $subjects[] = [
                    'code' => $this->firstValue($row, ['code', 'subject_code', 'subjectCode']),
                    'subject' => $subject,
                    'grade' => $grade,
                    'score' => $score,
                ];
            }
        }

        return array_values(array_filter($subjects, fn (array $subject) => filled($subject['subject'] ?? null)));
    }

    private function firstValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && filled($data[$key]) && !is_array($data[$key])) {
                return $data[$key];
            }
        }

        return null;
    }

    private function firstArray(array $data, array $keys): ?array
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return null;
    }

    private function extractHtmlErrorMessage(string $html): ?string
    {
        $message = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

        return $message !== '' ? mb_substr($message, 0, 240) : null;
    }

    private function yearOptions(int $startYear): array
    {
        $current = (int) date('Y');
        $years = [];

        for ($year = $current; $year >= $startYear; $year--) {
            $years[] = ['value' => (string) $year, 'label' => (string) $year];
        }

        return $years;
    }
}
