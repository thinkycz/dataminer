function bounded(value, fallback, maximum) {
    return Number.isInteger(value) && value > 0
        ? Math.min(value, maximum)
        : fallback;
}

function validSelector(value) {
    if (typeof value !== 'string' || value.length < 1 || value.length > 500) {
        throw new Error('A bounded CSS selector is required');
    }
    return value;
}

export function validateWebsiteDefinition(definition) {
    if (
        !definition ||
        definition.schema_version !== 1 ||
        definition.source_type !== 'website'
    ) {
        throw new Error('Website definition version 1 required');
    }
    const url = new URL(definition.url);
    if (
        !['http:', 'https:'].includes(url.protocol) ||
        url.username ||
        url.password
    ) {
        throw new Error('Only HTTP(S) website URLs are supported');
    }
    validSelector(definition.website?.record_selector);
    if (!Array.isArray(definition.fields) || definition.fields.length === 0) {
        throw new Error('Field selectors required');
    }
    for (const field of [
        ...definition.fields,
        ...(definition.website?.detail_fields ?? []),
    ]) {
        const name = field.name;
        if (!/^[A-Za-z][\w.-]{0,119}$/.test(name))
            throw new Error('Invalid field name');
        validSelector(field.path);
    }
    if (definition.website.detail_url_selector)
        validSelector(definition.website.detail_url_selector);
    if (definition.website.signed_in_selector)
        validSelector(definition.website.signed_in_selector);
    if (
        definition.website.detail_fields?.length &&
        !definition.website.detail_url_selector
    )
        throw new Error('Detail link selector required');
    if (definition.pagination) {
        if (
            !['none', 'next_page', 'load_more', 'scroll'].includes(
                definition.pagination.mode,
            )
        ) {
            throw new Error('Invalid pagination type');
        }
        if (['next_page', 'load_more'].includes(definition.pagination.mode)) {
            validSelector(definition.pagination.next_path);
        }
    }
    return definition;
}

async function readItems(page, definition) {
    return page.evaluate(({ website, fields }) => {
        return [...document.querySelectorAll(website.record_selector)]
            .slice(0, 1000)
            .map((item) => {
                const row = {};
                for (const field of fields) {
                    const element = item.querySelector(field.path);
                    row[field.name] =
                        field.type === 'url'
                            ? (element?.href ??
                              element?.getAttribute('href') ??
                              null)
                            : (element?.textContent?.trim().slice(0, 10_000) ??
                              null);
                }
                if (website.detail_url_selector) {
                    const anchor = item.querySelector(
                        website.detail_url_selector,
                    );
                    row.__detailUrl = anchor?.href ?? null;
                }
                return row;
            });
    }, definition);
}

async function readDetail(page, fields) {
    return page.evaluate((definitions) => {
        const row = {};
        for (const field of definitions) {
            const element = document.querySelector(field.path);
            row[field.name] =
                field.type === 'url'
                    ? (element?.href ?? element?.getAttribute('href') ?? null)
                    : (element?.textContent?.trim().slice(0, 10_000) ?? null);
        }
        return row;
    }, fields);
}

async function authExpired(page, selector, response) {
    if ([401, 403].includes(response?.status())) return true;
    return selector ? (await page.locator(selector).count()) === 0 : false;
}

export async function extractWebsite(page, rawDefinition, rawLimits = {}) {
    const definition = validateWebsiteDefinition(rawDefinition);
    const limits = {
        rows: bounded(rawLimits.rows ?? definition.limits?.rows, 100, 1000),
        pages: Math.min(
            bounded(rawLimits.pages ?? definition.limits?.pages, 5, 50),
            bounded(definition.pagination?.max_actions, 49, 49) + 1,
        ),
        seconds: bounded(
            rawLimits.seconds ?? definition.limits?.seconds,
            60,
            300,
        ),
    };
    const deadline = Date.now() + limits.seconds * 1000;
    const rows = [];
    const seen = new Map();
    const diagnostics = new Set();
    const visited = new Set();
    let pages = 0;
    let partial = false;
    let reason = null;
    let naturalEnd = false;
    let response = await page.goto(definition.url, {
        waitUntil: 'domcontentloaded',
    });
    while (pages < limits.pages && Date.now() < deadline) {
        pages++;
        if (
            await authExpired(
                page,
                definition.website.signed_in_selector,
                response,
            )
        ) {
            partial = true;
            reason = 'auth_expired';
            break;
        }
        if (response?.status() >= 400) {
            partial = true;
            reason = 'source_http_error';
            break;
        }
        await page
            .locator(definition.website.record_selector)
            .first()
            .waitFor({
                state: 'attached',
                timeout: 5_000,
            })
            .catch(() => {});
        const current = page.url();
        if (
            visited.has(current) &&
            definition.pagination?.mode === 'next_page'
        ) {
            partial = true;
            reason = 'repeated_page';
            break;
        }
        visited.add(current);
        let added = 0;
        const currentFingerprints = new Map();
        for (const item of await readItems(page, definition)) {
            const detailUrl = item.__detailUrl;
            delete item.__detailUrl;
            let detailAllowed = false;
            try {
                detailAllowed =
                    !!detailUrl &&
                    /^https?:$/.test(new URL(detailUrl).protocol);
            } catch {}
            if (definition.website.detail_fields?.length && !detailAllowed) {
                partial = true;
                diagnostics.add('detail_missing');
            }
            if (definition.website.detail_fields?.length && detailAllowed) {
                const detailPage = await page.context().newPage();
                try {
                    const detailResponse = await detailPage.goto(detailUrl, {
                        waitUntil: 'domcontentloaded',
                        timeout: 15_000,
                    });
                    if (
                        await authExpired(
                            detailPage,
                            definition.website.signed_in_selector,
                            detailResponse,
                        )
                    ) {
                        partial = true;
                        reason = 'auth_expired';
                        break;
                    }
                    if (detailResponse?.status() >= 400) {
                        partial = true;
                        diagnostics.add('detail_failed');
                    } else {
                        Object.assign(
                            item,
                            await readDetail(
                                detailPage,
                                definition.website.detail_fields,
                            ),
                        );
                    }
                } catch {
                    partial = true;
                    diagnostics.add('detail_failed');
                } finally {
                    await detailPage.close();
                }
            }
            if (reason === 'auth_expired') break;
            const fingerprint = JSON.stringify(item);
            const occurrence = (currentFingerprints.get(fingerprint) ?? 0) + 1;
            currentFingerprints.set(fingerprint, occurrence);
            if (
                ['load_more', 'scroll'].includes(definition.pagination?.mode) &&
                pages > 1 &&
                occurrence <= (seen.get(fingerprint) ?? 0)
            ) {
                continue;
            }
            for (const field of [
                ...definition.fields,
                ...(definition.website.detail_fields ?? []),
            ]) {
                if (
                    field.required &&
                    (item[field.name] === null ||
                        item[field.name] === undefined ||
                        item[field.name] === '')
                ) {
                    partial = true;
                    diagnostics.add(`required_field_missing:${field.name}`);
                }
            }
            rows.push(item);
            added++;
            if (rows.length >= limits.rows) break;
        }
        for (const [fingerprint, count] of currentFingerprints)
            seen.set(fingerprint, Math.max(seen.get(fingerprint) ?? 0, count));
        if (reason === 'auth_expired') break;
        if (rows.length >= limits.rows) {
            partial = true;
            reason = 'row_limit';
            break;
        }
        if (pages > 1 && added === 0) {
            partial = true;
            reason = 'no_progress';
            break;
        }
        if (!definition.pagination || definition.pagination.mode === 'none') {
            naturalEnd = true;
            break;
        }
        const { mode, next_path: selector } = definition.pagination;
        if (mode === 'next_page') {
            if (!(await page.locator(selector).count())) {
                naturalEnd = true;
                break;
            }
            const next = await page
                .locator(selector)
                .first()
                .getAttribute('href')
                .catch(() => null);
            if (!next) {
                naturalEnd = true;
                break;
            }
            const nextUrl = new URL(next, page.url()).href;
            if (visited.has(nextUrl)) {
                partial = true;
                reason = 'repeated_page';
                break;
            }
            response = await page.goto(nextUrl, {
                waitUntil: 'domcontentloaded',
            });
        } else if (mode === 'load_more') {
            if (!(await page.locator(selector).count())) {
                naturalEnd = true;
                break;
            }
            await page.locator(selector).first().click();
            await page.waitForTimeout(500);
        } else {
            await page.evaluate(() =>
                window.scrollTo(0, document.body.scrollHeight),
            );
            await page.waitForTimeout(500);
        }
    }
    if (
        !reason &&
        !naturalEnd &&
        (pages >= limits.pages || Date.now() >= deadline)
    ) {
        partial = true;
        reason = pages >= limits.pages ? 'page_limit' : 'time_limit';
    }
    if (rows.length === 0 && definition.validation?.allow_empty !== true) {
        partial = true;
        reason = reason ?? 'no_rows';
    }
    return {
        rows,
        pages,
        complete: !partial,
        diagnostics: [...(reason ? [reason] : []), ...diagnostics],
    };
}
