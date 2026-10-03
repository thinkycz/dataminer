/** Parse a bounded CSV sample, including quoted delimiters and line breaks. */
export function sampleCsvRows(sample: string, delimiter = ','): string[][] {
    if (delimiter.length !== 1) return [];
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let quoted = false;
    for (let i = 0; i < Math.min(sample.length, 50_000); i++) {
        const char = sample[i];
        if (char === '"') {
            if (quoted && sample[i + 1] === '"') {
                cell += '"';
                i++;
            } else quoted = !quoted;
        } else if (char === delimiter && !quoted) {
            row.push(cell);
            cell = '';
        } else if ((char === '\n' || char === '\r') && !quoted) {
            row.push(cell);
            rows.push(row);
            row = [];
            cell = '';
            if (char === '\r' && sample[i + 1] === '\n') i++;
            if (rows.length >= 6) return rows;
        } else cell += char;
    }
    if (cell || row.length) rows.push([...row, cell]);
    return rows;
}
export function samplePath(value: unknown, path: string): unknown {
    let result = value;
    for (const key of path
        .replace(/^\$\.?/, '')
        .replace(/\[(\d+)\]/g, '.$1')
        .split('.')
        .filter(Boolean)) {
        if (!result || typeof result !== 'object') return undefined;
        result = (result as Record<string, unknown>)[key];
    }
    return result;
}

/** Use a readable leaf label, retaining context when two paths share a name. */
export function sampleColumnLabel(path: string, paths: string[]): string {
    const leaf = (value: string): string =>
        value
            .split(/[./]/)
            .filter(Boolean)
            .at(-1)
            ?.replace(/^@/, '')
            .split(':')
            .at(-1) ?? value;
    const label = leaf(path);
    return paths.filter((candidate) => leaf(candidate) === label).length > 1
        ? path.replace(/^\.\//, '').replace(/[./]/g, ' › ')
        : label;
}
