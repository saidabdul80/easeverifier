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
                'name' => 'year',
                'label' => 'Examination Year',
                'type' => 'select',
                'required' => true,
                'options' => $this->yearOptions(2008),
            ],
            [
                'name' => 'month',
                'label' => 'Examination Month',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'June/July', 'label' => 'June/July'],
                    ['value' => 'Nov/Dec', 'label' => 'Nov/Dec'],
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
        ];
    }

    public function fetchResult(array $params): string
    {
        $cookieJar = tempnam(sys_get_temp_dir(), 'nbais_');

        try {
            $checkHtml = $this->request(
                url: $this->baseUrl.'/check',
                method: 'GET',
                payload: null,
                headers: $this->commonHeaders,
                cookieJar: $cookieJar,
            );

            $checkForm = $this->findFormByAction($this->extractForms($checkHtml), '/check') ?? [
                'action' => '/check',
                'method' => 'POST',
                'inputs' => $this->extractHiddenInputs($checkHtml),
            ];

            $token = trim((string) ($checkForm['inputs']['_token'] ?? '')) ?: $this->extractCsrfToken($checkHtml);
            if ($token === null) {
                throw new RuntimeException('NBAIS check form did not include a CSRF token.');
            }

            $stage1 = $this->request(
                url: $this->resolveUrl((string) ($checkForm['action'] ?: '/check')),
                method: 'POST',
                payload: [
                    'exam_no' => trim((string) ($params['exam_no'] ?? $params['exam_number'] ?? '')),
                    'year' => trim((string) ($params['year'] ?? '')),
                    'month' => trim((string) ($params['month'] ?? $params['month-select'] ?? '')),
                    '_token' => $token,
                    'website' => '',
                ],
                headers: array_merge($this->commonHeaders, [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Origin: '.$this->baseUrl,
                    'Referer: '.$this->baseUrl.'/check',
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
                referer: $this->baseUrl.'/check',
            );

            return $response;
        } finally {
            @unlink($cookieJar);
        }
    }

    private function submitPinStage(array $pinForm, array $params, string $cookieJar, string $referer): string
    {
        $token = (string) ($pinForm['inputs']['_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('NBAIS PIN form did not include a CSRF token.');
        }

        return $this->request(
            url: $this->resolveUrl((string) ($pinForm['action'] ?: '/pin')),
            method: 'POST',
            payload: [
                'pin' => trim((string) ($params['pin'] ?? $params['PIN'] ?? $params['txtPIN'] ?? '')),
                '_token' => $token,
                'website' => '',
            ],
            headers: array_merge($this->commonHeaders, [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: '.$this->baseUrl,
                'Referer: '.$referer,
            ]),
            cookieJar: $cookieJar,
        );
    }

    protected function request(string $url, string $method, ?array $payload, array $headers, string $cookieJar): string
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

        if ($portalError = $this->extractPortalError($html)) {
            return $portalError;
        }

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
                'message' => 'NBAIS returned the PIN validation page instead of a final result. Please verify the supplied PIN.',
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
        $passport = $this->extractImageByMeaning($xpath, ['candidate-image', 'passport', 'photo']);
        $qrCode = $this->extractImageByMeaning($xpath, ['qr-code-scanner', 'qr-code', 'qr']);

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

    private function findFormByAction(array $forms, string $actionNeedle): ?array
    {
        foreach ($forms as $form) {
            $action = strtolower((string) ($form['action'] ?? ''));

            if (str_contains($action, strtolower($actionNeedle))) {
                return $form;
            }
        }

        return null;
    }

    private function findPinForm(array $forms): ?array
    {
        foreach ($forms as $form) {
            $fields = strtolower(implode(' ', array_keys($form['inputs'] ?? [])));
            $action = strtolower((string) ($form['action'] ?? ''));

            if (
                str_contains($fields, 'pin')
                || str_contains($action, '/pin')
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

        return str_ends_with($path, '/check');
    }

    private function extractStageTwoAction(string $html): ?string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        $candidates = [
            '//form[contains(translate(@action, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "/pin")]',
            '//a[contains(translate(@href, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "/pin")]',
            '//*[contains(translate(@data-url, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "/pin")]',
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

        if (preg_match('/["\']([^"\']*\/pin(?:\?[^"\']*)?)["\']/i', $html, $matches)) {
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

    private function extractCsrfToken(string $html): ?string
    {
        $inputs = $this->extractHiddenInputs($html);
        $token = trim((string) ($inputs['_token'] ?? ''));

        if ($token !== '') {
            return $token;
        }

        if (preg_match('/<meta[^>]+name\s*=\s*["\']csrf-token["\'][^>]+content\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    private function extractImageByMeaning(\DOMXPath $xpath, array $needles): ?string
    {
        foreach ($xpath->query('//img[@src]') ?: [] as $image) {
            if (!$image instanceof \DOMElement) {
                continue;
            }

            $haystack = strtolower(implode(' ', [
                $image->getAttribute('class'),
                $image->getAttribute('alt'),
                $image->getAttribute('id'),
                $image->getAttribute('src'),
                $image->parentNode instanceof \DOMElement ? $image->parentNode->getAttribute('class') : '',
            ]));

            foreach ($needles as $needle) {
                if (str_contains($haystack, strtolower($needle))) {
                    return $image->getAttribute('src');
                }
            }
        }

        return null;
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
            'gender' => null,
            'date_of_birth' => null,
            'dob' => null,
        ];

        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " sheet-label ")]') ?: [] as $labelNode) {
            $valueNode = $xpath->query('./following-sibling::*[contains(concat(" ", normalize-space(@class), " "), " sheet-value ")][1]', $labelNode)?->item(0);

            if (!$valueNode && $labelNode->parentNode) {
                $valueNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " sheet-value ")][1]', $labelNode->parentNode)?->item(0);
            }

            if (!$valueNode && $labelNode->parentNode) {
                $valueNode = $xpath->query('./following-sibling::*//*[contains(concat(" ", normalize-space(@class), " "), " sheet-value ")][1]', $labelNode->parentNode)?->item(0);
            }

            if (!$valueNode) {
                continue;
            }

            $key = strtolower($this->normalizeText((string) $labelNode->textContent));
            $value = $this->normalizeText((string) $valueNode->textContent);

            $this->mapCandidateField($candidate, $key, $value);
        }

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
            return;
        }

        if (str_contains($key, 'gender') || str_contains($key, 'sex')) {
            $candidate['gender'] = $value;
            return;
        }

        if (str_contains($key, 'date of birth') || str_contains($key, 'birth') || $key === 'dob') {
            $candidate['date_of_birth'] = $value;
            $candidate['dob'] = $value;
        }
    }

    private function extractSubjects(\DOMXPath $xpath): array
    {
        $subjects = [];
        $rows = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " results ")]//tr');

        if (!$rows || $rows->length === 0) {
            $rows = $xpath->query('//table[contains(@class, "table")]//tbody/tr');
        }

        foreach ($rows ?: [] as $row) {
            $cells = $xpath->query('./th|./td', $row);
            if ($cells->length < 2) {
                continue;
            }

            $values = [];
            foreach ($cells as $cell) {
                $values[] = $this->normalizeText((string) $cell->textContent);
            }

            if ($this->looksLikeSubjectHeader($values)) {
                continue;
            }

            $subjectIndex = $this->subjectColumnIndex($values);
            $subject = $values[$subjectIndex] ?? '';
            $grade = $values[$subjectIndex + 1] ?? '';
            $remark = $values[$subjectIndex + 2] ?? '';

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

    private function looksLikeSubjectHeader(array $values): bool
    {
        $joined = strtolower(implode(' ', $values));

        return str_contains($joined, 'subject') && str_contains($joined, 'grade');
    }

    private function subjectColumnIndex(array $values): int
    {
        if (count($values) >= 3 && preg_match('/^(?:\d+|s\/?n|sn|no\.?)$/i', $values[0] ?? '')) {
            return 1;
        }

        return 0;
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

        if (str_contains($lowerHtml, 'sheet-label') && str_contains($lowerHtml, 'table') && str_contains($lowerHtml, 'results')) {
            return false;
        }

        return str_contains($lowerHtml, 'no result')
            || str_contains($lowerHtml, 'result not found')
            || str_contains($lowerHtml, 'invalid')
            || str_contains($lowerHtml, 'not found')
            || str_contains($lowerHtml, 'try again')
            || str_contains($lowerHtml, 'incorrect')
            || str_contains($lowerHtml, 'pin')
            || str_contains($lowerHtml, 'serial');
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
        if ($message = $this->extractPortalErrorMessage($html)) {
            return mb_substr($message, 0, 240);
        }

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

    private function extractPortalError(string $html): ?array
    {
        $message = $this->extractPortalErrorMessage($html);

        return $message ? [
            'status' => 'error',
            'code' => $this->mapErrorCode($message),
            'message' => $message,
        ] : null;
    }

    private function extractPortalErrorMessage(string $html): ?string
    {
        if (!preg_match_all('/ecertNotify\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,\s*([\'"])(error|danger|warning)\3/isu', $html, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $message = $this->normalizeText(stripcslashes($match[2]));

            if ($this->isUsefulErrorText($message)) {
                return $message;
            }
        }

        return null;
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

        if (str_contains($lower, 'card limit reached') || str_contains($lower, 'used this card five times')) {
            return 'CARD_LIMIT_REACHED';
        }

        if (str_contains($lower, 'access denied')) {
            return 'ACCESS_VIOLATION';
        }

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

        return (
            str_contains($lower, 'form-control-plaintext')
            && str_contains($lower, '<table')
            && str_contains($lower, 'subject')
            && str_contains($lower, 'grade')
        ) || (
            str_contains($lower, 'sheet-label')
            && str_contains($lower, 'sheet-value')
            && str_contains($lower, 'table')
            && str_contains($lower, 'results')
        );
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
