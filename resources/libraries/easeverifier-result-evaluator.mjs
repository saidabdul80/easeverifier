/**
 * EaseVerifier Result Evaluator v1.1.0 — dependency-free ES module.
 * Evaluates trusted result data only; no network, database, or payment operations.
 * All returned messages are plain text. Use textContent when displaying them.
 *
 * INTEGRATION: map the portal once, then evaluate each existing student record.
 * const adapter = {
 *   fields: {
 *     'student.name': ['biodata.first_name', 'biodata.surname'],
 *     requirements: 'programme.required_subject_ids',
 *     subject_catalog: 'existing_subject_rows',
 *   },
 *   subject_fields: { id: 'subject_id', name: 'subject_title' },
 *   defaults: { minimum_grade: 'C6', max_sittings: 2 },
 * };
 * const decision = ResultEvaluator.evaluate(portalRecord, verifiedApiResponse, adapter);
 *
 * Requirements can be IDs/names, alternative groups, keyed slots, or mapped rows.
 * Nested records and JSON strings are accepted. Configure separators for CSV columns.
 * For custom business logic, an adapter function can return the canonical portal object.
 * Numeric portal grade codes need an explicit grade_map, e.g. { 4: 'C6' }.
 * No database schema, framework, or model convention is assumed.
 *
 * OUTPUT: code/message/next_action explain the decision; can_autofill is separate
 * from can_continue. requirements.rows preserves your portal IDs and returned grades.
 * configuration_errors explains mapping mistakes. Use trusted API/webhook data only.
 */
export class ResultEvaluator {
    static version = '1.1.0';

    /**
     * Map existing portal records without changing the portal database.
     * fields maps canonical keys to dot paths; a list of paths joins name parts.
     * requirement_fields and subject_fields map existing row columns.
     * grade_map translates the portal's grade codes, never guessed numeric ranks.
     * For more complex structures, pass a function that returns the canonical portal object.
     */
    static adaptPortal(source, adapter = {}) {
        if (typeof adapter === 'function') {
            const portal = adapter(source);
            if (!record(portal))
                throw new TypeError(
                    'Portal adapter must return a portal object',
                );
            return portal;
        }
        if (typeof source === 'string') {
            try {
                source = JSON.parse(source);
            } catch {
                throw new TypeError('Portal record must contain valid JSON');
            }
        }
        if (!record(source) || !record(adapter))
            throw new TypeError('Portal record and adapter must be objects');
        if (!Object.keys(adapter).length) return source;
        const portal = copyValue(
            adapter.fields && Object.keys(adapter.fields).length
                ? (adapter.defaults ?? {})
                : { ...(adapter.defaults ?? {}), ...source },
        );
        if (
            !record(portal) ||
            !record(adapter.fields ?? {}) ||
            !record(adapter.requirement_fields ?? {}) ||
            !record(adapter.subject_fields ?? {}) ||
            !record(adapter.grade_map ?? {})
        )
            throw new TypeError(
                'Adapter fields, defaults, and grade_map must be objects',
            );
        for (const [target, path] of Object.entries(adapter.fields ?? {})) {
            if (
                ![
                    'student.name',
                    'reference',
                    'portal_ref',
                    'candidate_id',
                    'ownership_check_enabled',
                    'result_used_by_another_student',
                    'minimum_grade',
                    'minimum_credits',
                    'max_sittings',
                    'requirements',
                    'subject_catalog',
                    'subject_matching',
                ].includes(target)
            )
                throw new TypeError(`Unsupported portal field: ${target}`);
            const value = Array.isArray(path)
                ? path
                      .map((part) => pathValue(source, part))
                      .filter(
                          (part) =>
                              typeof part === 'string' ||
                              typeof part === 'number',
                      )
                      .join(' ')
                      .trim()
                : pathValue(source, path);
            if (value !== undefined && value !== null)
                setPath(portal, target, copyValue(value));
            else if (
                pathValue(portal, target) === undefined ||
                pathValue(portal, target) === null
            )
                throw new TypeError(
                    `Mapped source field for "${target}" was not found. Check adapter.fields.`,
                );
        }
        if (adapter.subject_fields)
            portal.subject_matching = {
                ...(portal.subject_matching ?? {}),
                fields: copyValue(adapter.subject_fields),
            };
        if (typeof portal.subject_catalog === 'string') {
            try {
                portal.subject_catalog = JSON.parse(portal.subject_catalog);
            } catch {
                throw new TypeError(
                    'Mapped subject_catalog must contain valid JSON',
                );
            }
        }
        if (
            portal.subject_catalog !== undefined &&
            !Array.isArray(portal.subject_catalog)
        ) {
            if (!record(portal.subject_catalog))
                throw new TypeError(
                    'Mapped subject_catalog must be a list or ID-to-subject object',
                );
            const fields = {
                id: 'id',
                name: 'name',
                ...(portal.subject_matching?.fields ?? {}),
            };
            portal.subject_catalog = Object.entries(portal.subject_catalog).map(
                ([id, value]) => {
                    const row = record(value) ? copyValue(value) : {};
                    if (pathValue(row, fields.id) === undefined)
                        setPath(row, fields.id, id);
                    if (!record(value)) setPath(row, fields.name, value);
                    return row;
                },
            );
        }
        if (portal.requirements !== undefined) {
            if (typeof portal.requirements === 'string') {
                if (text(adapter.requirements_separator))
                    portal.requirements = portal.requirements
                        .split(adapter.requirements_separator)
                        .map((value) => value.trim())
                        .filter(Boolean);
                else {
                    try {
                        portal.requirements = JSON.parse(portal.requirements);
                    } catch {
                        throw new TypeError(
                            'Mapped requirements must be a list, JSON, or delimited text with requirements_separator',
                        );
                    }
                }
            }
            if (
                !Array.isArray(portal.requirements) &&
                !record(portal.requirements)
            )
                throw new TypeError(
                    'Mapped requirements must be a list or keyed object',
                );
            const keyed = !Array.isArray(portal.requirements);
            const fields = {
                key: 'key',
                subjects: 'subjects',
                minimum_grade: 'minimum_grade',
                required: 'required',
                ...(adapter.requirement_fields ?? {}),
            };
            portal.requirements = Object.entries(portal.requirements).map(
                ([key, value], index) => {
                    const row = record(value)
                        ? Object.fromEntries(
                              Object.entries(fields)
                                  .map(([target, path]) => [
                                      target,
                                      pathValue(value, path),
                                  ])
                                  .filter(
                                      ([, entry]) =>
                                          entry !== undefined && entry !== null,
                                  ),
                          )
                        : { subjects: value };
                    row.key =
                        row.key !== undefined
                            ? String(row.key)
                            : keyed
                              ? key
                              : `requirement_${index + 1}`;
                    if (
                        typeof row.subjects === 'string' &&
                        text(adapter.alternatives_separator)
                    )
                        row.subjects = row.subjects
                            .split(adapter.alternatives_separator)
                            .map((value) => value.trim())
                            .filter(Boolean);
                    if (
                        row.subjects !== undefined &&
                        !Array.isArray(row.subjects)
                    )
                        row.subjects = [row.subjects];
                    if ('required' in row)
                        row.required = databaseBoolean(row.required);
                    if ('minimum_grade' in row)
                        row.minimum_grade = mappedGrade(
                            row.minimum_grade,
                            adapter.grade_map ?? {},
                        );
                    return row;
                },
            );
        }
        if ('minimum_grade' in portal)
            portal.minimum_grade = mappedGrade(
                portal.minimum_grade,
                adapter.grade_map ?? {},
            );
        for (const key of [
            'ownership_check_enabled',
            'result_used_by_another_student',
        ])
            if (key in portal) portal[key] = databaseBoolean(portal[key]);
        for (const key of ['minimum_credits', 'max_sittings'])
            if (typeof portal[key] === 'string' && /^\d+$/.test(portal[key]))
                portal[key] = Number(portal[key]);
        for (const key of ['reference', 'portal_ref', 'candidate_id'])
            if (typeof portal[key] === 'number')
                portal[key] = String(portal[key]);
        return portal;
    }

    static normalizeSubject(subject) {
        const key = subjectLabel(subject);
        if (subjectAliasKeys.has(key)) return subjectAliasKeys.get(key);
        const orderedKey = key.split(' ').sort().join(' ');
        if (subjectAliasKeys.has(orderedKey))
            return subjectAliasKeys.get(orderedKey);
        if (subjectAliasesInitialized) return key;
        const aliases = {
            english: 'english language',
            'general mathematics': 'mathematics',
            maths: 'mathematics',
            'further maths': 'further mathematics',
            'further math': 'further mathematics',
            agriculture: 'agricultural science',
            'agriculture science': 'agricultural science',
            ict: 'computer studies',
            'ict/computer studies': 'computer studies',
            'information and communications technology': 'computer studies',
            'information & communications technology': 'computer studies',
            'lit. in english': 'literature in english',
            'lit in english': 'literature in english',
            'literature-in-english': 'literature in english',
            crs: 'christian religious studies',
            irs: 'islamic studies',
        };
        for (const [alias, canonical] of Object.entries(aliases)) {
            const label = subjectLabel(alias);
            subjectAliasKeys.set(label, subjectLabel(canonical));
            subjectAliasKeys.set(
                label.split(' ').sort().join(' '),
                subjectLabel(canonical),
            );
        }
        subjectAliasesInitialized = true;
        return (
            subjectAliasKeys.get(key) ?? subjectAliasKeys.get(orderedKey) ?? key
        );
    }

    /** Heuristic score, not a probability. matchSubject also checks competing candidates. */
    static subjectSimilarity(left, right) {
        const a = this.normalizeSubject(left).replace(/\s/g, '');
        const b = this.normalizeSubject(right).replace(/\s/g, '');
        if (!a || !b) return 0;
        if (a === b) return 1;
        if (
            (a.includes(b) || b.includes(a)) &&
            Math.abs(a.length - b.length) >= 3
        )
            return 0;
        const at = this.normalizeSubject(left).split(' ').sort();
        const bt = this.normalizeSubject(right).split(' ').sort();
        if (JSON.stringify(at) === JSON.stringify(bt)) return 1;
        if (at.length === bt.length && at.length >= 2 && prefixTokens(at, bt))
            return 0.96;
        if (Math.min(a.length, b.length) < 5 || /[^a-z0-9]/.test(a + b))
            return 0;
        return (
            Math.floor(
                (1 - editDistance(a, b) / Math.max(a.length, b.length)) *
                    10000 +
                    0.5,
            ) / 10000
        );
    }

    /**
     * Resolve IDs/codes, exact labels, normalized words, then spelling similarity.
     * Codes are not fuzzy matched. Threshold defaults to 0.88; a 0.06 margin separates candidates.
     * Returns matched/review/unmatched plus the portal ID/name, match type, score, and suggestions.
     * options.fields accepts existing catalogue column names or nested field paths.
     */
    static matchSubject(subject, catalogue, options = {}) {
        const config = matchingOptions(options);
        const entries = prepareCatalogue(catalogue, config, this);
        const isRecord = record(subject);
        const explicitId = isRecord
            ? pathValue(subject, config.fields.id)
            : subject;
        const identity =
            typeof explicitId === 'number' && Number.isInteger(explicitId)
                ? String(explicitId)
                : text(explicitId);
        const idMatches = entries.filter(
            (entry) => entry.id !== null && String(entry.id) === identity,
        );
        const result = (status, matches) => ({
            status,
            match_type:
                status === 'matched'
                    ? matches[0].match_type
                    : status === 'review'
                      ? 'ambiguous'
                      : 'none',
            score: matches[0]?.score ?? 0,
            subject_id: status === 'matched' ? matches[0].subject_id : null,
            subject_name: status === 'matched' ? matches[0].subject_name : null,
            subject_key: status === 'matched' ? matches[0].subject_key : null,
            candidates: matches.slice(0, 3).map((candidate) => {
                const value = { ...candidate };
                delete value.priority;
                return value;
            }),
        });
        const candidate = (entry, type, score, priority) => ({
            subject_id: entry.id,
            subject_name: entry.name,
            subject_key: entry.key,
            match_type: type,
            score,
            priority,
        });
        if (idMatches.length)
            return result('matched', [candidate(idMatches[0], 'id', 1, 5)]);
        if (
            (isRecord && explicitId !== undefined && explicitId !== null) ||
            typeof subject === 'number' ||
            /^\d+$/.test(identity)
        )
            return result('unmatched', []);
        const query = isRecord
            ? text(pathValue(subject, config.fields.name)) ||
              text(pathValue(subject, config.fields.code))
            : text(subject);
        if (!query) return result('unmatched', []);
        const matches = [];
        for (const entry of entries) {
            let best = candidate(entry, 'similarity', 0, 1);
            if (
                entry.code &&
                subjectLabel(query).replace(/\s/g, '') ===
                    subjectLabel(entry.code).replace(/\s/g, '')
            )
                best = candidate(entry, 'code', 1, 4);
            for (const label of [entry.name, ...entry.aliases]) {
                for (const cleanQuery of new Set([
                    query,
                    stripSubjectCode(query, entry.code),
                ])) {
                    let match;
                    if (subjectLabel(cleanQuery) === subjectLabel(label))
                        match = candidate(
                            entry,
                            label === entry.name ? 'exact' : 'alias',
                            1,
                            3,
                        );
                    else if (
                        this.normalizeSubject(cleanQuery).replace(/\s/g, '') ===
                            this.normalizeSubject(label).replace(/\s/g, '') ||
                        sameTokens(
                            this.normalizeSubject(cleanQuery),
                            this.normalizeSubject(label),
                        )
                    )
                        match = candidate(entry, 'normalized', 1, 2);
                    else
                        match = candidate(
                            entry,
                            'similarity',
                            this.subjectSimilarity(cleanQuery, label),
                            1,
                        );
                    if (
                        match.priority > best.priority ||
                        (match.priority === best.priority &&
                            match.score > best.score)
                    )
                        best = match;
                }
            }
            if (best.score >= 0.75) matches.push(best);
        }
        matches.sort(
            (a, b) =>
                b.priority - a.priority ||
                b.score - a.score ||
                compare(a.subject_key, b.subject_key),
        );
        if (!matches.length) return result('unmatched', []);
        const top = matches[0];
        const next = matches[1];
        const ambiguous =
            next &&
            next.priority === top.priority &&
            (top.priority > 1 ||
                top.score - next.score < config.margin - 0.000001);
        return result(
            ambiguous || (top.priority === 1 && top.score < config.threshold)
                ? 'review'
                : 'matched',
            matches,
        );
    }

    static gradeRank(grade) {
        const ranks = {
            A1: 9,
            B2: 8,
            B3: 7,
            C4: 6,
            C5: 5,
            C6: 4,
            D7: 3,
            P7: 3,
            E8: 2,
            P8: 2,
            F9: 1,
        };
        return ranks[text(grade).toUpperCase()] ?? 0;
    }

    /** Accept a direct result, API data envelope, or PayGo webhook result envelope. */
    static normalizeResult(payload) {
        const normalized = {
            state: 'invalid',
            reference: '',
            portal_ref: '',
            candidate_id: '',
            sittings: [],
        };
        if (typeof payload === 'string') {
            try {
                payload = JSON.parse(payload);
            } catch {
                return normalized;
            }
        }
        if (!record(payload)) return normalized;
        for (const key of ['reference', 'portal_ref', 'candidate_id'])
            normalized[key] = text(payload[key]);
        if ('success' in payload && typeof payload.success !== 'boolean')
            return normalized;
        const state = text(
            payload.result_status ?? payload.status,
        ).toLowerCase();
        const legacyPending =
            payload.success === false &&
            payload.error_code === 'RESULT_REFERENCE_INVALID' &&
            [
                'result is not available for this reference.',
                'result is not available for this portal attempt yet.',
            ].includes(text(payload.error).toLowerCase());
        if (
            legacyPending ||
            ['pending', 'processing', 'verifying', 'queued'].includes(state) ||
            payload.status === 202 ||
            ['RESULT_PENDING', 'RESULT_NOT_READY'].includes(payload.error_code)
        ) {
            return { ...normalized, state: 'pending' };
        }
        if (
            payload.success === false ||
            ['failed', 'error'].includes(state) ||
            (Number.isInteger(payload.status) && payload.status >= 400)
        )
            return { ...normalized, state: 'failed' };
        let data = record(payload.data)
            ? payload.data
            : record(payload.result)
              ? payload.result
              : payload;
        if (
            record(data.result) &&
            !Array.isArray(data.subjects) &&
            !Array.isArray(data.sittings)
        )
            data = data.result;
        const sittings = data.sittings ?? data.results ?? [data];
        if (!Array.isArray(sittings) || !sittings.length) return normalized;
        const seen = new Set();
        for (let index = 0; index < sittings.length; index++) {
            const sitting = sittings[index];
            if (!record(sitting)) return normalized;
            const sittingNo =
                sitting.sitting_no ?? payload.sitting ?? index + 1;
            if (
                !Number.isInteger(sittingNo) ||
                sittingNo < 1 ||
                seen.has(sittingNo)
            )
                return normalized;
            seen.add(sittingNo);
            const candidate = record(sitting.candidate)
                ? sitting.candidate
                : record(data.candidate)
                  ? data.candidate
                  : {};
            const candidateName = text(
                candidate.name ??
                    candidate.full_name ??
                    candidate.candidate_name ??
                    sitting.candidate_name,
            );
            const rows =
                sitting.subjects ??
                (record(sitting.result) ? sitting.result.subjects : undefined);
            if (!Array.isArray(rows) || !rows.length) return normalized;
            const subjects = [];
            for (const row of rows) {
                if (
                    !record(row) ||
                    !this.normalizeSubject(row.subject) ||
                    !text(row.grade)
                )
                    return normalized;
                subjects.push({
                    subject: text(row.subject),
                    normalized_subject: this.normalizeSubject(row.subject),
                    grade: text(row.grade).toUpperCase(),
                    sitting: sittingNo,
                });
            }
            normalized.sittings.push({
                sitting: sittingNo,
                candidate_name: candidateName,
                subjects,
            });
        }
        normalized.state = 'ready';
        return normalized;
    }

    static bestSubjects(payload, portal = {}) {
        const normalized = this.normalizeResult(payload);
        if (normalized.state !== 'ready') return [];
        const best = new Map();
        const catalogue = portalCatalogue(portal, this);
        for (const sitting of normalized.sittings) {
            for (const original of sitting.subjects) {
                const match = catalogue.length
                    ? this.matchSubject(
                          original.subject,
                          catalogue,
                          portal.subject_matching ?? {},
                      )
                    : null;
                const row = match
                    ? {
                          ...original,
                          subject_key:
                              match.subject_key ??
                              `unmapped:${original.normalized_subject}`,
                          portal_subject_id: match.subject_id,
                          portal_subject_name: match.subject_name,
                          match_type: match.match_type,
                          match_score: match.score,
                          match_status: match.status,
                          match_candidates: match.candidates,
                      }
                    : original;
                const key = row.subject_key ?? row.normalized_subject;
                const previous = best.get(key);
                if (
                    !previous ||
                    this.gradeRank(row.grade) >
                        this.gradeRank(previous.grade) ||
                    (this.gradeRank(row.grade) ===
                        this.gradeRank(previous.grade) &&
                        row.sitting < previous.sitting)
                )
                    best.set(key, row);
            }
        }
        return [...best.values()].sort((a, b) =>
            compare(
                a.subject_key ?? a.normalized_subject,
                b.subject_key ?? b.normalized_subject,
            ),
        );
    }

    /** Duplicate use must be determined by the portal's trusted server. */
    static checkOwnership(portal, payload) {
        const normalized = this.normalizeResult(payload);
        const expected = text(portal.student?.name);
        const names =
            normalized.state === 'ready'
                ? normalized.sittings.map((sitting) => sitting.candidate_name)
                : [];
        let code = 'OWNERSHIP_MATCHED';
        let status = 'matched';
        if (normalized.state !== 'ready') {
            code = 'OWNERSHIP_NOT_CHECKED';
            status = 'not_checked';
        } else if (portal.result_used_by_another_student === true) {
            code = 'DUPLICATE_RESULT';
            status = 'mismatch';
        } else if (portal.ownership_check_enabled === false) {
            code = 'OWNERSHIP_SKIPPED';
            status = 'skipped';
        } else {
            const expectedTokens = nameTokens(expected);
            let review = !expectedTokens.length;
            let mismatch = false;
            for (const name of names) {
                const tokens = nameTokens(name);
                if (!tokens.length) {
                    review = true;
                    continue;
                }
                if (JSON.stringify(tokens) === JSON.stringify(expectedTokens))
                    continue;
                if (
                    !expectedTokens.length ||
                    tokens.some((token) => expectedTokens.includes(token))
                )
                    review = true;
                else mismatch = true;
            }
            if (mismatch) {
                code = 'OWNERSHIP_MISMATCH';
                status = 'mismatch';
            } else if (review) {
                code = 'OWNERSHIP_REVIEW';
                status = 'review';
            }
        }
        return {
            status,
            code,
            message: this.messageFor(code),
            expected_name: expected,
            candidate_names: names,
        };
    }

    /** Each subject can fill only one slot. Alternatives are matched across all slots. */
    static checkRequirements(portal, payload) {
        const rules = validateRules(portal, this);
        const normalized = this.normalizeResult(payload);
        if (normalized.state !== 'ready') {
            return {
                passed: false,
                complete: false,
                rows: [],
                missing_subjects: [],
                low_grades: [],
                review_subjects: [],
                credits_found: 0,
                minimum_credits: rules.minimum_credits,
                sittings_used: 0,
                max_sittings: rules.max_sittings,
            };
        }
        const best = this.bestSubjects(payload, portal);
        const candidates = rules.requirements.map((rule) =>
            best
                .filter(
                    (row) =>
                        row.match_status === 'matched' &&
                        rule.subjects.includes(row.subject_key),
                )
                .sort(
                    (a, b) =>
                        this.gradeRank(b.grade) - this.gradeRank(a.grade) ||
                        a.sitting - b.sitting ||
                        compare(a.normalized_subject, b.normalized_subject),
                ),
        );
        const assigned = new Map();
        const owners = new Map();
        const assign = (slot, seen) => {
            for (const row of candidates[slot]) {
                if (
                    this.gradeRank(row.grade) <
                        this.gradeRank(
                            rules.requirements[slot].minimum_grade,
                        ) ||
                    seen.has(row.subject_key)
                )
                    continue;
                seen.add(row.subject_key);
                const owner = owners.get(row.subject_key);
                if (owner === undefined || assign(owner, seen)) {
                    owners.set(row.subject_key, slot);
                    assigned.set(slot, row);
                    return true;
                }
            }
            return false;
        };
        const order = rules.requirements
            .map((_, index) => index)
            .sort(
                (a, b) =>
                    Number(rules.requirements[b].required) -
                        Number(rules.requirements[a].required) || a - b,
            );
        for (const required of [true, false]) {
            const slots = order.filter(
                (slot) => rules.requirements[slot].required === required,
            );
            for (const slot of slots) assign(slot, new Set());
            for (const slot of slots) {
                if (assigned.has(slot)) continue;
                const row = candidates[slot].find(
                    (candidate) => !owners.has(candidate.subject_key),
                );
                if (row) {
                    assigned.set(slot, row);
                    owners.set(row.subject_key, slot);
                }
            }
        }
        const rows = rules.requirements.map((rule, index) => {
            const row = assigned.get(index);
            return {
                key: rule.key,
                required: rule.required,
                minimum_grade: rule.minimum_grade,
                subject: row?.subject ?? null,
                grade: row?.grade ?? null,
                sitting: row?.sitting ?? null,
                portal_subject_id: row?.portal_subject_id ?? null,
                portal_subject_name: row?.portal_subject_name ?? null,
                match_type: row?.match_type ?? null,
                match_score: row?.match_score ?? null,
                status: !row
                    ? 'missing'
                    : this.gradeRank(row.grade) >=
                        this.gradeRank(rule.minimum_grade)
                      ? 'met'
                      : 'grade_below_requirement',
            };
        });
        const missing = rows
            .filter((row) => row.required && row.status === 'missing')
            .map((row) => row.key);
        const low = rows
            .filter(
                (row) =>
                    row.required && row.status === 'grade_below_requirement',
            )
            .map((row) => row.key);
        const credits = best.filter(
            (row) =>
                (portal.subject_catalog === undefined ||
                    row.match_status === 'matched') &&
                this.gradeRank(row.grade) >=
                    this.gradeRank(rules.minimum_grade),
        ).length;
        const review = best
            .filter(
                (row) =>
                    row.match_status === 'review' ||
                    (portal.subject_catalog !== undefined &&
                        row.match_status === 'unmatched' &&
                        credits < rules.minimum_credits),
            )
            .map((row) => ({
                subject: row.subject,
                candidates: row.match_candidates,
            }));
        const ready = normalized.state === 'ready';
        const sittingCount = ready ? normalized.sittings.length : 0;
        return {
            passed:
                ready &&
                !missing.length &&
                !low.length &&
                !review.length &&
                credits >= rules.minimum_credits &&
                sittingCount <= rules.max_sittings,
            complete: ready && !missing.length,
            rows,
            missing_subjects: missing,
            low_grades: low,
            review_subjects: review,
            credits_found: credits,
            minimum_credits: rules.minimum_credits,
            sittings_used: sittingCount,
            max_sittings: rules.max_sittings,
        };
    }

    static evaluate(portal, payload, adapter = {}) {
        let adapterError;
        try {
            if (
                record(adapter) &&
                'result_adapter' in adapter &&
                typeof adapter.result_adapter !== 'function'
            )
                throw new TypeError('result_adapter must be callable');
            if (record(adapter) && typeof adapter.result_adapter === 'function')
                payload = adapter.result_adapter(payload);
            portal = this.adaptPortal(portal, adapter);
        } catch (error) {
            if (!(error instanceof TypeError)) throw error;
            adapterError = error;
        }
        const normalized = this.normalizeResult(payload);
        const ownership = this.checkOwnership(
            record(portal) ? portal : {},
            payload,
        );
        let requirements = null;
        let code;
        const configurationErrors = [];
        try {
            if (adapterError) throw adapterError;
            requirements = this.checkRequirements(portal, payload);
        } catch (error) {
            if (!(error instanceof TypeError)) throw error;
            code = 'CONFIGURATION_ERROR';
            configurationErrors.push(error.message);
        }
        if (!code) {
            if (normalized.state !== 'ready')
                code = {
                    pending: 'RESULT_PENDING',
                    failed: 'RESULT_FAILED',
                    invalid: 'INVALID_RESULT',
                }[normalized.state];
            else if (
                ['reference', 'portal_ref', 'candidate_id'].some(
                    (key) =>
                        text(portal[key]) &&
                        normalized[key] &&
                        text(portal[key]) !== normalized[key],
                )
            )
                code = 'RESULT_CONTEXT_MISMATCH';
            else if (!['matched', 'skipped'].includes(ownership.status))
                code = ownership.code;
            else if (requirements.sittings_used > requirements.max_sittings)
                code = 'SITTING_LIMIT_EXCEEDED';
            else if (requirements.review_subjects.length)
                code = 'SUBJECT_MATCH_REVIEW';
            else if (requirements.missing_subjects.length)
                code = 'SUBJECTS_MISSING';
            else if (requirements.low_grades.length)
                code = 'GRADES_BELOW_REQUIREMENT';
            else if (requirements.credits_found < requirements.minimum_credits)
                code = 'INSUFFICIENT_CREDITS';
            else code = 'REQUIREMENTS_MET';
        }
        const autofill =
            [
                'REQUIREMENTS_MET',
                'SUBJECTS_MISSING',
                'GRADES_BELOW_REQUIREMENT',
                'INSUFFICIENT_CREDITS',
            ].includes(code) &&
            !requirements.rows.some(
                (row) => row.grade !== null && !this.gradeRank(row.grade),
            );
        const status =
            code === 'REQUIREMENTS_MET'
                ? 'ready'
                : code === 'RESULT_PENDING'
                  ? 'pending'
                  : code === 'RESULT_FAILED'
                    ? 'failed'
                    : [
                            'CONFIGURATION_ERROR',
                            'INVALID_RESULT',
                            'OWNERSHIP_REVIEW',
                            'SUBJECT_MATCH_REVIEW',
                        ].includes(code)
                      ? 'review'
                      : 'blocked';
        const next =
            code === 'REQUIREMENTS_MET'
                ? 'continue_portal_checks'
                : code === 'RESULT_PENDING'
                  ? 'retry_result_pull'
                  : code === 'RESULT_FAILED'
                    ? 'review_result_error'
                    : code === 'CONFIGURATION_ERROR'
                      ? 'check_portal_configuration'
                      : code === 'SUBJECTS_MISSING' &&
                          requirements.sittings_used < requirements.max_sittings
                        ? 'review_or_verify_another_sitting'
                        : 'contact_school';
        return {
            version: this.version,
            code,
            status,
            message: this.messageFor(code),
            next_action: next,
            can_autofill: autofill,
            can_continue: code === 'REQUIREMENTS_MET',
            reference: normalized.reference,
            ownership,
            requirements,
            configuration_errors: configurationErrors,
        };
    }

    static messageFor(code) {
        const messages = {
            REQUIREMENTS_MET:
                'The supplied result meets the configured portal checks. Continue with the remaining portal checks.',
            RESULT_PENDING:
                'The result is still being prepared. Retry the result pull using the existing reference; another payment is not required for this status.',
            RESULT_FAILED:
                'The result could not be retrieved. Review the provider error and check the exam details before retrying.',
            INVALID_RESULT:
                'A usable verified result was not supplied. Retrieve the result before checking ownership or requirements.',
            RESULT_CONTEXT_MISMATCH:
                'The result belongs to a different portal request. Auto-fill is blocked; check the student and reference.',
            DUPLICATE_RESULT:
                'This result is already linked to another student. Auto-fill is blocked; contact the school to resolve the duplicate.',
            OWNERSHIP_MISMATCH:
                'The verified result name does not match the student profile. Auto-fill is blocked; check the exam details or contact the school.',
            OWNERSHIP_REVIEW:
                'The result name is missing or only partially matches the student profile. School ownership review is required before auto-fill.',
            OWNERSHIP_MATCHED:
                'The candidate name matches the student profile on every supplied sitting.',
            OWNERSHIP_SKIPPED:
                'Name comparison was disabled by the portal. Ownership has not been established by this library.',
            OWNERSHIP_NOT_CHECKED:
                'Ownership cannot be checked until a usable result is supplied.',
            SITTING_LIMIT_EXCEEDED:
                'The result contains more sittings than the programme allows. Ask the school to review the permitted sittings.',
            SUBJECTS_MISSING:
                'The result was verified, but one or more required subjects are missing. Review the listed subjects and any permitted additional sitting.',
            SUBJECT_MATCH_REVIEW:
                'A returned subject could not be mapped confidently to the portal catalogue. Review the suggested matches before applying grades.',
            GRADES_BELOW_REQUIREMENT:
                'Required subjects are present, but one or more grades are below the configured minimum or unrecognized. Review the listed grades.',
            INSUFFICIENT_CREDITS:
                'The result does not contain enough distinct subjects at the configured credit grade. Review the programme requirements.',
            CONFIGURATION_ERROR:
                'The portal rules are incomplete or invalid. Supply a student profile and explicit subject requirements before continuing.',
        };
        return Object.hasOwn(messages, code)
            ? messages[code]
            : 'The result needs school review before continuing.';
    }
}

const subjectAliasKeys = new Map();
let subjectAliasesInitialized = false;
const record = (value) =>
    value !== null && typeof value === 'object' && !Array.isArray(value);
const text = (value) => (typeof value === 'string' ? value.trim() : '');
const compare = (a, b) => (a < b ? -1 : a > b ? 1 : 0);
const nameTokens = (name) =>
    [
        ...new Set(
            (
                text(name)
                    .replace(/[A-Z]/g, (letter) => letter.toLowerCase())
                    .match(/[\p{L}\p{N}]+/gu) ?? []
            ).filter(
                (word) =>
                    !['mr', 'mrs', 'miss', 'ms', 'dr', 'chief'].includes(word),
            ),
        ),
    ].sort();

function subjectLabel(value) {
    let words = text(value)
        .replace(/[A-Z]/g, (letter) => letter.toLowerCase())
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim()
        .split(/\s+/);
    if (words.every((word) => word.length === 1)) return words.join('');
    const expansions = {
        math: 'mathematics',
        maths: 'mathematics',
        lang: 'language',
        sci: 'science',
        lit: 'literature',
        agric: 'agricultural',
        rel: 'religious',
        stud: 'studies',
        std: 'studies',
        acct: 'accounting',
        govt: 'government',
    };
    words = words
        .filter((word) => !['and', 'of', 'the', 'in'].includes(word))
        .map((word) =>
            Object.hasOwn(expansions, word) ? expansions[word] : word,
        );
    return words.join(' ');
}

const copyValue = (value) =>
    Array.isArray(value)
        ? value.map(copyValue)
        : record(value)
          ? Object.fromEntries(
                Object.entries(value).map(([key, entry]) => [
                    key,
                    copyValue(entry),
                ]),
            )
          : value;
function pathValue(source, path) {
    if (typeof path !== 'string' || !path)
        throw new TypeError('Mapping paths must be non-empty strings');
    if (path === '$') return source;
    if (
        source !== null &&
        typeof source === 'object' &&
        Object.hasOwn(source, path)
    )
        return source[path];
    let value = source;
    for (const key of path.split('.')) {
        if (['__proto__', 'prototype', 'constructor'].includes(key))
            throw new TypeError('Unsafe mapping path');
        if (
            value === null ||
            typeof value !== 'object' ||
            !Object.hasOwn(value, key)
        )
            return undefined;
        value = value[key];
    }
    return value;
}
function setPath(target, path, value) {
    const keys = path.split('.');
    let node = target;
    for (const key of keys.slice(0, -1)) {
        if (['__proto__', 'prototype', 'constructor'].includes(key))
            throw new TypeError('Unsafe mapping path');
        if (!record(node[key])) node[key] = {};
        node = node[key];
    }
    const key = keys.at(-1);
    if (['__proto__', 'prototype', 'constructor'].includes(key))
        throw new TypeError('Unsafe mapping path');
    node[key] = value;
}
function databaseBoolean(value) {
    if ([true, false].includes(value)) return value;
    const key = String(value).trim().toLowerCase();
    if (['1', 'true', 'yes', 'y'].includes(key)) return true;
    if (['0', 'false', 'no', 'n'].includes(key)) return false;
    return value;
}
function mappedGrade(value, mapping) {
    return Object.hasOwn(mapping, String(value))
        ? mapping[String(value)]
        : value;
}

function sameTokens(a, b) {
    return (
        a &&
        b &&
        a.split(' ').sort().join(' ') === b.split(' ').sort().join(' ')
    );
}
function prefixTokens(a, b) {
    return a.every(
        (word, index) =>
            word === b[index] ||
            (Math.min(word.length, b[index].length) >= 3 &&
                (word.startsWith(b[index]) || b[index].startsWith(word))),
    );
}
function editDistance(a, b) {
    let previous = Array.from({ length: b.length + 1 }, (_, index) => index);
    for (let i = 1; i <= a.length; i++) {
        const current = [i];
        for (let j = 1; j <= b.length; j++)
            current[j] = Math.min(
                current[j - 1] + 1,
                previous[j] + 1,
                previous[j - 1] + Number(a[i - 1] !== b[j - 1]),
            );
        previous = current;
    }
    return previous[b.length];
}
function matchingOptions(options) {
    if (!record(options))
        throw new TypeError('Invalid subject matching options');
    const threshold = options.threshold ?? 0.88;
    const margin = options.margin ?? 0.06;
    if (
        typeof threshold !== 'number' ||
        !Number.isFinite(threshold) ||
        threshold < 0.85 ||
        threshold > 1 ||
        typeof margin !== 'number' ||
        !Number.isFinite(margin) ||
        margin < 0.01 ||
        margin > 0.5
    )
        throw new TypeError('Invalid subject matching options');
    const fields = {
        id: 'id',
        name: 'name',
        code: 'code',
        aliases: 'aliases',
        ...(options.fields ?? {}),
    };
    if (options.fields !== undefined && !record(options.fields))
        throw new TypeError('Invalid subject field mapping');
    if (
        Object.entries(fields).some(
            ([key, value]) =>
                !['id', 'name', 'code', 'aliases'].includes(key) ||
                !text(value),
        )
    )
        throw new TypeError('Invalid subject field mapping');
    return { threshold, margin, fields };
}
function prepareCatalogue(catalogue, config, evaluator) {
    if (!Array.isArray(catalogue))
        throw new TypeError('Invalid subject catalogue');
    const entries = new Map();
    for (const value of catalogue) {
        const isRecord = record(value);
        const id = isRecord
            ? (pathValue(value, config.fields.id) ?? null)
            : null;
        const name = isRecord
            ? text(pathValue(value, config.fields.name))
            : text(value);
        const code = isRecord
            ? (pathValue(value, config.fields.code) ?? '')
            : '';
        const aliases = isRecord
            ? (pathValue(value, config.fields.aliases) ?? [])
            : [];
        if (!evaluator.normalizeSubject(name))
            throw new TypeError(
                `Subject catalogue name field "${config.fields.name}" is missing or empty. Check subject_fields.`,
            );
        if (
            (id !== null &&
                !(
                    (typeof id === 'string' && text(id)) ||
                    (typeof id === 'number' && Number.isInteger(id))
                )) ||
            typeof code !== 'string' ||
            !Array.isArray(aliases) ||
            aliases.some((alias) => !evaluator.normalizeSubject(alias))
        )
            throw new TypeError('Invalid subject catalogue');
        const key =
            id !== null
                ? `id:${id}`
                : `name:${evaluator.normalizeSubject(name).split(' ').sort().join('')}`;
        if (entries.has(key)) {
            if (id !== null) throw new TypeError('Duplicate subject ID');
            entries.get(key).aliases.push(name, ...aliases);
        } else entries.set(key, { id, key, name, code, aliases: [...aliases] });
    }
    return [...entries.values()];
}
function stripSubjectCode(query, code) {
    if (!code) return query;
    const tokens = subjectLabel(query).split(' ');
    const key = subjectLabel(code).replace(/\s/g, '');
    if (tokens.length > 1 && tokens[0] === key) tokens.shift();
    if (tokens.length > 1 && tokens.at(-1) === key) tokens.pop();
    return tokens.join(' ');
}
function portalCatalogue(portal, evaluator) {
    if (portal.subject_catalog !== undefined) return portal.subject_catalog;
    const labels = (portal.requirements ?? []).flatMap(
        (rule) => rule.subjects ?? [],
    );
    if (labels.some((label) => typeof label !== 'string'))
        throw new TypeError('Subject IDs require a catalogue');
    return labels.filter((label) => evaluator.normalizeSubject(label));
}

function validateRules(portal, evaluator) {
    const invalid = (reason = 'Invalid portal rules') => {
        throw new TypeError(reason);
    };
    if (
        !record(portal) ||
        !record(portal.student) ||
        typeof portal.student.name !== 'string'
    )
        invalid('Map student.name to a string in your portal record.');
    for (const key of [
        'ownership_check_enabled',
        'result_used_by_another_student',
    ])
        if (key in portal && typeof portal[key] !== 'boolean') invalid();
    if (portal.ownership_check_enabled !== false && !text(portal.student.name))
        invalid('student.name is empty. Check the adapter name field paths.');
    for (const key of ['reference', 'portal_ref', 'candidate_id'])
        if (key in portal && !text(portal[key])) invalid();
    const minimum = portal.minimum_grade ?? 'C6';
    const credits = portal.minimum_credits ?? 0;
    const maxSittings = portal.max_sittings ?? 2;
    if (!evaluator.gradeRank(minimum))
        invalid(
            'minimum_grade must be a known grade; map database grade codes with grade_map.',
        );
    if (!Number.isInteger(credits) || credits < 0)
        invalid('minimum_credits must be a non-negative integer.');
    if (!Number.isInteger(maxSittings) || maxSittings < 1 || maxSittings > 2)
        invalid('max_sittings must be 1 or 2.');
    if (!Array.isArray(portal.requirements) || !portal.requirements.length)
        invalid(
            'Map requirements to a non-empty list of subject IDs, names, or requirement rows.',
        );
    const catalogue = portalCatalogue(portal, evaluator);
    const options = portal.subject_matching ?? {};
    prepareCatalogue(catalogue, matchingOptions(options), evaluator);
    if (!catalogue.length) invalid();
    const keys = new Set();
    let required = 0;
    const requirements = portal.requirements.map((rule) => {
        if (
            !record(rule) ||
            !text(rule.key) ||
            keys.has(rule.key) ||
            !Array.isArray(rule.subjects) ||
            !rule.subjects.length ||
            rule.subjects.some(
                (subject) =>
                    !(
                        (typeof subject === 'number' &&
                            Number.isInteger(subject)) ||
                        text(subject)
                    ),
            )
        )
            invalid(
                'Each requirement needs a unique key and a non-empty subjects list.',
            );
        if ('required' in rule && typeof rule.required !== 'boolean') invalid();
        const min = rule.minimum_grade ?? minimum;
        if (!evaluator.gradeRank(min))
            invalid(
                'Requirement minimum_grade must be a known grade; use grade_map for numeric portal codes.',
            );
        keys.add(rule.key);
        if (rule.required !== false) required++;
        const subjects = rule.subjects.map((subject) =>
            evaluator.matchSubject(subject, catalogue, options),
        );
        if (subjects.some((match) => match.status !== 'matched'))
            invalid(
                'A requirement subject could not be resolved uniquely in subject_catalog. Check its ID, code, or aliases.',
            );
        return {
            key: rule.key,
            subjects: subjects.map((match) => match.subject_key),
            minimum_grade: text(min).toUpperCase(),
            required: rule.required !== false,
        };
    });
    if (!required) invalid();
    return {
        requirements,
        minimum_grade: text(minimum).toUpperCase(),
        minimum_credits: credits,
        max_sittings: maxSittings,
    };
}

export default ResultEvaluator;
