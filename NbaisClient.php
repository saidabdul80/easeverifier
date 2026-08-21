<?php

declare(strict_types=1);

namespace RVerify\Providers\Nbais;

use RVerify\Support\Config;

final class NbaisClient
{
    private string $baseUrl;
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private array $commonHeaders = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
    ];

    public function __construct(private readonly Config $config)
    {
        $this->baseUrl = rtrim($config->string('NBAIS_BASE_URL', 'https://resultchecker.nbais.com.ng'), '/');
    }

    public function fetchResult(array $params): string
    {
        $cookieJar = tempnam(sys_get_temp_dir(), 'rverify_nbais_');
        if ($cookieJar === false) {
            throw new \RuntimeException('Unable to create temporary NBAIS session storage.');
        }

        try {
            $checkPage = $this->get('/check', $cookieJar);
            $checkForm = $this->extractForm($checkPage, '/check');

            $html = $this->post($checkForm['action'], [
                ...$checkForm['fields'],
                'exam_no' => $params['exam_no'],
                'year' => $params['year'],
                'month' => $params['month'] ?? $params['month-select'],
                'website' => '',
            ], $cookieJar, '/check');

            if (empty($params['pin'])) {
                throw new \RuntimeException('NBAIS result checker PIN is required to complete result processing.');
            }

            return $this->submitPinProcessing($cookieJar, $html, (string) $params['pin']);
        } finally {
            @unlink($cookieJar);
        }
    }

    private function submitPinProcessing(string $cookieJar, string $html, string $pin): string
    {
        $form = $this->extractForm($html, '/pin');

        $payload = $form['fields'];
        $payload['website'] = $payload['website'] ?? '';
        $payload['pin'] = $pin;

        return $this->post($form['action'], $payload, $cookieJar, '/check');
    }

    private function extractForm(string $html, string $actionNeedle): array
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if ($loaded) {
            $xpath = new \DOMXPath($dom);
            $formNode = $xpath->query('//form[contains(@action, "' . $actionNeedle . '")]')->item(0);

            if ($formNode) {
                $fields = [];
                foreach ($xpath->query('.//input[@name]', $formNode) ?: [] as $input) {
                    $name = trim((string) $input->getAttribute('name'));
                    if ($name === '') {
                        continue;
                    }

                    $fields[$name] = (string) $input->getAttribute('value');
                }

                return [
                    'action' => (string) $formNode->getAttribute('action'),
                    'fields' => $fields,
                ];
            }
        }

        $quotedNeedle = preg_quote($actionNeedle, '/');
        if (!preg_match('/<form\b[^>]*action\s*=\s*["\']?([^"\'>\s]*' . $quotedNeedle . '[^"\'>\s]*)["\']?[^>]*>(.*?)<\/form>/is', $html, $formMatch)) {
            throw new \RuntimeException('Could not extract NBAIS form for ' . $actionNeedle . '.');
        }

        $fields = [];
        preg_match_all('/<input\b[^>]*name\s*=\s*["\']?([^"\'\s>]+)["\']?[^>]*>/is', $formMatch[2], $inputMatches, PREG_SET_ORDER);

        foreach ($inputMatches as $inputMatch) {
            $name = trim((string) ($inputMatch[1] ?? ''));
            if ($name === '') {
                continue;
            }

            preg_match('/value\s*=\s*["\']?([^"\'>\s]*)["\']?/is', $inputMatch[0], $valueMatch);
            $fields[$name] = html_entity_decode((string) ($valueMatch[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return [
            'action' => $formMatch[1],
            'fields' => $fields,
        ];
    }

    private function get(string $path, string $cookieJar): string
    {
        return $this->request('GET', $path, [], $cookieJar, null);
    }

    private function post(string $path, array $payload, string $cookieJar, ?string $refererPath): string
    {
        return $this->request('POST', $path, $payload, $cookieJar, $refererPath);
    }

    private function request(string $method, string $path, array $payload, string $cookieJar, ?string $refererPath): string
    {
        $headers = $this->commonHeaders;
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Origin: ' . $this->origin();
        }
        if ($refererPath) {
            $headers[] = 'Referer: ' . $this->url($refererPath);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url($path),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_COOKIEJAR => $cookieJar,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->config->bool('NBAIS_VERIFY_SSL', true),
            CURLOPT_SSL_VERIFYHOST => $this->config->bool('NBAIS_VERIFY_SSL', true) ? 2 : 0,
            CURLOPT_CONNECTTIMEOUT => $this->config->int('NBAIS_CONNECT_TIMEOUT', 20),
            CURLOPT_TIMEOUT => $this->config->int('NBAIS_TIMEOUT', 90),
            CURLOPT_ENCODING => '',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("NBAIS cURL error: {$error}");
        }

        if ($status < 200 || $status >= 400) {
            throw new \RuntimeException("NBAIS HTTP error {$status}");
        }

        return (string) $response;
    }

    private function url(string $path): string
    {
        $path = trim($path);

        if (preg_match('#^https?://#i', $path)) {
            $parsedPath = parse_url($path, PHP_URL_PATH) ?: '/';
            $query = parse_url($path, PHP_URL_QUERY);
            $path = $parsedPath . ($query ? '?' . $query : '');
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    private function origin(): string
    {
        $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME);
        $host = parse_url($this->baseUrl, PHP_URL_HOST);
        $port = parse_url($this->baseUrl, PHP_URL_PORT);

        return $scheme && $host
            ? $scheme . '://' . $host . ($port ? ':' . $port : '')
            : $this->baseUrl;
    }
}
