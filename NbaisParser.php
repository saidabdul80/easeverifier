<?php

declare(strict_types=1);

namespace RVerify\Providers\Nbais;

final class NbaisParser
{
    public function parse(string $html): array
    {
        $html = trim($html);
        $html = trim($html, " \t\n\r\0\x0B'\",");
        $lower = strtolower($html);

        $portalError = $this->extractPortalError($html);
        if ($portalError) {
            return $portalError;
        }

        $errorMap = [
            'invalid candidate' => ['INVALID_CANDIDATE', 'Invalid candidate details.'],
            'invalid details' => ['INVALID_CANDIDATE', 'Invalid candidate details.'],
            'not found' => ['RESULT_NOT_FOUND', 'Result not found for the details provided.'],
            'no result' => ['RESULT_NOT_FOUND', 'No result found.'],
            'access denied' => ['ACCESS_VIOLATION', 'Access denied by the result portal.'],
        ];

        foreach ($errorMap as $needle => [$code, $message]) {
            if (str_contains($lower, $needle)) {
                return ['status' => 'error', 'code' => $code, 'message' => $message];
            }
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $xpath = new \DOMXPath($dom);
        $portalError = $this->extractAlertError($xpath);
        if ($portalError) {
            return $portalError;
        }

        $candidate = [
            'name' => null,
            'candidate_name' => null,
            'exam_number' => null,
            'exam_year' => null,
            'exam_type' => null,
            'centre' => null,
            'centre_name' => null,
            'centre_number' => null,
            'state' => null,
        ];
        $subjects = [];

        foreach ($xpath->query('//*[contains(@class, "placeholder-label")]') ?: [] as $labelNode) {
            $label = $this->normalizeLabel($labelNode->textContent);
            $valueNode = $xpath->query('preceding-sibling::input[1]', $labelNode)->item(0);

            if (!$valueNode) {
                $valueNode = $xpath->query('preceding-sibling::*//input[contains(@class, "form-control-plaintext")][1]', $labelNode)->item(0);
            }

            $value = trim((string) ($valueNode?->getAttribute('value') ?? $valueNode?->textContent ?? ''));
            if ($label !== '' && $value !== '') {
                $this->mapCandidateField($candidate, $label, $value);
            }
        }

        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " sheet-label ")]') ?: [] as $labelNode) {
            $label = $this->normalizeLabel($labelNode->textContent);
            $valueNode = $xpath->query('following-sibling::*[contains(concat(" ", normalize-space(@class), " "), " sheet-value ")][1]', $labelNode)->item(0);
            $value = trim((string) ($valueNode?->textContent ?? ''));

            if ($label !== '' && $value !== '') {
                $this->mapCandidateField($candidate, $label, $value);
            }
        }

        if (!$candidate['exam_year']) {
            $statusNode = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " status-pill ")]')->item(0);
            $statusText = trim((string) ($statusNode?->textContent ?? ''));
            if (preg_match('/\b(20\d{2}|19\d{2})\b/', $statusText, $match)) {
                $candidate['exam_year'] = $match[1];
            }
        }

        foreach ($xpath->query('//table[contains(@class, "table")]//tbody/tr') ?: [] as $row) {
            $this->appendSubjectFromCells($subjects, $xpath->query('th|td', $row));
        }

        if (!$subjects) {
            $this->parseFallbackTables($xpath, $candidate, $subjects);
        }

        if (!$subjects) {
            return [
                'status' => 'error',
                'code' => 'RESULT_NOT_FOUND',
                'message' => 'No subject results were found. Please verify your details.',
            ];
        }

        $candidate['candidate_name'] = $candidate['candidate_name'] ?: $candidate['name'];
        $candidate['centre_name'] = $candidate['centre_name'] ?: $candidate['centre'];
        $candidate['centre'] = $candidate['centre'] ?: $candidate['centre_name'];

        return [
            'status' => 'success',
            'candidate' => $candidate,
            'subjects' => $subjects,
            'overall' => null,
        ];
    }

    private function parseFallbackTables(\DOMXPath $xpath, array &$candidate, array &$subjects): void
    {
        $keyMap = [
            'name' => 'name',
            'candidate' => 'name',
            'reg' => 'exam_number',
            'exam no' => 'exam_number',
            'year' => 'exam_year',
            'type' => 'exam_type',
            'centre' => 'centre',
            'center' => 'centre',
            'school' => 'centre',
            'state' => 'state',
        ];

        foreach ($xpath->query('//table//tr') ?: [] as $row) {
            $cells = $xpath->query('td|th', $row);

            if ($cells->length === 2) {
                $key = strtolower(trim($cells->item(0)->textContent));
                $value = trim($cells->item(1)->textContent);
                foreach ($keyMap as $pattern => $field) {
                    if (str_contains($key, $pattern)) {
                        $candidate[$field] = $value ?: null;
                        break;
                    }
                }
            }

            $this->appendSubjectFromCells($subjects, $cells);
        }
    }

    private function appendSubjectFromCells(array &$subjects, ?\DOMNodeList $cells): void
    {
        if (!$cells || $cells->length < 2) {
            return;
        }

        $subject = trim(preg_replace('/\s+/', ' ', (string) $cells->item(0)->textContent));
        $grade = trim(preg_replace('/\s+/', ' ', (string) $cells->item(1)->textContent));
        $remark = $cells->length >= 3
            ? trim(preg_replace('/\s+/', ' ', (string) $cells->item(2)->textContent))
            : null;

        if ($subject === '' || !preg_match('/^[A-F]\d$/i', $grade)) {
            return;
        }

        $subjects[] = [
            'subject' => $subject,
            'grade' => strtoupper($grade),
            'score' => $remark !== '' ? $remark : null,
        ];
    }

    private function normalizeLabel(string $label): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $label)));
    }

    private function extractPortalError(string $html): ?array
    {
        if (!preg_match_all('/ecertNotify\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,\s*([\'"])(error|danger|warning)\3/isu', $html, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $message = $this->normalizePortalMessage(stripcslashes($match[2]));
            if ($message !== '') {
                return $this->portalErrorResponse($message);
            }
        }

        return null;
    }

    private function extractAlertError(\DOMXPath $xpath): ?array
    {
        $query = '//*[contains(concat(" ", normalize-space(@class), " "), " alert-danger ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " alert-error ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " text-danger ")]';

        foreach ($xpath->query($query) ?: [] as $node) {
            $message = $this->normalizePortalMessage($node->textContent);
            if ($message !== '') {
                return $this->portalErrorResponse($message);
            }
        }

        return null;
    }

    private function normalizePortalMessage(string $message): string
    {
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = trim(strip_tags($message));
        return trim(preg_replace('/\s+/', ' ', $message) ?? '');
    }

    private function portalErrorResponse(string $message): array
    {
        $lower = strtolower($message);
        $code = 'NBAIS_ERROR';

        if (str_contains($lower, 'card limit reached') || str_contains($lower, 'used this card five times')) {
            $code = 'CARD_LIMIT_REACHED';
        } elseif (str_contains($lower, 'not found') || str_contains($lower, 'no result')) {
            $code = 'RESULT_NOT_FOUND';
        } elseif (str_contains($lower, 'invalid')) {
            $code = 'INVALID_CANDIDATE';
        }

        return ['status' => 'error', 'code' => $code, 'message' => $message];
    }

    private function mapCandidateField(array &$candidate, string $label, string $value): void
    {
        if ($label === 'name' || $label === 'candidate name') {
            $candidate['name'] = $value;
            $candidate['candidate_name'] = $value;
            return;
        }

        if ($label === 'exam number') {
            $candidate['exam_number'] = $value;
            return;
        }

        if ($label === 'exam type') {
            $candidate['exam_type'] = $value;
            return;
        }

        if ($label === 'exam year') {
            $candidate['exam_year'] = $value;
            return;
        }

        if ($label === 'center number' || $label === 'centre number') {
            $candidate['centre_number'] = $value;
            return;
        }

        if ($label === 'center name' || $label === 'centre name') {
            $candidate['centre_name'] = $value;
            $candidate['centre'] = $value;
        }
    }
}
