<?php

declare(strict_types=1);

namespace EaseVerifier;

use InvalidArgumentException;

/**
 * Dependency-free PHP 8.1+ result evaluator v1.1.0.
 * No network, database, payment, or framework operations.
 * Messages are plain text; escape them with htmlspecialchars when rendering HTML.
 *
 * INTEGRATION: define an adapter once, then evaluate each student's existing row.
 *
 * $adapter = [
 *     'fields' => [
 *         'student.name' => ['biodata.first_name', 'biodata.surname'],
 *         'requirements' => 'programme.required_subject_ids',
 *         'subject_catalog' => 'existing_subject_rows',
 *     ],
 *     'subject_fields' => ['id' => 'subject_id', 'name' => 'subject_title'],
 *     'defaults' => ['minimum_grade' => 'C6', 'max_sittings' => 2],
 * ];
 * $decision = ResultEvaluator::evaluate($portalRecord, $verifiedApiResponse, $adapter);
 *
 * Requirements may be ID/name lists, alternative groups, keyed slots, or mapped rows.
 * PDO-style objects, nested arrays, and JSON strings are accepted by evaluate().
 * requirements_separator / alternatives_separator support explicitly delimited columns.
 * Complex business rules can use a callable adapter instead of a field map.
 * Numeric portal grade codes need an explicit grade_map, such as [4 => 'C6'].
 * No portal schema, framework, query builder, or model convention is assumed.
 *
 * OUTPUT: code/message/next_action describe the decision. can_autofill is separate
 * from can_continue. requirements.rows retains returned grades and portal IDs.
 * configuration_errors identifies mapping mistakes for the integrator.
 * Verify API/webhook authenticity on your server before supplying a result.
 */
final class ResultEvaluator
{
    public const VERSION = '1.1.0';

    /**
     * Adapt existing database rows without changing the portal schema.
     * fields maps canonical keys to dot paths; arrays of paths join name parts.
     * requirement_fields and subject_fields map existing row columns.
     * grade_map translates portal grade codes; numeric grades are never guessed.
     * A callable adapter may instead return the canonical portal array directly.
     */
    public static function adaptPortal(array|object|string $source, array|callable $adapter = []): array
    {
        if (is_callable($adapter)) {
            $portal = self::plain($adapter($source));
            if (! self::record($portal)) {
                throw new InvalidArgumentException('Portal adapter must return a portal object');
            }

            return $portal;
        }
        if (is_string($source)) {
            $source = json_decode($source, true);
            if (! is_array($source)) {
                throw new InvalidArgumentException('Portal record must contain valid JSON');
            }
        }
        $source = self::plain($source);
        if ($adapter === []) {
            return $source;
        }
        foreach (['fields', 'defaults', 'requirement_fields', 'subject_fields', 'grade_map'] as $key) {
            if (isset($adapter[$key]) && ! self::record($adapter[$key])) {
                throw new InvalidArgumentException('Adapter fields, defaults, and grade_map must be objects');
            }
        }
        $portal = ! empty($adapter['fields']) ? ($adapter['defaults'] ?? []) : array_replace($adapter['defaults'] ?? [], $source);
        foreach ($adapter['fields'] ?? [] as $target => $path) {
            if (! in_array($target, ['student.name', 'reference', 'portal_ref', 'candidate_id', 'ownership_check_enabled', 'result_used_by_another_student', 'minimum_grade', 'minimum_credits', 'max_sittings', 'requirements', 'subject_catalog', 'subject_matching'], true)) {
                throw new InvalidArgumentException('Unsupported portal field: '.$target);
            }
            if (is_array($path)) {
                $parts = array_map(fn (mixed $part): mixed => self::pathValue($source, $part), $path);
                $value = trim(implode(' ', array_filter($parts, fn (mixed $part): bool => is_string($part) || is_numeric($part))));
            } else {
                $value = self::pathValue($source, $path);
            }
            if ($value !== null) {
                self::setPath($portal, $target, $value);
            } elseif (self::pathValue($portal, $target) === null) {
                throw new InvalidArgumentException('Mapped source field for "'.$target.'" was not found. Check adapter.fields.');
            }
        }
        if (isset($adapter['subject_fields'])) {
            $portal['subject_matching']['fields'] = $adapter['subject_fields'];
        }
        if (is_string($portal['subject_catalog'] ?? null)) {
            $portal['subject_catalog'] = json_decode($portal['subject_catalog'], true);
            if (! is_array($portal['subject_catalog'])) {
                throw new InvalidArgumentException('Mapped subject_catalog must contain valid JSON');
            }
        }
        if (isset($portal['subject_catalog']) && is_array($portal['subject_catalog']) && ! array_is_list($portal['subject_catalog'])) {
            $fields = array_replace(['id' => 'id', 'name' => 'name'], $portal['subject_matching']['fields'] ?? []);
            $catalogue = [];
            foreach ($portal['subject_catalog'] as $id => $value) {
                $row = self::record($value) ? $value : [];
                if (self::pathValue($row, $fields['id']) === null) {
                    self::setPath($row, $fields['id'], (string) $id);
                }
                if (! self::record($value)) {
                    self::setPath($row, $fields['name'], $value);
                }
                $catalogue[] = $row;
            }
            $portal['subject_catalog'] = $catalogue;
        }
        if (isset($portal['requirements'])) {
            if (is_string($portal['requirements'])) {
                if (self::text($adapter['requirements_separator'] ?? null) !== '') {
                    $portal['requirements'] = array_values(array_filter(array_map('trim', explode($adapter['requirements_separator'], $portal['requirements'])), fn (string $value): bool => $value !== ''));
                } else {
                    $portal['requirements'] = json_decode($portal['requirements'], true);
                    if (! is_array($portal['requirements'])) {
                        throw new InvalidArgumentException('Mapped requirements must be a list, JSON, or delimited text with requirements_separator');
                    }
                }
            }
            if (! is_array($portal['requirements'])) {
                throw new InvalidArgumentException('Mapped requirements must be a list or keyed object');
            }
            $keyed = ! array_is_list($portal['requirements']);
            $fields = array_replace(['key' => 'key', 'subjects' => 'subjects', 'minimum_grade' => 'minimum_grade', 'required' => 'required'], $adapter['requirement_fields'] ?? []);
            $requirements = [];
            foreach ($portal['requirements'] as $key => $value) {
                $row = [];
                if (self::record($value)) {
                    foreach ($fields as $target => $path) {
                        $entry = self::pathValue($value, $path);
                        if ($entry !== null) {
                            $row[$target] = $entry;
                        }
                    }
                } else {
                    $row['subjects'] = $value;
                }
                $row['key'] = isset($row['key']) ? (string) $row['key'] : ($keyed ? (string) $key : 'requirement_'.(count($requirements) + 1));
                if (is_string($row['subjects'] ?? null) && self::text($adapter['alternatives_separator'] ?? null) !== '') {
                    $row['subjects'] = array_values(array_filter(array_map('trim', explode($adapter['alternatives_separator'], $row['subjects'])), fn (string $value): bool => $value !== ''));
                }
                if (isset($row['subjects']) && ! is_array($row['subjects'])) {
                    $row['subjects'] = [$row['subjects']];
                }
                if (array_key_exists('required', $row)) {
                    $row['required'] = self::databaseBoolean($row['required']);
                }
                if (array_key_exists('minimum_grade', $row)) {
                    $row['minimum_grade'] = self::mappedGrade($row['minimum_grade'], $adapter['grade_map'] ?? []);
                }
                $requirements[] = $row;
            }
            $portal['requirements'] = $requirements;
        }
        if (array_key_exists('minimum_grade', $portal)) {
            $portal['minimum_grade'] = self::mappedGrade($portal['minimum_grade'], $adapter['grade_map'] ?? []);
        }
        foreach (['ownership_check_enabled', 'result_used_by_another_student'] as $key) {
            if (array_key_exists($key, $portal)) {
                $portal[$key] = self::databaseBoolean($portal[$key]);
            }
        }
        foreach (['minimum_credits', 'max_sittings'] as $key) {
            if (is_string($portal[$key] ?? null) && preg_match('/^\d+$/', $portal[$key])) {
                $portal[$key] = (int) $portal[$key];
            }
        }
        foreach (['reference', 'portal_ref', 'candidate_id'] as $key) {
            if (is_int($portal[$key] ?? null) || is_float($portal[$key] ?? null)) {
                $portal[$key] = (string) $portal[$key];
            }
        }

        return $portal;
    }

    public static function normalizeSubject(string $subject): string
    {
        $key = self::subjectLabel($subject);
        $words = explode(' ', $key);
        sort($words, SORT_STRING);
        $orderedKey = implode(' ', $words);
        static $aliasKeys = null;
        if ($aliasKeys !== null) {
            return $aliasKeys[$key] ?? $aliasKeys[$orderedKey] ?? $key;
        }
        $aliases = [
            'english' => 'english language', 'general mathematics' => 'mathematics',
            'maths' => 'mathematics', 'further maths' => 'further mathematics',
            'further math' => 'further mathematics', 'agriculture' => 'agricultural science',
            'agriculture science' => 'agricultural science',
            'ict' => 'computer studies', 'ict/computer studies' => 'computer studies',
            'information and communications technology' => 'computer studies',
            'information & communications technology' => 'computer studies',
            'lit. in english' => 'literature in english', 'lit in english' => 'literature in english',
            'literature-in-english' => 'literature in english',
            'crs' => 'christian religious studies', 'irs' => 'islamic studies',
        ];

        $aliasKeys = [];
        foreach ($aliases as $alias => $canonical) {
            $label = self::subjectLabel($alias);
            $aliasKeys[$label] = self::subjectLabel($canonical);
            $tokens = explode(' ', $label);
            sort($tokens, SORT_STRING);
            $aliasKeys[implode(' ', $tokens)] = self::subjectLabel($canonical);
        }

        return $aliasKeys[$key] ?? $aliasKeys[$orderedKey] ?? $key;
    }

    /** Similarity is a heuristic score, not a probability. Use matchSubject to handle ambiguity. */
    public static function subjectSimilarity(string $left, string $right): float
    {
        $a = str_replace(' ', '', self::normalizeSubject($left));
        $b = str_replace(' ', '', self::normalizeSubject($right));
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 1;
        }
        if ((str_contains($a, $b) || str_contains($b, $a)) && abs(strlen($a) - strlen($b)) >= 3) {
            return 0;
        }
        $at = explode(' ', self::normalizeSubject($left));
        $bt = explode(' ', self::normalizeSubject($right));
        sort($at, SORT_STRING);
        sort($bt, SORT_STRING);
        if ($at === $bt) {
            return 1;
        }
        if (count($at) === count($bt) && count($at) >= 2) {
            $prefix = true;
            foreach ($at as $index => $word) {
                if ($word !== $bt[$index] && (min(strlen($word), strlen($bt[$index])) < 3 || (! str_starts_with($word, $bt[$index]) && ! str_starts_with($bt[$index], $word)))) {
                    $prefix = false;
                    break;
                }
            }
            if ($prefix) {
                return 0.96;
            }
        }
        if (min(strlen($a), strlen($b)) < 5 || preg_match('/[^a-z0-9]/', $a.$b)) {
            return 0;
        }

        return floor((1 - levenshtein($a, $b) / max(strlen($a), strlen($b))) * 10000 + 0.5) / 10000;
    }

    /**
     * Resolve IDs/codes first, then labels, normalized words, and spelling similarity.
     * Short codes are never guessed. Additional qualifiers are not substring matches.
     * Default similarity threshold is 0.88; competing matches within 0.06 need review.
     * Returns matched/review/unmatched, the portal ID/name, match type, score, and suggestions.
     * Supply fields in options for existing catalogue column names or nested paths.
     */
    public static function matchSubject(string|int|array|object $subject, array $catalogue, array $options = []): array
    {
        $subject = self::plain($subject);
        $catalogue = self::plain($catalogue);
        $config = self::matchingOptions($options);
        $entries = self::prepareCatalogue($catalogue, $config);
        $isRecord = is_array($subject);
        $explicitId = $isRecord ? self::pathValue($subject, $config['fields']['id']) : $subject;
        $identity = is_int($explicitId) ? (string) $explicitId : self::text($explicitId);
        $result = function (string $status, array $matches): array {
            $candidates = array_map(function (array $candidate): array {
                unset($candidate['priority']);

                return $candidate;
            }, array_slice($matches, 0, 3));

            return ['status' => $status, 'match_type' => $status === 'matched' ? $matches[0]['match_type'] : ($status === 'review' ? 'ambiguous' : 'none'), 'score' => $matches[0]['score'] ?? 0, 'subject_id' => $status === 'matched' ? $matches[0]['subject_id'] : null, 'subject_name' => $status === 'matched' ? $matches[0]['subject_name'] : null, 'subject_key' => $status === 'matched' ? $matches[0]['subject_key'] : null, 'candidates' => $candidates];
        };
        $candidate = fn (array $entry, string $type, float $score, int $priority): array => ['subject_id' => $entry['id'], 'subject_name' => $entry['name'], 'subject_key' => $entry['key'], 'match_type' => $type, 'score' => $score, 'priority' => $priority];
        foreach ($entries as $entry) {
            if ($entry['id'] !== null && (string) $entry['id'] === $identity) {
                return $result('matched', [$candidate($entry, 'id', 1, 5)]);
            }
        }
        if (($isRecord && $explicitId !== null) || is_int($subject) || preg_match('/^\d+$/', $identity)) {
            return $result('unmatched', []);
        }
        $query = $isRecord ? (self::text(self::pathValue($subject, $config['fields']['name'])) ?: self::text(self::pathValue($subject, $config['fields']['code']))) : self::text($subject);
        if ($query === '') {
            return $result('unmatched', []);
        }
        $matches = [];
        foreach ($entries as $entry) {
            $best = $candidate($entry, 'similarity', 0, 1);
            if ($entry['code'] !== '' && str_replace(' ', '', self::subjectLabel($query)) === str_replace(' ', '', self::subjectLabel($entry['code']))) {
                $best = $candidate($entry, 'code', 1, 4);
            }
            foreach ([$entry['name'], ...$entry['aliases']] as $label) {
                foreach (array_unique([$query, self::stripSubjectCode($query, $entry['code'])]) as $cleanQuery) {
                    if (self::subjectLabel($cleanQuery) === self::subjectLabel($label)) {
                        $match = $candidate($entry, $label === $entry['name'] ? 'exact' : 'alias', 1, 3);
                    } elseif (str_replace(' ', '', self::normalizeSubject($cleanQuery)) === str_replace(' ', '', self::normalizeSubject($label)) || self::sameTokens(self::normalizeSubject($cleanQuery), self::normalizeSubject($label))) {
                        $match = $candidate($entry, 'normalized', 1, 2);
                    } else {
                        $match = $candidate($entry, 'similarity', self::subjectSimilarity($cleanQuery, $label), 1);
                    }
                    if ($match['priority'] > $best['priority'] || ($match['priority'] === $best['priority'] && $match['score'] > $best['score'])) {
                        $best = $match;
                    }
                }
            }
            if ($best['score'] >= 0.75) {
                $matches[] = $best;
            }
        }
        usort($matches, fn (array $a, array $b): int => $b['priority'] <=> $a['priority'] ?: $b['score'] <=> $a['score'] ?: strcmp($a['subject_key'], $b['subject_key']));
        if ($matches === []) {
            return $result('unmatched', []);
        }
        $top = $matches[0];
        $next = $matches[1] ?? null;
        $ambiguous = $next !== null && $next['priority'] === $top['priority'] && ($top['priority'] > 1 || $top['score'] - $next['score'] < $config['margin'] - 0.000001);

        return $result($ambiguous || ($top['priority'] === 1 && $top['score'] < $config['threshold']) ? 'review' : 'matched', $matches);
    }

    public static function gradeRank(string $grade): int
    {
        return ['A1' => 9, 'B2' => 8, 'B3' => 7, 'C4' => 6, 'C5' => 5, 'C6' => 4, 'D7' => 3, 'P7' => 3, 'E8' => 2, 'P8' => 2, 'F9' => 1][strtoupper(trim($grade))] ?? 0;
    }

    /** Accept a direct result, API data envelope, or PayGo webhook result envelope. */
    public static function normalizeResult(array|object|string $payload): array
    {
        $normalized = ['state' => 'invalid', 'reference' => '', 'portal_ref' => '', 'candidate_id' => '', 'sittings' => []];
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
            if (! is_array($payload)) {
                return $normalized;
            }
        }
        $payload = self::plain($payload);
        foreach (['reference', 'portal_ref', 'candidate_id'] as $key) {
            $normalized[$key] = self::text($payload[$key] ?? null);
        }
        if (array_key_exists('success', $payload) && ! is_bool($payload['success'])) {
            return $normalized;
        }
        $state = strtolower(self::text($payload['result_status'] ?? $payload['status'] ?? null));
        $legacyPending = ($payload['success'] ?? null) === false && ($payload['error_code'] ?? null) === 'RESULT_REFERENCE_INVALID'
            && in_array(strtolower(self::text($payload['error'] ?? null)), ['result is not available for this reference.', 'result is not available for this portal attempt yet.'], true);
        if ($legacyPending || in_array($state, ['pending', 'processing', 'verifying', 'queued'], true) || ($payload['status'] ?? null) === 202 || in_array($payload['error_code'] ?? null, ['RESULT_PENDING', 'RESULT_NOT_READY'], true)) {
            $normalized['state'] = 'pending';

            return $normalized;
        }
        if (($payload['success'] ?? null) === false || in_array($state, ['failed', 'error'], true) || (is_int($payload['status'] ?? null) && $payload['status'] >= 400)) {
            $normalized['state'] = 'failed';

            return $normalized;
        }
        $data = self::record($payload['data'] ?? null) ? $payload['data'] : (self::record($payload['result'] ?? null) ? $payload['result'] : $payload);
        if (self::record($data['result'] ?? null) && ! is_array($data['subjects'] ?? null) && ! is_array($data['sittings'] ?? null)) {
            $data = $data['result'];
        }
        $sittings = $data['sittings'] ?? $data['results'] ?? [$data];
        if (! is_array($sittings) || ! array_is_list($sittings) || $sittings === []) {
            return $normalized;
        }
        $seen = [];
        foreach ($sittings as $index => $sitting) {
            if (! self::record($sitting)) {
                return $normalized;
            }
            $sittingNo = $sitting['sitting_no'] ?? $payload['sitting'] ?? $index + 1;
            if (! is_int($sittingNo) || $sittingNo < 1 || in_array($sittingNo, $seen, true)) {
                return $normalized;
            }
            $seen[] = $sittingNo;
            $candidate = self::record($sitting['candidate'] ?? null) ? $sitting['candidate'] : (self::record($data['candidate'] ?? null) ? $data['candidate'] : []);
            $candidateName = self::text($candidate['name'] ?? $candidate['full_name'] ?? $candidate['candidate_name'] ?? $sitting['candidate_name'] ?? null);
            $rows = $sitting['subjects'] ?? (self::record($sitting['result'] ?? null) ? ($sitting['result']['subjects'] ?? null) : null);
            if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
                return $normalized;
            }
            $subjects = [];
            foreach ($rows as $row) {
                if (! self::record($row) || self::text($row['subject'] ?? null) === '' || self::normalizeSubject($row['subject']) === '' || self::text($row['grade'] ?? null) === '') {
                    return $normalized;
                }
                $subjects[] = ['subject' => trim($row['subject']), 'normalized_subject' => self::normalizeSubject($row['subject']), 'grade' => strtoupper(trim($row['grade'])), 'sitting' => $sittingNo];
            }
            $normalized['sittings'][] = ['sitting' => $sittingNo, 'candidate_name' => $candidateName, 'subjects' => $subjects];
        }
        $normalized['state'] = 'ready';

        return $normalized;
    }

    public static function bestSubjects(array|object|string $payload, array $portal = []): array
    {
        $normalized = self::normalizeResult($payload);
        if ($normalized['state'] !== 'ready') {
            return [];
        }
        $best = [];
        $catalogue = self::portalCatalogue($portal);
        foreach ($normalized['sittings'] as $sitting) {
            foreach ($sitting['subjects'] as $row) {
                if ($catalogue !== []) {
                    $match = self::matchSubject($row['subject'], $catalogue, $portal['subject_matching'] ?? []);
                    $row = [...$row, 'subject_key' => $match['subject_key'] ?? 'unmapped:'.$row['normalized_subject'], 'portal_subject_id' => $match['subject_id'], 'portal_subject_name' => $match['subject_name'], 'match_type' => $match['match_type'], 'match_score' => $match['score'], 'match_status' => $match['status'], 'match_candidates' => $match['candidates']];
                }
                $key = $row['subject_key'] ?? $row['normalized_subject'];
                $previous = $best[$key] ?? null;
                if ($previous === null || self::gradeRank($row['grade']) > self::gradeRank($previous['grade']) || (self::gradeRank($row['grade']) === self::gradeRank($previous['grade']) && $row['sitting'] < $previous['sitting'])) {
                    $best[$key] = $row;
                }
            }
        }
        ksort($best, SORT_STRING);

        return array_values($best);
    }

    /** Duplicate use must be determined by the portal's trusted server. */
    public static function checkOwnership(array $portal, array|object|string $payload): array
    {
        $normalized = self::normalizeResult($payload);
        $expected = self::record($portal['student'] ?? null) ? self::text($portal['student']['name'] ?? null) : '';
        $names = $normalized['state'] === 'ready' ? array_column($normalized['sittings'], 'candidate_name') : [];
        $code = 'OWNERSHIP_MATCHED';
        $status = 'matched';
        if ($normalized['state'] !== 'ready') {
            $code = 'OWNERSHIP_NOT_CHECKED';
            $status = 'not_checked';
        } elseif (($portal['result_used_by_another_student'] ?? false) === true) {
            $code = 'DUPLICATE_RESULT';
            $status = 'mismatch';
        } elseif (($portal['ownership_check_enabled'] ?? true) === false) {
            $code = 'OWNERSHIP_SKIPPED';
            $status = 'skipped';
        } else {
            $expectedTokens = self::nameTokens($expected);
            $review = $expectedTokens === [];
            $mismatch = false;
            foreach ($names as $name) {
                $tokens = self::nameTokens($name);
                if ($tokens === []) {
                    $review = true;

                    continue;
                }
                if ($tokens === $expectedTokens) {
                    continue;
                }
                if ($expectedTokens === [] || array_intersect($tokens, $expectedTokens) !== []) {
                    $review = true;
                } else {
                    $mismatch = true;
                }
            }
            if ($mismatch) {
                $code = 'OWNERSHIP_MISMATCH';
                $status = 'mismatch';
            } elseif ($review) {
                $code = 'OWNERSHIP_REVIEW';
                $status = 'review';
            }
        }

        return ['status' => $status, 'code' => $code, 'message' => self::messageFor($code), 'expected_name' => $expected, 'candidate_names' => $names];
    }

    /** Each subject fills one slot. Resolve overlapping alternatives before grading. */
    public static function checkRequirements(array $portal, array|object|string $payload): array
    {
        $rules = self::validateRules($portal);
        $normalized = self::normalizeResult($payload);
        if ($normalized['state'] !== 'ready') {
            return ['passed' => false, 'complete' => false, 'rows' => [], 'missing_subjects' => [], 'low_grades' => [], 'review_subjects' => [], 'credits_found' => 0, 'minimum_credits' => $rules['minimum_credits'], 'sittings_used' => 0, 'max_sittings' => $rules['max_sittings']];
        }
        $best = self::bestSubjects($payload, $portal);
        $candidates = [];
        foreach ($rules['requirements'] as $rule) {
            $rows = array_values(array_filter($best, fn (array $row): bool => $row['match_status'] === 'matched' && in_array($row['subject_key'], $rule['subjects'], true)));
            usort($rows, fn (array $a, array $b): int => self::gradeRank($b['grade']) <=> self::gradeRank($a['grade']) ?: $a['sitting'] <=> $b['sitting'] ?: strcmp($a['normalized_subject'], $b['normalized_subject']));
            $candidates[] = $rows;
        }
        $assigned = [];
        $owners = [];
        $assign = function (int $slot, array &$seen) use (&$assign, &$assigned, &$owners, $candidates, $rules): bool {
            foreach ($candidates[$slot] as $row) {
                $key = $row['subject_key'];
                if (self::gradeRank($row['grade']) < self::gradeRank($rules['requirements'][$slot]['minimum_grade']) || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $owner = $owners[$key] ?? null;
                if ($owner === null || $assign($owner, $seen)) {
                    $owners[$key] = $slot;
                    $assigned[$slot] = $row;

                    return true;
                }
            }

            return false;
        };
        $order = array_keys($rules['requirements']);
        usort($order, fn (int $a, int $b): int => (int) $rules['requirements'][$b]['required'] <=> (int) $rules['requirements'][$a]['required'] ?: $a <=> $b);
        foreach ([true, false] as $required) {
            $slots = array_filter($order, fn (int $slot): bool => $rules['requirements'][$slot]['required'] === $required);
            foreach ($slots as $slot) {
                $seen = [];
                $assign($slot, $seen);
            }
            foreach ($slots as $slot) {
                if (isset($assigned[$slot])) {
                    continue;
                }
                foreach ($candidates[$slot] as $row) {
                    if (! isset($owners[$row['subject_key']])) {
                        $assigned[$slot] = $row;
                        $owners[$row['subject_key']] = $slot;
                        break;
                    }
                }
            }
        }
        $rows = [];
        $missing = [];
        $low = [];
        foreach ($rules['requirements'] as $index => $rule) {
            $row = $assigned[$index] ?? null;
            $status = $row === null ? 'missing' : (self::gradeRank($row['grade']) >= self::gradeRank($rule['minimum_grade']) ? 'met' : 'grade_below_requirement');
            $rows[] = ['key' => $rule['key'], 'required' => $rule['required'], 'minimum_grade' => $rule['minimum_grade'], 'subject' => $row['subject'] ?? null, 'grade' => $row['grade'] ?? null, 'sitting' => $row['sitting'] ?? null, 'portal_subject_id' => $row['portal_subject_id'] ?? null, 'portal_subject_name' => $row['portal_subject_name'] ?? null, 'match_type' => $row['match_type'] ?? null, 'match_score' => $row['match_score'] ?? null, 'status' => $status];
            if ($rule['required'] && $status === 'missing') {
                $missing[] = $rule['key'];
            }
            if ($rule['required'] && $status === 'grade_below_requirement') {
                $low[] = $rule['key'];
            }
        }
        $credits = count(array_filter($best, fn (array $row): bool => (! array_key_exists('subject_catalog', $portal) || $row['match_status'] === 'matched') && self::gradeRank($row['grade']) >= self::gradeRank($rules['minimum_grade'])));
        $review = array_values(array_map(fn (array $row): array => ['subject' => $row['subject'], 'candidates' => $row['match_candidates']], array_filter($best, fn (array $row): bool => $row['match_status'] === 'review' || array_key_exists('subject_catalog', $portal) && $row['match_status'] === 'unmatched' && $credits < $rules['minimum_credits'])));
        $ready = $normalized['state'] === 'ready';
        $sittingCount = $ready ? count($normalized['sittings']) : 0;

        return ['passed' => $ready && $missing === [] && $low === [] && $review === [] && $credits >= $rules['minimum_credits'] && $sittingCount <= $rules['max_sittings'], 'complete' => $ready && $missing === [], 'rows' => $rows, 'missing_subjects' => $missing, 'low_grades' => $low, 'review_subjects' => $review, 'credits_found' => $credits, 'minimum_credits' => $rules['minimum_credits'], 'sittings_used' => $sittingCount, 'max_sittings' => $rules['max_sittings']];
    }

    public static function evaluate(array|object|string $portal, array|object|string $payload, array|callable $adapter = []): array
    {
        $adapterError = null;
        try {
            if (is_array($adapter) && array_key_exists('result_adapter', $adapter)) {
                if (! is_callable($adapter['result_adapter'])) {
                    throw new InvalidArgumentException('result_adapter must be callable');
                }
                $payload = $adapter['result_adapter']($payload);
            }
            $portal = self::adaptPortal($portal, $adapter);
        } catch (InvalidArgumentException $error) {
            $adapterError = $error;
            $portal = is_string($portal) ? [] : self::plain($portal);
        }
        $normalized = self::normalizeResult($payload);
        $ownership = self::checkOwnership($portal, $payload);
        $requirements = null;
        $code = null;
        $configurationErrors = [];
        try {
            if ($adapterError !== null) {
                throw $adapterError;
            }
            $requirements = self::checkRequirements($portal, $payload);
        } catch (InvalidArgumentException $error) {
            $code = 'CONFIGURATION_ERROR';
            $configurationErrors[] = $error->getMessage();
        }
        if ($code === null) {
            if ($normalized['state'] !== 'ready') {
                $code = ['pending' => 'RESULT_PENDING', 'failed' => 'RESULT_FAILED', 'invalid' => 'INVALID_RESULT'][$normalized['state']];
            } else {
                foreach (['reference', 'portal_ref', 'candidate_id'] as $key) {
                    if (self::text($portal[$key] ?? null) !== '' && $normalized[$key] !== '' && trim($portal[$key]) !== $normalized[$key]) {
                        $code = 'RESULT_CONTEXT_MISMATCH';
                        break;
                    }
                }
                if ($code === null) {
                    $code = match (true) {
                        ! in_array($ownership['status'], ['matched', 'skipped'], true) => $ownership['code'],
                        $requirements['sittings_used'] > $requirements['max_sittings'] => 'SITTING_LIMIT_EXCEEDED',
                        $requirements['review_subjects'] !== [] => 'SUBJECT_MATCH_REVIEW',
                        $requirements['missing_subjects'] !== [] => 'SUBJECTS_MISSING',
                        $requirements['low_grades'] !== [] => 'GRADES_BELOW_REQUIREMENT',
                        $requirements['credits_found'] < $requirements['minimum_credits'] => 'INSUFFICIENT_CREDITS',
                        default => 'REQUIREMENTS_MET',
                    };
                }
            }
        }
        $autofill = in_array($code, ['REQUIREMENTS_MET', 'SUBJECTS_MISSING', 'GRADES_BELOW_REQUIREMENT', 'INSUFFICIENT_CREDITS'], true)
            && array_filter($requirements['rows'], fn (array $row): bool => $row['grade'] !== null && ! self::gradeRank($row['grade'])) === [];
        $status = match ($code) {
            'REQUIREMENTS_MET' => 'ready', 'RESULT_PENDING' => 'pending', 'RESULT_FAILED' => 'failed',
            'CONFIGURATION_ERROR', 'INVALID_RESULT', 'OWNERSHIP_REVIEW', 'SUBJECT_MATCH_REVIEW' => 'review', default => 'blocked',
        };
        $next = match (true) {
            $code === 'REQUIREMENTS_MET' => 'continue_portal_checks',
            $code === 'RESULT_PENDING' => 'retry_result_pull',
            $code === 'RESULT_FAILED' => 'review_result_error',
            $code === 'CONFIGURATION_ERROR' => 'check_portal_configuration',
            $code === 'SUBJECTS_MISSING' && $requirements['sittings_used'] < $requirements['max_sittings'] => 'review_or_verify_another_sitting',
            default => 'contact_school',
        };

        return ['version' => self::VERSION, 'code' => $code, 'status' => $status, 'message' => self::messageFor($code), 'next_action' => $next, 'can_autofill' => $autofill, 'can_continue' => $code === 'REQUIREMENTS_MET', 'reference' => $normalized['reference'], 'ownership' => $ownership, 'requirements' => $requirements, 'configuration_errors' => $configurationErrors];
    }

    public static function messageFor(string $code): string
    {
        return [
            'REQUIREMENTS_MET' => 'The supplied result meets the configured portal checks. Continue with the remaining portal checks.',
            'RESULT_PENDING' => 'The result is still being prepared. Retry the result pull using the existing reference; another payment is not required for this status.',
            'RESULT_FAILED' => 'The result could not be retrieved. Review the provider error and check the exam details before retrying.',
            'INVALID_RESULT' => 'A usable verified result was not supplied. Retrieve the result before checking ownership or requirements.',
            'RESULT_CONTEXT_MISMATCH' => 'The result belongs to a different portal request. Auto-fill is blocked; check the student and reference.',
            'DUPLICATE_RESULT' => 'This result is already linked to another student. Auto-fill is blocked; contact the school to resolve the duplicate.',
            'OWNERSHIP_MISMATCH' => 'The verified result name does not match the student profile. Auto-fill is blocked; check the exam details or contact the school.',
            'OWNERSHIP_REVIEW' => 'The result name is missing or only partially matches the student profile. School ownership review is required before auto-fill.',
            'OWNERSHIP_MATCHED' => 'The candidate name matches the student profile on every supplied sitting.',
            'OWNERSHIP_SKIPPED' => 'Name comparison was disabled by the portal. Ownership has not been established by this library.',
            'OWNERSHIP_NOT_CHECKED' => 'Ownership cannot be checked until a usable result is supplied.',
            'SITTING_LIMIT_EXCEEDED' => 'The result contains more sittings than the programme allows. Ask the school to review the permitted sittings.',
            'SUBJECTS_MISSING' => 'The result was verified, but one or more required subjects are missing. Review the listed subjects and any permitted additional sitting.',
            'SUBJECT_MATCH_REVIEW' => 'A returned subject could not be mapped confidently to the portal catalogue. Review the suggested matches before applying grades.',
            'GRADES_BELOW_REQUIREMENT' => 'Required subjects are present, but one or more grades are below the configured minimum or unrecognized. Review the listed grades.',
            'INSUFFICIENT_CREDITS' => 'The result does not contain enough distinct subjects at the configured credit grade. Review the programme requirements.',
            'CONFIGURATION_ERROR' => 'The portal rules are incomplete or invalid. Supply a student profile and explicit subject requirements before continuing.',
        ][$code] ?? 'The result needs school review before continuing.';
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private static function plain(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        return is_array($value) ? array_map(self::plain(...), $value) : $value;
    }

    private static function pathValue(mixed $source, mixed $path): mixed
    {
        if (! is_string($path) || $path === '') {
            throw new InvalidArgumentException('Mapping paths must be non-empty strings');
        }
        if ($path === '$') {
            return $source;
        }
        if (is_array($source) && array_key_exists($path, $source)) {
            return $source[$path];
        }
        foreach (explode('.', $path) as $key) {
            if (in_array($key, ['__proto__', 'prototype', 'constructor'], true)) {
                throw new InvalidArgumentException('Unsafe mapping path');
            }
            if (! is_array($source) || ! array_key_exists($key, $source)) {
                return null;
            }
            $source = $source[$key];
        }

        return $source;
    }

    private static function setPath(array &$target, string $path, mixed $value): void
    {
        $node = &$target;
        foreach (explode('.', $path) as $key) {
            if (in_array($key, ['__proto__', 'prototype', 'constructor'], true)) {
                throw new InvalidArgumentException('Unsafe mapping path');
            }
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                $node[$key] = [];
            }
            $node = &$node[$key];
        }
        $node = $value;
    }

    private static function databaseBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }
        $key = is_string($value) || is_numeric($value) ? strtolower(trim((string) $value)) : '';
        if (in_array($key, ['1', 'true', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($key, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }

        return $value;
    }

    private static function mappedGrade(mixed $value, array $mapping): mixed
    {
        return is_string($value) || is_numeric($value) ? ($mapping[(string) $value] ?? $value) : $value;
    }

    private static function subjectLabel(string $value): string
    {
        $words = preg_split('/\s+/u', trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', strtolower(trim($value)))));
        if (array_filter($words, fn (string $word): bool => strlen($word) !== 1) === []) {
            return implode('', $words);
        }
        $expansions = ['math' => 'mathematics', 'maths' => 'mathematics', 'lang' => 'language', 'sci' => 'science', 'lit' => 'literature', 'agric' => 'agricultural', 'rel' => 'religious', 'stud' => 'studies', 'std' => 'studies', 'acct' => 'accounting', 'govt' => 'government'];
        $words = array_values(array_filter($words, fn (string $word): bool => ! in_array($word, ['and', 'of', 'the', 'in'], true)));

        return implode(' ', array_map(fn (string $word): string => $expansions[$word] ?? $word, $words));
    }

    private static function sameTokens(string $a, string $b): bool
    {
        $left = explode(' ', $a);
        $right = explode(' ', $b);
        sort($left, SORT_STRING);
        sort($right, SORT_STRING);

        return $a !== '' && $b !== '' && $left === $right;
    }

    private static function matchingOptions(array $options): array
    {
        $threshold = $options['threshold'] ?? 0.88;
        $margin = $options['margin'] ?? 0.06;
        if ((! is_int($threshold) && ! is_float($threshold)) || ! is_finite((float) $threshold) || $threshold < 0.85 || $threshold > 1 || (! is_int($margin) && ! is_float($margin)) || ! is_finite((float) $margin) || $margin < 0.01 || $margin > 0.5 || ! self::record($options['fields'] ?? [])) {
            throw new InvalidArgumentException('Invalid subject matching options');
        }
        $fields = array_merge(['id' => 'id', 'name' => 'name', 'code' => 'code', 'aliases' => 'aliases'], $options['fields'] ?? []);
        foreach ($fields as $key => $value) {
            if (! in_array($key, ['id', 'name', 'code', 'aliases'], true) || self::text($value) === '') {
                throw new InvalidArgumentException('Invalid subject field mapping');
            }
        }

        return ['threshold' => $threshold, 'margin' => $margin, 'fields' => $fields];
    }

    private static function prepareCatalogue(array $catalogue, array $config): array
    {
        if (! array_is_list($catalogue)) {
            throw new InvalidArgumentException('Invalid subject catalogue');
        }
        $entries = [];
        foreach ($catalogue as $value) {
            $isRecord = self::record($value);
            $id = $isRecord ? self::pathValue($value, $config['fields']['id']) : null;
            $name = $isRecord ? self::text(self::pathValue($value, $config['fields']['name'])) : self::text($value);
            $code = $isRecord ? (self::pathValue($value, $config['fields']['code']) ?? '') : '';
            $aliases = $isRecord ? (self::pathValue($value, $config['fields']['aliases']) ?? []) : [];
            if (self::normalizeSubject($name) === '') {
                throw new InvalidArgumentException('Subject catalogue name field "'.$config['fields']['name'].'" is missing or empty. Check subject_fields.');
            }
            if (($id !== null && ! is_int($id) && self::text($id) === '') || ! is_string($code) || ! is_array($aliases) || ! array_is_list($aliases)) {
                throw new InvalidArgumentException('Invalid subject catalogue');
            }
            foreach ($aliases as $alias) {
                if (self::text($alias) === '' || self::normalizeSubject($alias) === '') {
                    throw new InvalidArgumentException('Invalid subject catalogue');
                }
            }
            $tokens = explode(' ', self::normalizeSubject($name));
            sort($tokens, SORT_STRING);
            $key = $id !== null ? 'id:'.$id : 'name:'.implode('', $tokens);
            if (isset($entries[$key])) {
                if ($id !== null) {
                    throw new InvalidArgumentException('Duplicate subject ID');
                }
                $entries[$key]['aliases'] = [...$entries[$key]['aliases'], $name, ...$aliases];
            } else {
                $entries[$key] = ['id' => $id, 'key' => $key, 'name' => $name, 'code' => $code, 'aliases' => $aliases];
            }
        }

        return array_values($entries);
    }

    private static function stripSubjectCode(string $query, string $code): string
    {
        if ($code === '') {
            return $query;
        }
        $tokens = explode(' ', self::subjectLabel($query));
        $key = str_replace(' ', '', self::subjectLabel($code));
        if (count($tokens) > 1 && $tokens[0] === $key) {
            array_shift($tokens);
        }
        if (count($tokens) > 1 && $tokens[array_key_last($tokens)] === $key) {
            array_pop($tokens);
        }

        return implode(' ', $tokens);
    }

    private static function portalCatalogue(array $portal): array
    {
        if (array_key_exists('subject_catalog', $portal)) {
            if (! is_array($portal['subject_catalog'])) {
                throw new InvalidArgumentException('Invalid subject catalogue');
            }

            return $portal['subject_catalog'];
        }
        $labels = [];
        foreach ($portal['requirements'] ?? [] as $rule) {
            foreach ($rule['subjects'] ?? [] as $subject) {
                if (! is_string($subject)) {
                    throw new InvalidArgumentException('Subject IDs require a catalogue');
                }
                if (self::normalizeSubject($subject) !== '') {
                    $labels[] = $subject;
                }
            }
        }

        return $labels;
    }

    private static function record(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    private static function nameTokens(string $name): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', strtolower(trim($name)), $matches);
        $tokens = array_values(array_diff(array_unique($matches[0]), ['mr', 'mrs', 'miss', 'ms', 'dr', 'chief']));
        sort($tokens, SORT_STRING);

        return $tokens;
    }

    private static function validateRules(array $portal): array
    {
        $invalid = function (string $reason = 'Invalid portal rules'): never {
            throw new InvalidArgumentException($reason);
        };
        if (! self::record($portal['student'] ?? null) || ! is_string($portal['student']['name'] ?? null)) {
            $invalid('Map student.name to a string in your portal record.');
        }
        foreach (['ownership_check_enabled', 'result_used_by_another_student'] as $key) {
            if (array_key_exists($key, $portal) && ! is_bool($portal[$key])) {
                $invalid();
            }
        }
        if (($portal['ownership_check_enabled'] ?? true) !== false && self::text($portal['student']['name'] ?? null) === '') {
            $invalid('student.name is empty. Check the adapter name field paths.');
        }
        foreach (['reference', 'portal_ref', 'candidate_id'] as $key) {
            if (array_key_exists($key, $portal) && self::text($portal[$key]) === '') {
                $invalid();
            }
        }
        $minimum = $portal['minimum_grade'] ?? 'C6';
        $credits = $portal['minimum_credits'] ?? 0;
        $maxSittings = $portal['max_sittings'] ?? 2;
        if (! is_string($minimum) || ! self::gradeRank($minimum)) {
            $invalid('minimum_grade must be a known grade; map database grade codes with grade_map.');
        }
        if (! is_int($credits) || $credits < 0) {
            $invalid('minimum_credits must be a non-negative integer.');
        }
        if (! is_int($maxSittings) || $maxSittings < 1 || $maxSittings > 2) {
            $invalid('max_sittings must be 1 or 2.');
        }
        if (! is_array($portal['requirements'] ?? null) || ! array_is_list($portal['requirements']) || $portal['requirements'] === []) {
            $invalid('Map requirements to a non-empty list of subject IDs, names, or requirement rows.');
        }
        $catalogue = self::portalCatalogue($portal);
        $options = $portal['subject_matching'] ?? [];
        if (! is_array($options)) {
            $invalid();
        }
        self::prepareCatalogue($catalogue, self::matchingOptions($options));
        if ($catalogue === []) {
            $invalid();
        }
        $keys = [];
        $required = 0;
        $requirements = [];
        foreach ($portal['requirements'] as $rule) {
            if (! self::record($rule) || self::text($rule['key'] ?? null) === '' || in_array($rule['key'], $keys, true) || ! is_array($rule['subjects'] ?? null) || ! array_is_list($rule['subjects']) || $rule['subjects'] === []) {
                $invalid('Each requirement needs a unique key and a non-empty subjects list.');
            }
            foreach ($rule['subjects'] as $subject) {
                if (! is_int($subject) && self::text($subject) === '') {
                    $invalid();
                }
            }
            if (array_key_exists('required', $rule) && ! is_bool($rule['required'])) {
                $invalid();
            }
            $min = $rule['minimum_grade'] ?? $minimum;
            if (! is_string($min) || ! self::gradeRank($min)) {
                $invalid('Requirement minimum_grade must be a known grade; use grade_map for numeric portal codes.');
            }
            $keys[] = $rule['key'];
            if (($rule['required'] ?? true) !== false) {
                $required++;
            }
            $subjects = array_map(fn (string|int $subject): array => self::matchSubject($subject, $catalogue, $options), $rule['subjects']);
            if (array_filter($subjects, fn (array $match): bool => $match['status'] !== 'matched') !== []) {
                $invalid('A requirement subject could not be resolved uniquely in subject_catalog. Check its ID, code, or aliases.');
            }
            $requirements[] = ['key' => $rule['key'], 'subjects' => array_column($subjects, 'subject_key'), 'minimum_grade' => strtoupper(trim($min)), 'required' => ($rule['required'] ?? true) !== false];
        }
        if ($required === 0) {
            $invalid();
        }

        return ['requirements' => $requirements, 'minimum_grade' => strtoupper(trim($minimum)), 'minimum_credits' => $credits, 'max_sittings' => $maxSittings];
    }
}
