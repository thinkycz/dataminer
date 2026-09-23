import { randomBytes } from 'node:crypto';
import { chromium } from 'playwright';
import { startBrowserService } from '../../resources/scraping/browser-service.mjs';

const products = [
    { title: 'Notebook', price: 12, description: 'Graph paper notebook' },
    { title: 'Pencil', price: 2, description: 'Soft graphite pencil' },
    { title: 'Eraser', price: 3, description: 'White rubber eraser' },
];

function document(body) {
    return `<!doctype html><html><head><title>Test catalog</title><style>
        body { font: 20px Arial; margin: 0; color: #172e27; }
        .account, h1 { position: absolute; left: 40px; top: 10px; }
        .item { position: absolute; left: 40px; width: 500px; height: 150px; border: 1px solid #315447; background: #f3f7f4; }
        .title { position: absolute; left: 20px; top: 20px; margin: 0; font-size: 24px; }
        .price { position: absolute; left: 20px; top: 65px; }
        .detail { position: absolute; left: 20px; top: 110px; }
        .next { position: absolute; left: 40px; top: 500px; }
        input, button { position: absolute; left: 40px; width: 400px; height: 50px; font: inherit; }
        .description { position: absolute; left: 40px; top: 100px; margin: 0; }
    </style></head><body>${body}</body></html>`;
}

async function serveSource(route) {
    const url = new URL(route.request().url());
    if (url.origin !== 'https://1.1.1.1' || !url.pathname.startsWith('/e2e/'))
        return route.abort('blockedbyclient');
    const headers = await route.request().allHeaders();
    if (url.pathname === '/e2e/interaction') {
        const selected = headers.cookie
            ?.split('; ')
            .includes('catalog_preference=on');
        const frame = `<label><input type="checkbox" ${selected ? 'checked' : ''} style="position:absolute;left:20px;top:20px;width:24px;height:24px" onchange="parent.postMessage(this.checked ? 'on' : 'off', '*')"><span style="position:absolute;left:60px;top:22px;font:18px Arial">Include archived items</span></label>`;
        return route.fulfill({
            contentType: 'text/html',
            body: document(`<h1>Source preferences</h1>
                <iframe title="Preferences" sandbox="allow-scripts" srcdoc="${frame.replaceAll('&', '&amp;').replaceAll('"', '&quot;')}" style="position:absolute;left:40px;top:100px;width:500px;height:150px;border:0"></iframe>
                <script>document.title = 'Preference:${selected ? 'on' : 'off'}';
                window.addEventListener('message', (event) => {
                    if (event.source !== document.querySelector('iframe').contentWindow || !['on', 'off'].includes(event.data)) return;
                    document.cookie = 'catalog_preference=' + event.data + '; Path=/; Secure; SameSite=Lax';
                    document.title = 'Preference:' + event.data;
                });</script>`),
        });
    }
    if (!headers.cookie?.split('; ').includes('catalog_session=demo')) {
        return route.fulfill({
            contentType: 'text/html',
            body: document(`<h1>Catalog login</h1><form>
                <input name="email" aria-label="Email" placeholder="Email" style="top:100px">
                <input name="password" aria-label="Password" type="password" placeholder="Password" style="top:200px">
                <button style="top:300px">Sign in</button>
                </form><script>document.querySelector('form').onsubmit = (event) => {
                    event.preventDefault();
                    if (event.target.email.value === 'catalog@example.com' && event.target.password.value === 'catalog-password') {
                        document.cookie = 'catalog_session=demo; Path=/; Secure; SameSite=Lax';
                        location.href = '/e2e/website';
                    }
                };</script>`),
        });
    }
    if (url.pathname.startsWith('/e2e/item/')) {
        const product = products[Number(url.pathname.split('/').at(-1))];
        return route.fulfill({
            status: product ? 200 : 404,
            contentType: 'text/html',
            body: document(
                `<p class="account">Catalog account</p><p class="description">${product?.description ?? 'Not found'}</p>`,
            ),
        });
    }
    const secondPage = url.searchParams.get('page') === '2';
    const pageProducts = secondPage ? products.slice(2) : products.slice(0, 2);
    return route.fulfill({
        contentType: 'text/html',
        body: document(
            `<p class="account">Catalog account</p>${pageProducts
                .map(
                    (product, index) =>
                        `<article class="item" style="top:${100 + index * 180}px"><h2 class="title">${product.title}</h2><span class="price">${product.price}</span><a class="detail" href="/e2e/item/${secondPage ? 2 : index}">Details</a></article>`,
                )
                .join(
                    '',
                )}${secondPage ? '' : '<a class="next" href="/e2e/website?page=2">Next page</a>'}`,
        ),
    });
}

export async function startTestBrowserService() {
    const secret = randomBytes(32).toString('hex');
    const service = await startBrowserService({
        secret,
        allowVisibleBrowser: process.env.DATAMINER_E2E_NATIVE === '1',
        browserFactory: {
            launch: async (options) => {
                const browser = await chromium.launch(options);
                return {
                    close: () => browser.close(),
                    isConnected: () => browser.isConnected(),
                    newContext: async (settings) => {
                        const context = await browser.newContext(settings);
                        const newPage = context.newPage.bind(context);
                        context.newPage = async () => {
                            const page = await newPage();
                            await page.route('**/*', serveSource);
                            return page;
                        };
                        return context;
                    },
                };
            },
        },
    });
    return { ...service, secret };
}
