<?php

namespace App\Services\ResultVerify\ResultGates;

use App\Services\ResultVerify\ResultInterface;
use RuntimeException;

class NabtebResult implements ResultInterface
{
    private string $baseUrl = 'https://eworld.nabteb.gov.ng';

    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    private array $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
    ];

    public function formFields(): array
    {
        return [
            [
                'name' => 'candid',
                'label' => 'Candidate Number',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'examtype',
                'label' => 'Examination Type',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => '01', 'label' => 'MAY/JUN'],
                    ['value' => '02', 'label' => 'NOV/DEC'],
                    ['value' => '03', 'label' => 'Modular (March)'],
                    ['value' => '04', 'label' => 'Modular (December)'],
                    ['value' => '05', 'label' => 'Modular (June)'],
                    ['value' => '06', 'label' => 'GCE (A-Level)'],
                    ['value' => '07', 'label' => 'Common Entrance'],
                ],
            ],
            [
                'name' => 'examyear',
                'label' => 'Examination Year',
                'type' => 'select',
                'required' => true,
                'options' => $this->yearOptions(1997, (int) config('services.nabteb.latest_year', 2025)),
            ],
            [
                'name' => 'serial',
                'label' => 'Card Serial Number',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'pin',
                'label' => 'PIN',
                'type' => 'text',
                'required' => true,
            ],
        ];
    }

    public function fetchResult(array $params): string
    {
        $cookieJar = tempnam(sys_get_temp_dir(), 'nabteb_');

        try {
            $this->request(
                url: $this->baseUrl.'/',
                method: 'GET',
                payload: null,
                headers: $this->headers,
                cookieJar: $cookieJar,
            );

            $response =  $this->request(
                url: $this->baseUrl.'/results.asp',
                method: 'POST',
                payload: [
                    'candid' => trim((string) ($params['candid'] ?? $params['candidate_number'] ?? '')),
                    'examtype' => trim((string) ($params['examtype'] ?? $params['exam_type'] ?? '')),
                    'examyear' => trim((string) ($params['examyear'] ?? $params['exam_year'] ?? '')),
                    'serial' => trim((string) ($params['serial'] ?? $params['serial_number'] ?? '')),
                    'pin' => trim((string) ($params['pin'] ?? '')),
                    'emailcheck' => '1',
                    'flag' => 'false',
                    'email' => '',
                    'Submit' => 'Submit',
                ],
                headers: array_merge($this->headers, [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Origin: '.$this->baseUrl,
                    'Referer: '.$this->baseUrl.'/',
                ]),
                cookieJar: $cookieJar,
            );

            return $response;
        } finally {
            @unlink($cookieJar);
        }
    }

    public function parseResult(string $html): array
    {
        $html = trim($html, " \t\n\r\0\x0B'\",");
        $lower = strtolower($html);

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        $candidate = $this->extractCandidate($xpath, $html);
        $subjects = $this->extractSubjects($xpath, $html);

        if ($subjects !== []) {
            $result = [
                'board' => 'NABTEB',
                'exam_type' => $candidate['exam_type'] ?? null,
                'exam_year' => $candidate['exam_year'] ?? null,
                'subjects' => $subjects,
            ];

            return [
                'status' => 'success',
                'candidate' => $candidate,
                'result' => $result,
                'subjects' => $subjects,
                'overall' => null,
            ];
        }

        if ($this->looksLikeError($lower)) {
            return [
                'status' => 'error',
                'code' => $this->mapErrorCode($lower),
                'message' => $this->extractHtmlErrorMessage($html) ?? 'NABTEB returned an error.',
            ];
        }

        $message = $this->extractHtmlErrorMessage($html);

        return [
            'status' => 'error',
            'code' => $message ? $this->mapErrorCode($message) : 'RESULT_NOT_FOUND',
            'message' => $message ?: 'No NABTEB subject results were found. Please verify the supplied details.',
        ];
    }

    private function request(string $url, string $method, ?array $payload, array $headers, string $cookieJar): string
    {
        $timeout = max(1, (int) config('services.nabteb.timeout', 12));
        $connectTimeout = min(
            $timeout,
            max(1, (int) config('services.nabteb.connect_timeout', 5)),
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_COOKIEJAR => $cookieJar,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => $timeout,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        if (strtoupper($method) === 'POST') {
            $body = http_build_query($payload ?? []);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new RuntimeException("NABTEB result checker timed out after {$timeout} seconds. Please try again.");
            }

            throw new RuntimeException("NABTEB {$method} request failed: {$error}");
        }

        if ($status < 200 || $status >= 400) {
            $message = $this->extractHtmlErrorMessage((string) $response) ?? "NABTEB returned HTTP status {$status}";
            throw new RuntimeException($message);
        }

        return (string) $response;
    }

    private function extractCandidate(\DOMXPath $xpath, string $html): array
    {
        $candidate = [
            'name' => null,
            'candidate_name' => null,
            'exam_number' => null,
            'exam_type' => null,
            'exam_year' => null,
            'centre' => null,
            'centre_name' => null,
            'centre_number' => null,
        ];

        foreach ($xpath->query('//table//tr') ?: [] as $row) {
            $cells = $xpath->query('./th|./td', $row);
            if ($cells->length < 2) {
                continue;
            }

            $label = $this->normalizeText((string) $cells->item(0)?->textContent);
            $value = $this->normalizeText((string) $cells->item(1)?->textContent);

            $this->mapCandidateField($candidate, $label, $value);
        }

        $text = $this->normalizeText(strip_tags($html));
        $patterns = [
            'name' => '/(?:candidate\s+name|name)\s*:?\s*([A-Z][A-Z\s\.\'-]{2,})(?=\s+(?:candidate|exam|centre|center|subject|grade)\b|$)/i',
            'exam_number' => '/(?:candidate\s+number|candidate\s+no|exam\s+number|exam\s+no)\s*:?\s*([A-Z0-9\/\-]+)/i',
            'exam_year' => '/(?:exam\s+year|year|type\s+of\s+examination)\s*:?\s*[A-Z\/,\s]*(\d{4})/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if ($candidate[$key] !== null) {
                continue;
            }

            if (preg_match($pattern, $text, $matches)) {
                $candidate[$key] = $this->normalizeText((string) $matches[1]);
            }
        }

        if ($candidate['name'] !== null) {
            $candidate['candidate_name'] = $candidate['name'];
        }

        if ($candidate['centre_name'] !== null) {
            $candidate['centre'] = $candidate['centre_name'];
        }

        return $candidate;
    }

    private function extractSubjects(\DOMXPath $xpath, string $html): array
    {
        $subjects = [];

        foreach ($xpath->query('//table//tr') ?: [] as $row) {
            $cells = $xpath->query('./th|./td', $row);
            if ($cells->length < 2) {
                continue;
            }

            $values = [];
            foreach ($cells as $cell) {
                $values[] = $this->normalizeText((string) $cell->textContent);
            }

            $rowText = strtolower(implode(' ', $values));
            if (str_contains($rowText, 'subject') && str_contains($rowText, 'grade')) {
                continue;
            }

            $gradeIndex = null;
            foreach ($values as $index => $value) {
                if ($this->looksLikeGrade($value)) {
                    $gradeIndex = $index;
                    break;
                }
            }

            if ($gradeIndex === null || $gradeIndex === 0) {
                continue;
            }

            $subject = $values[$gradeIndex - 1] ?? $values[0] ?? '';
            if ($subject === '' || $this->looksLikeMetadataLabel($subject)) {
                continue;
            }

            $subjects[] = [
                'subject' => $subject,
                'grade' => strtoupper($values[$gradeIndex]),
                'remark' => $values[$gradeIndex + 1] ?? null,
                'score' => $values[$gradeIndex + 1] ?? null,
            ];
        }

        if ($subjects !== []) {
            return $this->uniqueSubjects($subjects);
        }

        $text = $this->normalizeText(strip_tags($html));
        preg_match_all('/([A-Z][A-Z&\-\',\.\/\(\) ]{2,})\s+(A1|B2|B3|C4|C5|C6|D7|E8|P7|P8|F9|PASS|FAIL|ABS|ABSENT|WITHHELD)\b(?:\s+([A-Z ]{2,}))?/i', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $subject = $this->normalizeText((string) ($match[1] ?? ''));
            if ($subject === '' || $this->looksLikeMetadataLabel($subject)) {
                continue;
            }

            $remark = isset($match[3]) ? $this->normalizeText((string) $match[3]) : null;

            $subjects[] = [
                'subject' => $subject,
                'grade' => strtoupper($this->normalizeText((string) ($match[2] ?? ''))),
                'remark' => $remark ?: null,
                'score' => $remark ?: null,
            ];
        }

        return $this->uniqueSubjects($subjects);
    }

    private function mapCandidateField(array &$candidate, string $label, string $value): void
    {
        if ($value === '') {
            return;
        }

        $key = strtolower($label);

        if ((str_contains($key, 'candidate') && str_contains($key, 'name')) || $key === 'name') {
            $candidate['name'] ??= $value;
            return;
        }

        if ((str_contains($key, 'candidate') && (str_contains($key, 'number') || str_contains($key, 'no'))) || str_contains($key, 'exam number')) {
            $candidate['exam_number'] ??= $value;
            return;
        }

        if (str_contains($key, 'exam type') || str_contains($key, 'examination type') || str_contains($key, 'type of examination')) {
            $candidate['exam_type'] ??= $value;

            if ($candidate['exam_year'] === null && preg_match('/\b(\d{4})\b/', $value, $matches)) {
                $candidate['exam_year'] = $matches[1];
            }

            return;
        }

        if (str_contains($key, 'exam year') || $key === 'year') {
            $candidate['exam_year'] ??= $value;
            return;
        }

        if (str_contains($key, 'centre') || str_contains($key, 'center') || str_contains($key, 'school')) {
            if (str_contains($key, 'number') || str_contains($key, 'no')) {
                $candidate['centre_number'] ??= $value;
                return;
            }

            $candidate['centre_name'] ??= $value;
        }
    }

    private function looksLikeGrade(string $value): bool
    {
        return (bool) preg_match('/^(A1|B2|B3|C4|C5|C6|D7|E8|P7|P8|F9|PASS|FAIL|ABS|ABSENT|WITHHELD)$/i', trim($value));
    }

    private function looksLikeMetadataLabel(string $value): bool
    {
        $lower = strtolower($value);

        foreach (['candidate', 'exam', 'year', 'centre', 'center', 'school', 'serial', 'pin', 'result', 'grade'] as $label) {
            if (str_contains($lower, $label)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeError(string $lowerHtml): bool
    {
        if (str_contains($lowerHtml, '<table') && str_contains($lowerHtml, 'subject') && str_contains($lowerHtml, 'grade')) {
            return false;
        }

        $credentialError = (str_contains($lowerHtml, 'pin') || str_contains($lowerHtml, 'serial') || str_contains($lowerHtml, 'card'))
            && (
                str_contains($lowerHtml, 'invalid')
                || str_contains($lowerHtml, 'incorrect')
                || str_contains($lowerHtml, 'wrong')
                || (bool) preg_match('/\bused\b/', $lowerHtml)
                || str_contains($lowerHtml, 'expired')
            );

        return str_contains($lowerHtml, 'length required')
            || str_contains($lowerHtml, 'http error')
            || str_contains($lowerHtml, 'invalid')
            || str_contains($lowerHtml, 'not found')
            || str_contains($lowerHtml, 'no result')
            || str_contains($lowerHtml, 'incorrect')
            || $credentialError;
    }

    private function mapErrorCode(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'pin') || str_contains($lower, 'serial') || str_contains($lower, 'card')) {
            return 'INVALID_PIN';
        }

        if (str_contains($lower, 'candidate') || str_contains($lower, 'invalid')) {
            return 'INVALID_CANDIDATE';
        }

        if (str_contains($lower, 'not found') || str_contains($lower, 'no result')) {
            return 'RESULT_NOT_FOUND';
        }

        return 'UNKNOWN_ERROR';
    }

    private function extractHtmlErrorMessage(string $html): ?string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        foreach ([
            '//*[contains(@class, "alert")]',
            '//*[contains(@class, "error")]',
            '//*[contains(@class, "danger")]',
            '//*[contains(@id, "error")]',
            '//h1',
            '//h2',
            '//h3',
            '//p',
            '//title',
        ] as $query) {
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

    private function isUsefulErrorText(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        $lower = strtolower($message);

        if (in_array($lower, ['nabteb eworld', 'online result checker', 'result checker'], true)) {
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

    private function uniqueSubjects(array $subjects): array
    {
        $unique = [];

        foreach ($subjects as $subject) {
            $key = strtolower(trim((string) ($subject['subject'] ?? '')));
            if ($key === '' || isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $subject;
        }

        return array_values($unique);
    }

    private function yearOptions(int $startYear, int $latestYear): array
    {
        $options = [];

        for ($year = $latestYear; $year >= $startYear; $year--) {
            $options[] = [
                'value' => (string) $year,
                'label' => (string) $year,
            ];
        }

        return $options;
    }
}
