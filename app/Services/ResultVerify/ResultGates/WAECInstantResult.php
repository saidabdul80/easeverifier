<?php

namespace App\Services\ResultVerify\ResultGates;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WAECInstantResult extends WAECResult
{
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function fetchResult(array $params): string
    {
        $baseUrl = rtrim((string) config('services.waec.instant_base_url'), '/');
        $endpoint = $baseUrl.'/Home/InstantResultVerification';
        $cookies = new CookieJar;
        $payload = $this->instantPayload($params);
        $startedAt = microtime(true);

        Log::info('WAEC instant verification started', [
            'exam_number' => $payload['CandidateNo'],
            'exam_year' => $payload['ExamYear'],
            'exam_type' => $payload['ExamType'],
            'has_pin' => $payload['PIN'] !== '',
        ]);

        $this->httpRequest($cookies)
            ->accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
            ->get($baseUrl.'/')
            ->throw();

        $verification = $this->httpRequest($cookies)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Origin' => $baseUrl,
                'Referer' => $baseUrl.'/',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post($endpoint, $payload)
            ->throw();

        $response = $verification->json();
        if (! is_array($response) || ! array_key_exists('state', $response)) {
            throw new RuntimeException('WAEC instant verification returned an invalid response.');
        }

        if ((int) $response['state'] !== 1) {
            return json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $html = $this->httpRequest($cookies)
            ->accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
            ->withHeaders(['Referer' => $baseUrl.'/'])
            ->get($endpoint)
            ->throw()
            ->body();

        Log::info('WAEC instant verification completed', [
            'exam_number' => $payload['CandidateNo'],
            'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return $html;
    }

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
                    ['value' => 'MAY/JUN', 'label' => 'MAY/JUN (School Candidates)'],
                    ['value' => 'NOV/DEC', 'label' => 'NOV/DEC (Private Candidates)'],
                ],
            ],
            [
                'name' => 'txtPIN',
                'label' => 'Result Checker PIN',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'txtCardSerialNo',
                'label' => 'Card Serial Number (WAEC Direct cards only)',
                'type' => 'text',
                'required' => false,
            ],

        ];
    }

    public function parseResult(string $html): array
    {
        $response = json_decode(trim($html), true);

        if (is_array($response) && array_key_exists('state', $response)) {
            $message = trim((string) ($response['msg'] ?? 'WAEC instant verification failed.'));

            return [
                'status' => 'error',
                'code' => $this->instantErrorCode($message),
                'message' => $message,
            ];
        }

        $parsed = parent::parseResult($html);

        if (($parsed['status'] ?? null) !== 'success') {
            return $parsed;
        }

        $candidate = is_array($parsed['candidate'] ?? null) ? $parsed['candidate'] : [];
        $instantName = $this->instantCandidateValue($html, [
            "candidate's name",
            'candidate name',
            'candidate full name',
            'full name',
            'name',
        ]);
        $candidate['name'] = $instantName ?: ($candidate['name'] ?? null);
        $candidate['exam_number'] = $candidate['exam_number'] ?: $this->instantCandidateValue($html, [
            'examination number',
            'exam number',
            'candidate number',
            'index number',
        ]);
        $candidate['exam_year'] = $candidate['exam_year'] ?: $this->instantCandidateValue($html, [
            'examination year',
            'exam year',
        ]);
        $candidate['exam_type'] = $candidate['exam_type'] ?: $this->instantCandidateValue($html, [
            'type of examination',
            'examination type',
            'exam type',
        ]);
        $instantCentre = $this->instantCandidateValue($html, [
            'school name',
            'centre name',
            'center name',
            'examination centre',
            'examination center',
        ]);
        $candidate['centre'] = $instantCentre ?: ($candidate['centre'] ?? null);
        $candidate['candidate_name'] = $candidate['name'];
        $parsed['candidate'] = $candidate;

        return $parsed;
    }

    private function instantCandidateValue(string $html, array $labels): ?string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//*[@id or @name]') ?: [] as $element) {
            $identifier = $this->compactLabel((string) (
                $element->attributes?->getNamedItem('name')?->nodeValue
                ?? $element->attributes?->getNamedItem('id')?->nodeValue
                ?? ''
            ));

            foreach ($labels as $label) {
                if (! str_ends_with($identifier, $this->compactLabel($label))) {
                    continue;
                }

                $value = $this->instantText((string) (
                    $element->attributes?->getNamedItem('value')?->nodeValue
                    ?? $element->textContent
                    ?? ''
                ));

                if ($this->validCandidateValue($value, $labels)) {
                    return $value;
                }
            }
        }

        foreach ($xpath->query('//*[not(*) and not(self::script) and not(self::style)]') ?: [] as $node) {
            $text = $this->instantText((string) $node->textContent);

            foreach ($labels as $label) {
                $inlineValue = $this->valueAfterLabel($text, $label);
                if ($inlineValue !== null) {
                    return $inlineValue;
                }

                if ($this->compactLabel($text) !== $this->compactLabel($label)) {
                    continue;
                }

                foreach ([$node->nextSibling?->textContent, $node->parentNode?->textContent] as $possibleValue) {
                    $textValue = $this->instantText((string) $possibleValue);
                    $value = $this->valueAfterLabel($textValue, $label) ?? $textValue;

                    if ($this->validCandidateValue($value, $labels)) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    private function valueAfterLabel(string $text, string $label): ?string
    {
        if (! preg_match('/^'.preg_quote($label, '/').'\s*[:\-]?\s*(.+)$/iu', $text, $matches)) {
            return null;
        }

        $value = $this->instantText((string) ($matches[1] ?? ''));

        return $this->validCandidateValue($value, [$label]) ? $value : null;
    }

    private function validCandidateValue(string $value, array $labels): bool
    {
        if ($value === '' || mb_strlen($value) > 240) {
            return false;
        }

        return ! in_array($this->compactLabel($value), array_map($this->compactLabel(...), $labels), true);
    }

    private function compactLabel(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/i', '', strtolower($value));
    }

    private function instantText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
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

    /** @return array{ExamType: string, PIN: string, ExamYear: string, CandidateNo: string, ExamName: string} */
    private function instantPayload(array $params): array
    {
        [$examType, $examName] = $this->instantExamType((string) ($params['ExamType'] ?? ''));

        return [
            'ExamType' => $examType,
            'PIN' => trim((string) ($params['txtPIN'] ?? $params['pin'] ?? '')),
            'ExamYear' => trim((string) ($params['ExamYear'] ?? '')),
            'CandidateNo' => trim((string) ($params['txtExamNumber'] ?? $params['ExamNumber'] ?? '')),
            'ExamName' => $examName,
        ];
    }

    /** @return array{string, string} */
    private function instantExamType(string $examType): array
    {
        $normalized = strtoupper(trim($examType));

        if ($normalized === '1' || str_contains($normalized, 'MAY') || str_contains($normalized, 'SCHOOL')) {
            return ['1', 'WASSCE (For School Candidates)'];
        }

        if ($normalized === '2' || str_contains($normalized, 'NOV') || str_contains($normalized, 'DEC') || str_contains($normalized, 'PRIVATE')) {
            return ['2', 'WASSCE (For Private Candidates)'];
        }

        throw new RuntimeException('Unsupported WAEC examination type for instant verification.');
    }

    private function instantErrorCode(string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, 'pin')) {
            return 'INVALID_PIN';
        }

        if (str_contains($message, 'not found') || str_contains($message, 'no result')) {
            return 'RESULT_NOT_FOUND';
        }

        if (str_contains($message, 'candidate')) {
            return 'INVALID_CANDIDATE';
        }

        return 'UNKNOWN_ERROR';
    }

    private function httpRequest(CookieJar $cookies): PendingRequest
    {
        return Http::withOptions([
            'allow_redirects' => true,
            'cookies' => $cookies,
            'verify' => true,
        ])
            ->connectTimeout(max(2, (int) config('services.waec.connect_timeout', 8)))
            ->timeout(max(5, (int) config('services.waec.instant_timeout', 35)))
            ->withUserAgent($this->userAgent)
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9']);
    }
}
