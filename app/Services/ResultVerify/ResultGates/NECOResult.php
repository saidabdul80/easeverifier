<?php

namespace App\Services\ResultVerify\ResultGates;

use App\Services\ResultVerify\ResultInterface;
use RuntimeException;

class NECOResult implements ResultInterface
{
    private string $baseUrl = 'https://result.api.neco.gov.ng/api/results/check';

    private string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    private array $headers = [
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Accept-Encoding: gzip, deflate, br, zstd',
        'Origin: https://results.neco.gov.ng',
        'Referer: https://results.neco.gov.ng/',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-site',
        'sec-ch-ua: "Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "macOS"',
    ];

    public function formFields(): array
    {
        return [
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
                    ['value' => 'ssce_int', 'label' => 'SSCE Internal'],
                    ['value' => 'ssce_ext', 'label' => 'SSCE External'],
                    ['value' => 'bece', 'label' => 'BECE'],
                    ['value' => 'ncee', 'label' => 'NCEE'],
                    ['value' => 'gifted', 'label' => 'GIFTED'],
                ],
            ],
            [
                'name' => 'reg_no',
                'label' => 'Examination Number',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'token',
                'label' => 'Result Checker Token',
                'type' => 'text',
                'required' => true,
            ],
        ];
    }

    public function fetchResult(array $params): string
    {
        $query = http_build_query([
            'exam_year' => $params['exam_year'] ?? '',
            'exam_type' => $params['exam_type'] ?? '',
            'reg_no' => $params['reg_no'] ?? '',
            'token' => $params['token'] ?? '',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '?' . $query,
            CURLOPT_HTTPGET => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("cURL error: {$error}");
        }

        if ($status >= 500) {
            throw new RuntimeException("HTTP error {$status} from NECO");
        }

        if (($status < 200 || $status >= 400) && !$this->looksLikeJson((string) $response)) {
            $message = $this->extractHtmlErrorMessage((string) $response) ?? "HTTP error {$status} from NECO";
            throw new RuntimeException($message);
        }
        \Log::debug($response);
        return (string) $response;
    }

    public function parseResult(string $html): array
    {
        $decoded = $this->decodeResponse($html);
        if (!is_array($decoded)) {
            $message = $this->extractHtmlErrorMessage($html);

            return [
                'status' => 'error',
                'code' => $message ? $this->mapHtmlErrorCode($message) : 'UNKNOWN_ERROR',
                'message' => $message ?: 'NECO returned an unreadable response.',
            ];
        }

        if ($this->responseLooksLikeError($decoded)) {
            return [
                'status' => 'error',
                'code' => $this->extractErrorCode($decoded),
                'message' => $this->extractErrorMessage($decoded),
            ];
        }

        $payload = $this->extractPayload($decoded);
        $candidate = $this->extractCandidate($payload, $decoded);
        $subjects = $this->extractSubjects($payload, $decoded);

        if (empty($subjects)) {
            return [
                'status' => 'error',
                'code' => 'RESULT_NOT_FOUND',
                'message' => 'No subject results were found. Please verify your details.',
            ];
        }

        return [
            'status' => 'success',
            'candidate' => $candidate,
            'subjects' => $subjects,
            'overall' => null,
        ];
    }

    private function responseLooksLikeError(array $decoded): bool
    {
        $status = strtolower((string) ($decoded['status'] ?? $decoded['message'] ?? $decoded['info'] ?? ''));
        $message = strtolower($this->extractErrorMessage($decoded));

        if (isset($decoded['success']) && $decoded['success'] === false) {
            return true;
        }

        if (isset($decoded['error']) && $decoded['error']) {
            return true;
        }

        if ((int) ($decoded['status'] ?? 0) >= 400) {
            return true;
        }

        return str_contains($status, 'error')
            || str_contains($status, 'bad_request')
            || str_contains($message, 'maximum result checks reached')
            || str_contains($message, 'maximum number of result inquiries')
            || str_contains($status, 'not_found')
            || str_contains($message, 'invalid')
            || str_contains($status, 'invalid')
            || str_contains($status, 'not found')
            || str_contains($status, 'failed');
    }

    private function extractErrorCode(array $decoded): string
    {
        $message = strtolower($this->extractErrorMessage($decoded));

        if (
            str_contains($message, 'maximum result checks reached')
            || str_contains($message, 'maximum number of result inquiries')
            || str_contains($message, 'everify portal')
        ) {
            return 'MAX_RESULT_CHECKS_REACHED';
        }

        if (str_contains($message, 'token') || str_contains($message, 'pin')) {
            return 'INVALID_PIN';
        }

        if (str_contains($message, 'reg') || str_contains($message, 'exam number') || str_contains($message, 'candidate')) {
            return 'INVALID_CANDIDATE';
        }

        if (str_contains($message, 'not found') || str_contains($message, 'no result')) {
            return 'RESULT_NOT_FOUND';
        }

        return 'UNKNOWN_ERROR';
    }

    private function extractErrorMessage(array $decoded): string
    {
        foreach (['info', 'message', 'error', 'detail', 'status'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                return $decoded[$key];
            }
        }

        return 'NECO returned an error.';
    }

    private function extractHtmlErrorMessage(string $html): ?string
    {
        $html = trim($html);
        if ($html === '') {
            return null;
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        $queries = [
            '//*[contains(@class, "alert")]',
            '//*[contains(@class, "error")]',
            '//*[contains(@class, "danger")]',
            '//*[contains(@id, "error")]',
            '//h1',
            '//h2',
            '//h3',
            '//p',
            '//title',
        ];

        foreach ($queries as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $message = $this->normalizeText((string) $node->textContent);
                if ($this->isUsefulErrorText($message)) {
                    return mb_substr($message, 0, 240);
                }
            }
        }

        $message = $this->normalizeText(strip_tags($html));

        return $this->isUsefulErrorText($message) ? mb_substr($message, 0, 240) : null;
    }

    private function mapHtmlErrorCode(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'token') || str_contains($lower, 'pin')) {
            return 'INVALID_PIN';
        }

        if (str_contains($lower, 'invalid') || str_contains($lower, 'candidate') || str_contains($lower, 'reg')) {
            return 'INVALID_CANDIDATE';
        }

        if (str_contains($lower, 'not found') || str_contains($lower, 'no result')) {
            return 'RESULT_NOT_FOUND';
        }

        return 'UNKNOWN_ERROR';
    }

    private function isUsefulErrorText(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        $lower = strtolower($message);

        if (in_array($lower, ['neco results', 'neco result checker', 'result checker'], true)) {
            return false;
        }

        if (mb_strlen($message) > 500 && !str_contains($lower, 'error') && !str_contains($lower, 'invalid') && !str_contains($lower, 'not found')) {
            return false;
        }

        return true;
    }

    private function normalizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function looksLikeJson(string $response): bool
    {
        $response = trim($response);

        if ($response === '' || ($response[0] !== '{' && $response[0] !== '[')) {
            return false;
        }

        json_decode($response, true);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function extractPayload(array $decoded): array
    {
        foreach (['data', 'result', 'payload', 'results'] as $key) {
            if (!array_key_exists($key, $decoded)) {
                continue;
            }

            if (is_array($decoded[$key])) {
                return $decoded[$key];
            }

            if (is_string($decoded[$key])) {
                $nested = $this->decodeResponse($decoded[$key]);
                if (is_array($nested)) {
                    return $nested;
                }
            }
        }

        return $decoded;
    }

    private function extractCandidate(array $payload, array $decoded): array
    {
        $source = array_merge($decoded, $payload);

        $name = $this->firstString($source, [
            'candidate_name',
            'candidateName',
            'full_name',
            'fullName',
            'name',
        ]);

        $examNumber = $this->firstString($source, [
            'exam_number',
            'examNumber',
            'reg_no',
            'regNo',
            'registration_number',
            'reg_number',
            'candidate_number',
        ]);

        $examYear = $this->firstString($source, [
            'exam_year',
            'examYear',
            'year',
        ]);

        $examType = $this->firstString($source, [
            'exam_type',
            'examType',
            'type',
        ]);

        $centreName = $this->firstString($source, [
            'centre_name',
            'center_name',
            'centre',
            'center',
            'school_name',
        ]);

        $centreNumber = $this->firstString($source, [
            'centre_number',
            'center_number',
            'school_code',
            'centre_code',
            'center_code',
        ]);

        return [
            'name' => $name,
            'candidate_name' => $name,
            'exam_number' => $examNumber,
            'exam_year' => $examYear,
            'exam_type' => $examType,
            'centre' => $centreName,
            'centre_name' => $centreName,
            'centre_number' => $centreNumber,
            'gender' => $this->firstString($source, ['gender']),
            'date_of_birth' => $this->firstString($source, ['dob', 'date_of_birth', 'dateOfBirth']),
        ];
    }

    private function extractSubjects(array $payload, array $decoded): array
    {
        $directSubjects = $this->extractFlatSubjects(array_merge($decoded, $payload));
        if ($directSubjects) {
            return $directSubjects;
        }

        $containers = [
            $payload['subjects'] ?? null,
            $payload['results'] ?? null,
            $payload['grades'] ?? null,
            $payload['result'] ?? null,
            $decoded['subjects'] ?? null,
            $decoded['results'] ?? null,
            $decoded['grades'] ?? null,
        ];

        foreach ($containers as $container) {
            if (!is_array($container)) {
                continue;
            }

            $subjects = [];
            foreach ($container as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $subject = $this->firstString($row, [
                    'subject',
                    'subject_name',
                    'subjectName',
                    'name',
                    'course_title',
                ]);

                $grade = $this->firstString($row, [
                    'grade',
                    'grade_value',
                    'gradeValue',
                    'value',
                ]);

                $remark = $this->firstString($row, [
                    'remark',
                    'remark_text',
                    'description',
                    'score',
                ]);

                if ($subject === null || $grade === null) {
                    continue;
                }

                $subjects[] = [
                    'subject' => trim($subject),
                    'grade' => strtoupper(trim($grade)),
                    'score' => $remark ? trim($remark) : null,
                ];
            }

            if ($subjects) {
                return $subjects;
            }
        }

        return [];
    }

    private function extractFlatSubjects(array $source): array
    {
        $numOfSubjects = (int) ($source['num_of_sub'] ?? 0);
        if ($numOfSubjects <= 0) {
            return [];
        }

        $subjects = [];

        for ($index = 1; $index <= $numOfSubjects; $index++) {
            $subject = $this->firstString($source, ["sub{$index}_name"]);
            $grade = $this->firstString($source, ["sub{$index}_grade"]);
            $remark = $this->firstString($source, ["sub{$index}_remark"]);

            if ($subject === null || $grade === null) {
                continue;
            }

            $subjects[] = [
                'subject' => trim($subject),
                'grade' => strtoupper(trim($grade)),
                'score' => $remark ? trim($remark) : null,
            ];
        }

        return $subjects;
    }

    private function decodeResponse(string $value): ?array
    {
        $decoded = json_decode(trim($value), true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (is_string($decoded)) {
            $decodedAgain = json_decode($decoded, true);
            if (is_array($decodedAgain)) {
                return $decodedAgain;
            }
        }

        return null;
    }

    private function firstString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_scalar($source[$key])) {
                $value = trim((string) $source[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function yearOptions(int $startYear = 2000): array
    {
        $currentYear = (int) date('Y');
        $options = [];

        for ($year = $currentYear; $year >= $startYear; $year--) {
            $options[] = [
                'value' => (string) $year,
                'label' => (string) $year,
            ];
        }

        return $options;
    }
}
