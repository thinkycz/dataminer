import { computed, type Ref } from 'vue';
import type { RecipeDefinitionDraft } from '@/lib/recipe-definition';
import { sampleCsvRows } from '@/lib/source-sample';

/** Discover records and columns without changing the browser selection session. */
export function useSourceSample(
    form: RecipeDefinitionDraft,
    sampleData: Ref<unknown>,
) {
    function parseXmlSample(): Document | null {
        if (
            form.source_type !== 'xml' ||
            typeof sampleData.value !== 'string' ||
            /<!\s*(?:DOCTYPE|ENTITY)/i.test(sampleData.value)
        )
            return null;
        const document = new DOMParser().parseFromString(
            sampleData.value,
            'application/xml',
        );
        return document.querySelector('parsererror') ? null : document;
    }
    function discoverXmlNamespaces(document: Document): void {
        const namespaces = {
            ...(form.xml?.namespaces as Record<string, string>),
        };
        for (const element of document.getElementsByTagName('*')) {
            for (const node of [element, ...element.attributes]) {
                const uri = node.namespaceURI;
                if (
                    !uri ||
                    uri === 'http://www.w3.org/2000/xmlns/' ||
                    Object.values(namespaces).includes(uri)
                )
                    continue;
                const base = node.prefix || 'ns';
                let prefix = base;
                for (let suffix = 2; namespaces[prefix]; suffix++)
                    prefix = `${base}${suffix}`;
                namespaces[prefix] = uri;
            }
        }
        form.xml = { ...form.xml, namespaces };
    }
    function xmlNodeName(node: Element | Attr): string {
        const prefix = Object.entries(form.xml?.namespaces ?? {}).find(
            ([, uri]) => uri === node.namespaceURI,
        )?.[0];
        return prefix ? `${prefix}:${node.localName}` : node.localName;
    }
    const sampleRecordsPaths = computed(() => {
        if (form.source_type === 'xml') {
            const document = parseXmlSample();
            if (!document?.documentElement) return [];
            const paths: string[] = [];
            const singleRecords: string[] = [];
            const visit = (
                element: Element,
                path: string,
                depth: number,
            ): void => {
                if (depth > 10) return;
                const children = [...element.children];
                for (const child of children) {
                    const childPath = `${path}/${xmlNodeName(child)}`;
                    if (
                        children.filter(
                            (item) =>
                                item.localName === child.localName &&
                                item.namespaceURI === child.namespaceURI,
                        ).length > 1
                    )
                        paths.push(childPath);
                    if (child.children.length || child.attributes.length)
                        singleRecords.push(childPath);
                    visit(child, childPath, depth + 1);
                }
            };
            const root = document.documentElement;
            visit(root, `/${xmlNodeName(root)}`, 0);
            return [
                ...new Set(
                    paths.length
                        ? paths
                        : [...singleRecords, `/${xmlNodeName(root)}`],
                ),
            ];
        }
        const paths: string[] = [];
        const visit = (value: unknown, path: string, depth: number): void => {
            if (depth > 4 || value === null || typeof value !== 'object')
                return;
            if (Array.isArray(value)) {
                if (
                    value.length &&
                    typeof value[0] === 'object' &&
                    value[0] !== null &&
                    !Array.isArray(value[0])
                )
                    paths.push(path);
                return;
            }
            for (const [key, child] of Object.entries(value))
                visit(child, path ? `${path}.${key}` : key, depth + 1);
        };
        visit(sampleData.value, '', 0);
        return paths;
    });
    const sampleFieldPaths = computed(() => {
        if (form.source_type === 'csv')
            return typeof sampleData.value === 'string'
                ? (
                      sampleCsvRows(
                          sampleData.value,
                          String(form.csv?.delimiter ?? ','),
                      )[0] ?? []
                  )
                      .map((header) => header.trim().replace(/^\uFEFF/, ''))
                      .filter(Boolean)
                : [];
        if (form.source_type === 'xml') {
            const document = parseXmlSample();
            if (!document) return [];
            try {
                const namespaces = (form.xml?.namespaces ?? {}) as Record<
                    string,
                    string
                >;
                const resolver = (prefix: string | null): string | null =>
                    prefix ? (namespaces[prefix] ?? null) : null;
                const records = document.evaluate(
                    form.records_path ||
                        `/${xmlNodeName(document.documentElement)}`,
                    document,
                    resolver,
                    XPathResult.ORDERED_NODE_SNAPSHOT_TYPE,
                    null,
                );
                const record = records.snapshotItem(0);
                if (!(record instanceof Element)) return [];
                const paths: string[] = [];
                const visit = (
                    element: Element,
                    path: string,
                    depth: number,
                ): void => {
                    if (depth > 10) return;
                    for (const attribute of element.attributes) {
                        if (
                            attribute.namespaceURI !==
                            'http://www.w3.org/2000/xmlns/'
                        )
                            paths.push(`${path}/@${xmlNodeName(attribute)}`);
                    }
                    for (const child of element.children) {
                        const childPath = `${path}/${xmlNodeName(child)}`;
                        if (!child.children.length) paths.push(childPath);
                        visit(child, childPath, depth + 1);
                    }
                };
                visit(record, '.', 0);
                return [...new Set(paths)];
            } catch {
                return [];
            }
        }
        if (!sampleData.value || typeof sampleData.value !== 'object')
            return [];
        let record: unknown = sampleData.value;
        for (const segment of form.records_path
            .replace(/^\$\.?/, '')
            .replace(/\[(\d+)\]/g, '.$1')
            .split('.')
            .filter(Boolean)) {
            if (record === null || typeof record !== 'object') return [];
            record = (record as Record<string, unknown>)[segment];
        }
        const paths: string[] = [];
        const visit = (value: unknown, path: string, depth: number): void => {
            if (depth > 10) return;
            if (value === null || typeof value !== 'object') {
                if (path) paths.push(path);
            } else if (Array.isArray(value)) {
                value
                    .slice(0, 3)
                    .forEach((child, index) =>
                        visit(child, `${path}[${index}]`, depth + 1),
                    );
            } else {
                for (const [key, child] of Object.entries(value)) {
                    if (/^[A-Za-z_][A-Za-z0-9_-]*$/.test(key))
                        visit(child, path ? `${path}.${key}` : key, depth + 1);
                }
            }
        };
        for (const row of Array.isArray(record)
            ? record.slice(0, 20)
            : [record])
            visit(row, '', 0);
        return [...new Set(paths)];
    });

    return {
        sampleRecordsPaths,
        sampleFieldPaths,
        parseXmlSample,
        discoverXmlNamespaces,
    };
}
