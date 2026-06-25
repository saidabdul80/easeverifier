<?php

namespace App\Services\ResultVerify\ResultGates;

use App\Services\ResultVerify\ResultInterface;
use RuntimeException;

class NbaisResult implements ResultInterface
{
    private string $baseUrl = 'https://resultchecker.nbais.com.ng';

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
                'name' => 'parent_cat',
                'label' => 'State',
                'type' => 'select',
                'required' => true,
                'options' => $this->stateOptions(),
            ],
            [
                'name' => 'sub_cat',
                'label' => 'School / Centre',
                'type' => 'select',
                'required' => true,
                'depends_on' => 'parent_cat',
                'options_endpoint' => '/api/v1/results/nbais/schools',
            ],
            [
                'name' => 'year',
                'label' => 'Examination Year',
                'type' => 'select',
                'required' => true,
                'options' => $this->yearOptions(2008),
            ],
            [
                'name' => 'month-select',
                'label' => 'Examination Month',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'June/July', 'label' => 'June/July'],
                    ['value' => 'Nov/Dec', 'label' => 'Nov/Dec'],
                ],
            ],
            [
                'name' => 'exam_type',
                'label' => 'Examination Type',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'SAISSCE', 'label' => 'SAISSCE'],
                    ['value' => 'SCIENCE', 'label' => 'SCIENCE'],
                    ['value' => 'TAHFEEZ', 'label' => 'TAHFEEZ'],
                ],
            ],
            [
                'name' => 'exam_no',
                'label' => 'Examination Number',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'pin',
                'label' => 'Result Checker PIN',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'serial',
                'label' => 'Result Checker Serial Number',
                'type' => 'text',
                'required' => true,
            ],
        ];
    }

    public function fetchResult(array $params): string
    {
        $cookieJar = tempnam(sys_get_temp_dir(), 'nbais_');

        $payload = [
            'website' => '',
            'parent_cat' => trim((string) ($params['parent_cat'] ?? '')),
            'sub_cat' => trim((string) ($params['sub_cat'] ?? '')),
            'year' => trim((string) ($params['year'] ?? '')),
            'month-select' => trim((string) ($params['month-select'] ?? $params['month'] ?? '')),
            'exam_type' => trim((string) ($params['exam_type'] ?? '')),
            'exam_no' => trim((string) ($params['exam_no'] ?? $params['exam_number'] ?? '')),
        ];

        try {
            $this->request(
                url: $this->baseUrl.'/',
                method: 'GET',
                payload: null,
                headers: $this->commonHeaders,
                cookieJar: $cookieJar,
            );

            $stage1 = $this->request(
                url: $this->baseUrl.'/process-results-1.php',
                method: 'POST',
                payload: $payload,
                headers: array_merge($this->commonHeaders, [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Origin: '.$this->baseUrl,
                    'Referer: '.$this->baseUrl.'/',
                ]),
                cookieJar: $cookieJar,
            );


            if ($this->looksLikeResultPage($stage1)) {
                return $stage1;
            }

            if ($this->extractStageValidationMessage($stage1)) {
                return $stage1;
            }


            $pinForm = $this->findPinForm($this->extractForms($stage1));
            if (!$pinForm) {
                $pinForm = $this->fallbackPinForm($stage1);
            }

            if (!$pinForm) {
                $message = $this->extractHtmlErrorMessage($stage1);

                throw new RuntimeException($message ?: 'NBAIS returned an intermediate validation page but no second-stage PIN form was found.');
            }
            

            $response = $this->submitPinStage(
                pinForm: $pinForm,
                params: $params,
                cookieJar: $cookieJar,
                referer: $this->baseUrl.'/process-results-1.php',
            );
            
            return $response;
        } finally {
            @unlink($cookieJar);
        }
    }

    private function submitPinStage(array $pinForm, array $params, string $cookieJar, string $referer): string
    {
        $stage2Payload = $this->injectPinFields(
            payload: $pinForm['inputs'],
            pin: trim((string) ($params['pin'] ?? $params['PIN'] ?? $params['txtPIN'] ?? '')),
            serial: trim((string) ($params['serial'] ?? $params['Serial'] ?? $params['serial_no'] ?? $params['txtCardSerialNo'] ?? '')),
        );

        return $this->request(
            url: $this->resolveUrl((string) $pinForm['action']),
            method: (string) ($pinForm['method'] ?? 'POST'),
            payload: $stage2Payload,
            headers: array_merge($this->commonHeaders, [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: '.$this->baseUrl,
                'Referer: '.$referer,
            ]),
            cookieJar: $cookieJar,
        );
    }

    private function request(string $url, string $method, ?array $payload, array $headers, string $cookieJar): string
    {
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
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload ?? []));
        } else {
            if ($payload) {
                curl_setopt($ch, CURLOPT_URL, $url.(str_contains($url, '?') ? '&' : '?').http_build_query($payload));
            }
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("NBAIS {$method} request failed: {$error}");
        }

        if ($status < 200 || $status >= 400) {
            $message = $this->extractHtmlErrorMessage((string) $response) ?? "NBAIS returned HTTP status {$status}";
            throw new RuntimeException($message);
        }

        return (string) $response;
    }

    public function parseResult(string $html): array
    {
        $html = trim($html, " \t\n\r\0\x0B'\",");
        $lower = strtolower($html);

        if ($pinForm = $this->findPinForm($this->extractForms($html))) {
            $message = $this->extractHtmlErrorMessage($html);
            if ($message && $this->mapErrorCode($message) !== 'UNKNOWN_ERROR') {
                return [
                    'status' => 'error',
                    'code' => $this->mapErrorCode($message),
                    'message' => $message,
                    'meta' => [
                        'required_fields' => array_keys($pinForm['inputs']),
                    ],
                ];
            }

            return [
                'status' => 'error',
                'code' => 'NBAIS_SECOND_STAGE_NOT_COMPLETED',
                'message' => 'NBAIS returned the PIN validation page instead of a final result. Please verify the supplied PIN and serial number.',
                'meta' => [
                    'detected_fields' => array_keys($pinForm['inputs']),
                ],
            ];
        }

        if ($this->looksLikeError($lower)) {
            return [
                'status' => 'error',
                'code' => $this->errorCode($lower),
                'message' => $this->errorMessage($html),
            ];
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        $candidate = $this->extractCandidate($xpath);
        $subjects = $this->extractSubjects($xpath);
        $notes = $this->extractNotes($xpath);

        if ($subjects === []) {
            $message = $this->extractHtmlErrorMessage($html);

            return [
                'status' => 'error',
                'code' => $this->mapErrorCode($message ?? $html),
                'message' => $message ?? 'No NBAIS subject results were found. Please verify the supplied details.',
            ];
        }

        $examLabel = $this->normalizeText((string) ($xpath->query('//span[contains(@class, "border")]')?->item(0)?->textContent ?? ''));
        $passport = $xpath->query('//img[contains(@class, "candidate-image")]/@src')?->item(0)?->nodeValue;
        $qrCode = $xpath->query('//div[contains(@class, "qr-code-scanner")]//img/@src')?->item(0)?->nodeValue;

        if ($passport) {
            $candidate['passport'] = $this->absoluteUrl($passport);
        }

        $result = [
            'board' => 'NBAIS',
            'certificate' => 'Senior Arabic And Islamic Secondary School Certificate Examination',
            'exam_label' => $examLabel ?: ($candidate['exam_year'] ?? null),
            'subjects' => $subjects,
            'notes' => $notes,
            'qr_code' => $qrCode ? $this->absoluteUrl($qrCode) : null,
        ];

        return [
            'status' => 'success',
            'candidate' => $candidate,
            'result' => $result,
            'subjects' => $subjects,
            'overall' => null,
        ];
    }

    public function fetchSchools(string|int $stateId): array
    {
        $query = http_build_query(['parent_cat' => $stateId]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl.'/loadsubcat.php?'.$query,
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Referer: '.$this->baseUrl.'/',
                'Accept: text/html,*/*;q=0.8',
            ],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("NBAIS school lookup failed: {$error}");
        }

        if ($status < 200 || $status >= 400) {
            throw new RuntimeException("NBAIS school lookup returned HTTP status {$status}");
        }

        return $this->parseSchoolOptions((string) $response);
    }

    public function parseSchoolOptions(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8"><select>'.$html.'</select>');
        $xpath = new \DOMXPath($dom);

        $options = [];
        foreach ($xpath->query('//option') ?: [] as $option) {
            $value = trim((string) $option->getAttribute('value'));
            $label = $this->normalizeText((string) $option->textContent);

            if ($value === '' || $label === '') {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        $fallbackOptions = $this->parseSchoolOptionsFromFragment($html);

        if (count($fallbackOptions) > count($options)) {
            return $fallbackOptions;
        }

        return $options;
    }

    private function parseSchoolOptionsFromFragment(string $html): array
    {
        if (!preg_match_all('/<option\b([^>]*)>(.*?)<\/option>/is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $options = [];
        foreach ($matches as $match) {
            $attributes = $match[1] ?? '';
            $label = $this->normalizeText(strip_tags($match[2] ?? ''));

            if (!preg_match('/\bvalue\s*=\s*(["\'])(.*?)\1/is', $attributes, $valueMatch)) {
                continue;
            }

            $value = trim(html_entity_decode($valueMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($value === '' || $label === '') {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }

    private function extractForms(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        $forms = [];
        foreach ($xpath->query('//form') ?: [] as $form) {
            $inputs = [];

            foreach ($xpath->query('.//input', $form) ?: [] as $input) {
                $name = trim((string) $input->getAttribute('name'));
                if ($name === '') {
                    continue;
                }

                $inputs[$name] = (string) $input->getAttribute('value');
            }

            foreach ($xpath->query('.//select', $form) ?: [] as $select) {
                $name = trim((string) $select->getAttribute('name'));
                if ($name === '') {
                    continue;
                }

                $selected = $xpath->query('.//option[@selected]', $select)?->item(0);
                $inputs[$name] = $selected?->getAttribute('value') ?? '';
            }

            $forms[] = [
                'action' => (string) $form->getAttribute('action'),
                'method' => strtoupper((string) ($form->getAttribute('method') ?: 'GET')),
                'inputs' => $inputs,
            ];
        }

        return $forms;
    }

    private function findPinForm(array $forms): ?array
    {
        foreach ($forms as $form) {
            $fields = strtolower(implode(' ', array_keys($form['inputs'] ?? [])));

            if (
                str_contains($fields, 'pin')
                || str_contains($fields, 'serial')
                || str_contains($fields, 'card')
                || str_contains($fields, 'scratch')
            ) {
                return $form;
            }
        }

        return null;
    }

    private function fallbackPinForm(string $html): ?array
    {
        $action = $this->extractStageTwoAction($html);

        if (!$action || $this->isStageOneAction($action)) {
            return null;
        }

        return [
            'action' => $action,
            'method' => 'POST',
            'inputs' => $this->extractHiddenInputs($html),
        ];
    }

    private function isStageOneAction(string $action): bool
    {
        $path = strtolower(parse_url($this->resolveUrl($action), PHP_URL_PATH) ?: '');

        return str_ends_with($path, '/process-results-1.php');
    }

    private function extractStageTwoAction(string $html): ?string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        $candidates = [
            '//form[contains(translate(@action, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "process-results")]',
            '//a[contains(translate(@href, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "process-results")]',
            '//*[contains(translate(@data-url, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "process-results")]',
        ];

        foreach ($candidates as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $value = $node instanceof \DOMElement
                    ? ((string) ($node->getAttribute('action') ?: $node->getAttribute('href') ?: $node->getAttribute('data-url')))
                    : '';

                if (trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        if (preg_match('/["\']([^"\']*process-results-[^"\']+\.php[^"\']*)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/\b(?:action|url|href)\s*[:=]\s*["\']([^"\']+\.php[^"\']*)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    private function extractHiddenInputs(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        $inputs = [];
        foreach ($xpath->query('//input') ?: [] as $input) {
            $name = trim((string) $input->getAttribute('name'));
            if ($name === '') {
                continue;
            }

            $inputs[$name] = (string) $input->getAttribute('value');
        }

        return $inputs;
    }

    private function injectPinFields(array $payload, string $pin, string $serial): array
    {
        $pinFields = ['pin', 'PIN', 'card_pin', 'cardpin', 'txtPIN', 'pin_no', 'pin_number'];
        $serialFields = ['serial', 'Serial', 'serial_no', 'serial_number', 'card_serial', 'card_serial_no', 'txtSerial', 'txtCardSerialNo'];

        $hasPin = false;
        foreach ($pinFields as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $pin;
                $hasPin = true;
            }
        }

        $hasSerial = false;
        foreach ($serialFields as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $serial;
                $hasSerial = true;
            }
        }

        if (!$hasPin) {
            $payload['pin'] = $pin;
        }

        if (!$hasSerial && $serial !== '') {
            $payload['serial'] = $serial;
        }

        if (!array_key_exists('Submit', $payload) && !array_key_exists('submit', $payload)) {
            $payload['Submit'] = 'Submit';
        }

        return $payload;
    }

    private function resolveUrl(string $action): string
    {
        $action = trim($action);

        if ($action === '') {
            return $this->baseUrl.'/';
        }

        if (str_starts_with($action, 'http://') || str_starts_with($action, 'https://')) {
            return $action;
        }

        if (str_starts_with($action, '/')) {
            return rtrim($this->baseUrl, '/').$action;
        }

        return rtrim($this->baseUrl, '/').'/'.ltrim($action, '/');
    }

    private function extractCandidate(\DOMXPath $xpath): array
    {
        $candidate = [
            'name' => null,
            'candidate_name' => null,
            'exam_number' => null,
            'exam_type' => null,
            'exam_year' => null,
            'centre_number' => null,
            'centre_name' => null,
            'centre' => null,
        ];

        foreach ($xpath->query('//input[contains(@class, "form-control-plaintext")]') ?: [] as $input) {
            $label = $xpath->query('./following-sibling::*[contains(@class, "placeholder-label")][1]', $input)?->item(0);

            if (!$label) {
                continue;
            }

            $key = strtolower($this->normalizeText((string) $label->textContent));
            $value = $this->normalizeText((string) $input->getAttribute('value'));

            $this->mapCandidateField($candidate, $key, $value);
        }

        if ($candidate['name'] !== null) {
            $candidate['candidate_name'] = $candidate['name'];
        }

        if ($candidate['centre_name'] !== null) {
            $candidate['centre'] = $candidate['centre_name'];
        }

        return $candidate;
    }

    private function mapCandidateField(array &$candidate, string $key, string $value): void
    {
        if ($value === '') {
            return;
        }

        if ($key === 'name' || str_contains($key, 'candidate')) {
            $candidate['name'] = $value;
            return;
        }

        if (str_contains($key, 'exam number')) {
            $candidate['exam_number'] = $value;
            return;
        }

        if (str_contains($key, 'exam type')) {
            $candidate['exam_type'] = $value;
            return;
        }

        if (str_contains($key, 'exam year') || str_contains($key, 'month/year')) {
            $candidate['exam_year'] = $value;
            return;
        }

        if (str_contains($key, 'center number') || str_contains($key, 'centre number')) {
            $candidate['centre_number'] = $value;
            return;
        }

        if (str_contains($key, 'center name') || str_contains($key, 'centre name')) {
            $candidate['centre_name'] = $value;
        }
    }

    private function extractSubjects(\DOMXPath $xpath): array
    {
        $subjects = [];
        $rows = $xpath->query('//table[contains(@class, "table")]//tbody/tr');

        foreach ($rows ?: [] as $row) {
            $cells = $xpath->query('./th|./td', $row);
            if ($cells->length < 3) {
                continue;
            }

            $subject = $this->normalizeText((string) $cells->item(0)?->textContent);
            $grade = $this->normalizeText((string) $cells->item(1)?->textContent);
            $remark = $this->normalizeText((string) $cells->item(2)?->textContent);

            if ($subject === '' || $grade === '') {
                continue;
            }

            $subjects[] = [
                'subject' => $subject,
                'grade' => strtoupper($grade),
                'remark' => $remark ?: null,
                'score' => $remark ?: null,
            ];
        }

        return $subjects;
    }

    private function extractNotes(\DOMXPath $xpath): array
    {
        $notes = [];

        foreach ($xpath->query('//div[contains(@class, "notes-container")]//li') ?: [] as $note) {
            $value = $this->normalizeText((string) $note->textContent);
            if ($value !== '') {
                $notes[] = $value;
            }
        }

        return $notes;
    }

    private function looksLikeError(string $lowerHtml): bool
    {
        if (str_contains($lowerHtml, 'form-control-plaintext') && str_contains($lowerHtml, 'subject')) {
            return false;
        }

        return str_contains($lowerHtml, 'no result')
            || str_contains($lowerHtml, 'result not found')
            || str_contains($lowerHtml, 'invalid')
            || str_contains($lowerHtml, 'not found')
            || str_contains($lowerHtml, 'try again')
            || str_contains($lowerHtml, 'incorrect')
            || str_contains($lowerHtml, 'pin')
            || str_contains($lowerHtml, 'serial')
            || str_contains($lowerHtml, 'process-results-1.php') && !str_contains($lowerHtml, 'candidate-image');
    }

    private function errorCode(string $lowerHtml): string
    {
        return $this->mapErrorCode($lowerHtml);
    }

    private function errorMessage(string $html): string
    {
        $text = $this->extractHtmlErrorMessage($html) ?? $this->normalizeText(strip_tags($html));

        if ($text === '') {
            return 'NBAIS returned an empty response.';
        }

        return mb_substr($text, 0, 240);
    }

    private function extractHtmlErrorMessage(string $html): ?string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        $queries = [
            '//*[contains(@class, "main_question")]',
            '//*[contains(@style, "color: red")]',
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

    private function extractStageValidationMessage(string $html): ?string
    {
        $message = $this->extractHtmlErrorMessage($html);

        if (!$message) {
            return null;
        }

        $lower = strtolower($message);

        return str_contains($lower, 'invalid candidate')
            || str_contains($lower, 'double-check the exam number')
            || str_contains($lower, 'try again')
            ? $message
            : null;
    }

    private function isUsefulErrorText(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        $lower = strtolower($message);

        if (in_array($lower, ['nbais result checker', 'nbais - result checker', 'result checker'], true)) {
            return false;
        }

        if (str_contains($lower, 'some results are currently blocked')) {
            return false;
        }

        return true;
    }

    private function mapErrorCode(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'pin') || str_contains($lower, 'serial') || str_contains($lower, 'card')) {
            return 'INVALID_PIN';
        }

        if (str_contains($lower, 'invalid')) {
            return 'INVALID_CANDIDATE';
        }

        if (str_contains($lower, 'not found') || str_contains($lower, 'no result')) {
            return 'RESULT_NOT_FOUND';
        }

        return 'UNKNOWN_ERROR';
    }

    private function looksLikeResultPage(string $html): bool
    {
        $lower = strtolower($html);

        return str_contains($lower, 'form-control-plaintext')
            && str_contains($lower, 'candidate-image')
            && str_contains($lower, '<table')
            && str_contains($lower, 'subject')
            && str_contains($lower, 'grade');
    }

    private function normalizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim($this->baseUrl, '/').'/'.ltrim($url, '/');
    }

    private function stateOptions(): array
    {
        $states = [
            ['id' => '1', 'name' => 'Kwara'],
            ['id' => '2', 'name' => 'Benue'],
            ['id' => '3', 'name' => 'FCT'],
            ['id' => '4', 'name' => 'Osun'],
            ['id' => '5', 'name' => 'Oyo'],
            ['id' => '6', 'name' => 'Nassarawa'],
            ['id' => '7', 'name' => 'Kogi'],
            ['id' => '8', 'name' => 'Niger'],
            ['id' => '9', 'name' => 'Katsina'],
            ['id' => '10', 'name' => 'Jigawa'],
            ['id' => '11', 'name' => 'Kaduna'],
            ['id' => '12', 'name' => 'Kano'],
            ['id' => '13', 'name' => 'Adamawa'],
            ['id' => '14', 'name' => 'Plateau'],
            ['id' => '15', 'name' => 'Zamfara'],
            ['id' => '16', 'name' => 'Kebbi'],
            ['id' => '17', 'name' => 'Sokoto'],
            ['id' => '18', 'name' => 'Taraba'],
            ['id' => '19', 'name' => 'Yobe'],
            ['id' => '20', 'name' => 'Gombe'],
            ['id' => '21', 'name' => 'Bauchi'],
            ['id' => '22', 'name' => 'Borno'],
            ['id' => '23', 'name' => 'Enugu'],
            ['id' => '24', 'name' => 'Ogun'],
            ['id' => '25', 'name' => 'Lagos'],
            ['id' => '26', 'name' => 'Ondo'],
            ['id' => '27', 'name' => 'Ekiti'],
            ['id' => '28', 'name' => 'Abia'],
            ['id' => '29', 'name' => 'Akwa Ibom'],
            ['id' => '30', 'name' => 'Anambra'],
            ['id' => '31', 'name' => 'Bayelsa'],
            ['id' => '32', 'name' => 'Cross River'],
            ['id' => '33', 'name' => 'Delta'],
            ['id' => '34', 'name' => 'Ebonyi'],
            ['id' => '35', 'name' => 'Edo'],
            ['id' => '36', 'name' => 'Imo'],
            ['id' => '37', 'name' => 'Rivers'],
        ];

        return $states;
    }

    private function yearOptions(int $startYear): array
    {
        $options = [];
        $currentYear = (int) date('Y');

        for ($year = $currentYear; $year >= $startYear; $year--) {
            $options[] = [
                'value' => (string) $year,
                'label' => (string) $year,
            ];
        }

        return $options;
    }
}
