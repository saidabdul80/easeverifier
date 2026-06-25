<?php

namespace App\Services\ResultVerify\ResultGates;

use App\Services\ResultVerify\ResultInterface;
use RuntimeException;

class WAECResult implements ResultInterface
{
    private string $baseUrl = 'https://www.waecdirect.org';

    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private array $commonHeaders = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
    ];

    public function formFields(): array
    {
        return [
            [
                'name' => 'txtExamNumber',
                'label' => 'Examination Number',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'ExamYear',
                'label' => 'Examination Year',
                'type' => 'select',
                'required' => true,
                'options' => $this->yearOptions(2000),
            ],
            [
                'name' => 'ExamType',
                'label' => 'Examination Type',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'MAY/JUN', 'label' => 'MAY/JUN'],
                    ['value' => 'NOV/DEC', 'label' => 'NOV/DEC'],
                ],
            ],
            [
                'name' => 'txtPIN',
                'label' => 'PIN (12 digits)',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'txtCardSerialNo',
                'label' => 'Card Serial Number',
                'type' => 'text',
                'required' => true,
            ],
        ];
    }

    public function fetchResult(array $params): string
    {
        $cookieJar = tempnam(sys_get_temp_dir(), 'waec_');

        try {
            $this->initSession($cookieJar);

            $query = http_build_query([
                'ExamNumber' => trim((string) ($params['txtExamNumber'] ?? $params['ExamNumber'] ?? '')),
                'ExamYear' => trim((string) ($params['ExamYear'] ?? '')),
                'serial' => trim((string) ($params['txtCardSerialNo'] ?? $params['serial'] ?? '')),
                'pin' => trim((string) ($params['txtPIN'] ?? $params['pin'] ?? '')),
                'ExamType' => trim((string) ($params['ExamType'] ?? '')),
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . '/DisplayResult.aspx?' . $query,
                CURLOPT_HTTPGET => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER => false,
                CURLOPT_COOKIEJAR => $cookieJar,
                CURLOPT_COOKIEFILE => $cookieJar,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_HTTPHEADER => array_merge($this->commonHeaders, [
                    'Referer: ' . $this->baseUrl . '/',
                ]),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT => 90,
                CURLOPT_ENCODING => '',
            ]);

            $html = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error) {
                throw new RuntimeException("cURL error: {$error}");
            }

            if ($status < 200 || $status >= 400) {
                throw new RuntimeException("HTTP error {$status} from WAEC");
            }
            \Log::debug($html);
            return (string) $html;
        } finally {
            @unlink($cookieJar);
        }
    }

    public function parseResult(string $html): array
    {
        $html = trim($html, " \t\n\r\0\x0B'\",");
        $lower = strtolower($html);

        if ($waecError = $this->parseWaecErrorPage($html, $lower)) {
            return $waecError;
        }

        $errorMap = [
            'invalid card details' => ['INVALID_PIN', 'Invalid card details.'],
            'invalid scratch card' => ['INVALID_PIN', 'Invalid scratch card details.'],
            'incorrect pin' => ['INVALID_PIN', 'Incorrect PIN or serial number.'],
            'candidate number is invalid' => ['INVALID_CANDIDATE', 'Invalid candidate details.'],
            'invalid candidate' => ['INVALID_CANDIDATE', 'Invalid candidate details.'],
            'result is not available' => ['RESULT_NOT_FOUND', 'Result not found for the details provided.'],
            'result not available for this candidate' => ['RESULT_NOT_FOUND', 'Result not available for this candidate in the specified year and examination diet.'],
            'result not found' => ['RESULT_NOT_FOUND', 'Result not found for the details provided.'],
            'no result' => ['RESULT_NOT_FOUND', 'No result found.'],
            'error occured' => ['UNKNOWN_ERROR', 'WAEC returned an unexpected error.'],
            'error occurred' => ['UNKNOWN_ERROR', 'WAEC returned an unexpected error.'],
        ];

        foreach ($errorMap as $needle => [$code, $message]) {
            if (str_contains($lower, $needle)) {
                return [
                    'status' => 'error',
                    'code' => $code,
                    'message' => $message,
                ];
            }
        }

        if ($this->looksLikeWaecLandingPage($lower)) {
            return [
                'status' => 'error',
                'code' => 'UNEXPECTED_RESPONSE',
                'message' => 'WAEC returned the checker form instead of a result page. Please verify the examination details and card information.',
            ];
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        $candidate = [
            'name' => null,
            'exam_number' => null,
            'exam_year' => null,
            'exam_type' => null,
            'centre' => null,
            'candidate_name' => null,
        ];

        $subjects = [];
        $rows = $xpath->query('//table//tr');

        foreach ($rows ?: [] as $row) {
            $cells = $xpath->query('td|th', $row);
            if ($cells->length < 2) {
                continue;
            }

            $values = [];
            foreach ($cells as $cell) {
                $values[] = trim(preg_replace('/\s+/', ' ', (string) $cell->textContent));
            }

            $first = strtolower((string) ($values[0] ?? ''));
            $second = trim((string) ($values[1] ?? ''));

            if ($this->looksLikeWaecGrade($second) && !$this->looksLikeMetadataLabel($first)) {
                $subjects[] = [
                    'subject' => $values[0],
                    'grade' => $second,
                    'score' => $values[2] ?? null,
                ];
                continue;
            }

            $this->mapCandidateField($candidate, $first, $second);
        }

        if (!$subjects) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
            preg_match_all('/([A-Z][A-Z&\-\',\.\/\(\) ]{2,})\s+(A1|B2|B3|C4|C5|C6|D7|E8|F9)/u', $text, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $subject = trim((string) ($match[1] ?? ''));
                if ($subject === '' || $this->looksLikeMetadataLabel(strtolower($subject))) {
                    continue;
                }

                $subjects[] = [
                    'subject' => $subject,
                    'grade' => trim((string) ($match[2] ?? '')),
                    'score' => null,
                ];
            }
        }

        $subjects = $this->uniqueSubjects($subjects);

        if (empty($subjects)) {
            $message = $this->extractGenericHtmlErrorMessage($html);

            return [
                'status' => 'error',
                'code' => $message ? $this->mapWaecErrorCode($message) : 'RESULT_NOT_FOUND',
                'message' => $message ?: 'No subject results were found. Please verify your details.',
            ];
        }

        $candidate['candidate_name'] = $candidate['name'];

        return [
            'status' => 'success',
            'candidate' => $candidate,
            'subjects' => array_values($subjects),
            'overall' => null,
        ];
    }

    private function initSession(string $cookieJar): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/',
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $cookieJar,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $this->commonHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => '',
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("Session init error: {$error}");
        }

        if ($status < 200 || $status >= 400) {
            throw new RuntimeException("Session init HTTP error {$status} from WAEC");
        }

        return (string) $response;
    }

    private function extractHiddenFields(string $html, array $names): array
    {
        $values = [];
        foreach ($names as $name) {
            $values[$name] = $this->extractHiddenValue($html, $name);
        }

        return $values;
    }

    private function extractHiddenValue(string $html, string $name): string
    {
        $pattern = '/<input[^>]+name=["\']' . preg_quote($name, '/') . '["\'][^>]*value=["\']([^"\']*)["\']/i';
        if (preg_match($pattern, $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        $patternById = '/<input[^>]+id=["\']' . preg_quote($name, '/') . '["\'][^>]*value=["\']([^"\']*)["\']/i';
        if (preg_match($patternById, $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        return '';
    }

    private function mapCandidateField(array &$candidate, string $key, string $value): void
    {
        if ($value === '') {
            return;
        }

        if (str_contains($key, 'candidate name') || str_contains($key, 'name')) {
            $candidate['name'] ??= $value;
            return;
        }

        if (str_contains($key, 'exam number') || str_contains($key, 'examination number') || str_contains($key, 'candidate number') || str_contains($key, 'index number')) {
            $candidate['exam_number'] ??= $value;
            return;
        }

        if (str_contains($key, 'exam year') || str_contains($key, 'year')) {
            $candidate['exam_year'] ??= $value;
            return;
        }

        if (str_contains($key, 'exam type') || str_contains($key, 'type of exam') || str_contains($key, 'type of examination')) {
            $candidate['exam_type'] ??= $value;
            return;
        }

        if (str_contains($key, 'centre') || str_contains($key, 'center') || str_contains($key, 'school')) {
            $candidate['centre'] ??= $value;
        }
    }

    private function looksLikeWaecGrade(?string $value): bool
    {
        return (bool) preg_match('/^(A1|B2|B3|C4|C5|C6|D7|E8|F9)$/i', trim((string) $value));
    }

    private function looksLikeMetadataLabel(string $value): bool
    {
        $labels = [
            'candidate',
            'name',
            'exam number',
            'candidate number',
            'examination number',
            'year',
            'exam year',
            'exam type',
            'type of examination',
            'centre',
            'center',
            'school',
            'card serial',
            'pin',
        ];

        foreach ($labels as $label) {
            if (str_contains($value, $label)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeWaecLandingPage(string $lowerHtml): bool
    {
        return str_contains($lowerHtml, 'window.open(\'displayresult.aspx\'')
            || str_contains($lowerHtml, 'window.open("displayresult.aspx"')
            || (
                str_contains($lowerHtml, 'txtexamnumber')
                && str_contains($lowerHtml, 'txtcardserialno')
                && str_contains($lowerHtml, 'txtpin')
                && str_contains($lowerHtml, 'drpexamyear')
                && str_contains($lowerHtml, 'drpexamtype')
            );
    }

    private function parseWaecErrorPage(string $html, string $lowerHtml): ?array
    {
        if (
            !str_contains($lowerHtml, 'resulterror.aspx')
            && !str_contains($lowerHtml, '<title> error page')
            && !str_contains($lowerHtml, 'id="lblerrortitle"')
            && !str_contains($lowerHtml, 'id="lblerrormsg"')
        ) {
            return null;
        }

        $message = $this->extractWaecErrorMessage($html);

        if ($message === null) {
            return [
                'status' => 'error',
                'code' => 'UNKNOWN_ERROR',
                'message' => 'WAEC returned an error page.',
            ];
        }

        return [
            'status' => 'error',
            'code' => $this->mapWaecErrorCode($message),
            'message' => $message,
        ];
    }

    private function extractWaecErrorMessage(string $html): ?string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        foreach ([
            '//*[@id="lblErrorMsg"]',
            '//*[@id="lblErrorTitle"]',
            '//title',
        ] as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = $this->normalizeText((string) ($node?->textContent ?? ''));

            if ($value !== '' && strtolower($value) !== 'error page' && strtolower($value) !== 'error') {
                return $value;
            }
        }

        if (preg_match('/[?&]errMsg=([^&"\']+)/i', $html, $matches)) {
            $decoded = urldecode((string) $matches[1]);
            $decoded = $this->normalizeText($decoded);

            if ($decoded !== '') {
                return $decoded;
            }
        }

        if (preg_match('/[?&]errTitle=([^&"\']+)/i', $html, $matches)) {
            $decoded = urldecode((string) $matches[1]);
            $decoded = $this->normalizeText($decoded);

            if ($decoded !== '') {
                return $decoded;
            }
        }

        return null;
    }

    private function mapWaecErrorCode(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (
            str_contains($lowerMessage, 'invalid card')
            || str_contains($lowerMessage, 'invalid scratch card')
            || str_contains($lowerMessage, 'incorrect pin')
            || str_contains($lowerMessage, 'serial number')
            || str_contains($lowerMessage, 'pin')
        ) {
            return 'INVALID_PIN';
        }

        if (
            str_contains($lowerMessage, 'candidate number is invalid')
            || str_contains($lowerMessage, 'invalid candidate')
            || str_contains($lowerMessage, 'invalid candidate details')
            || str_contains($lowerMessage, 'examination number')
        ) {
            return 'INVALID_CANDIDATE';
        }

        if (
            str_contains($lowerMessage, 'result not available')
            || str_contains($lowerMessage, 'result not found')
            || str_contains($lowerMessage, 'no result')
            || str_contains($lowerMessage, 'specified year')
        ) {
            return 'RESULT_NOT_FOUND';
        }

        return 'UNKNOWN_ERROR';
    }

    private function extractGenericHtmlErrorMessage(string $html): ?string
    {
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

    private function isUsefulErrorText(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        $lower = strtolower($message);

        if (in_array($lower, ['waec direct online', 'waec result checker', 'result checker'], true)) {
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

        return $unique;
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
