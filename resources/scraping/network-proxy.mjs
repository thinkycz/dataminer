import dns from 'node:dns/promises';
import http from 'node:http';
import net from 'node:net';

const blocked = new net.BlockList();
for (const [subnet, prefix] of [
    ['0.0.0.0', 8],
    ['10.0.0.0', 8],
    ['100.64.0.0', 10],
    ['127.0.0.0', 8],
    ['169.254.0.0', 16],
    ['172.16.0.0', 12],
    ['192.0.0.0', 24],
    ['192.0.2.0', 24],
    ['192.168.0.0', 16],
    ['198.18.0.0', 15],
    ['198.51.100.0', 24],
    ['203.0.113.0', 24],
    ['224.0.0.0', 4],
    ['240.0.0.0', 4],
])
    blocked.addSubnet(subnet, prefix, 'ipv4');
for (const [subnet, prefix] of [
    ['::', 128],
    ['::1', 128],
    ['64:ff9b:1::', 48],
    ['100::', 64],
    ['2001::', 23],
    ['2001:db8::', 32],
    ['2001:10::', 28],
    ['2002::', 16],
    ['fc00::', 7],
    ['fe80::', 10],
    ['ff00::', 8],
])
    blocked.addSubnet(subnet, prefix, 'ipv6');

export function isPublicAddress(address) {
    const family = net.isIP(address);
    if (!family) return false;
    if (family === 6 && !address.toLowerCase().startsWith('2')) return false;
    return !blocked.check(address, family === 4 ? 'ipv4' : 'ipv6');
}

export async function resolvePublicHost(hostname, lookup = dns.lookup) {
    if (
        !hostname ||
        /(^|\.)(localhost|local|internal|test|invalid)$/i.test(hostname)
    ) {
        throw new Error('Local hostname blocked');
    }
    const answers = net.isIP(hostname)
        ? [{ address: hostname }]
        : await lookup(hostname, { all: true, verbatim: true });
    if (
        !answers.length ||
        answers.some(({ address }) => !isPublicAddress(address))
    ) {
        throw new Error('Non-public address blocked');
    }
    return answers[0].address;
}

function parseTarget(raw, expectedProtocol) {
    const url = new URL(raw);
    if (
        url.protocol !== expectedProtocol ||
        url.username ||
        url.password ||
        url.hash
    ) {
        throw new Error('Unsupported proxy target');
    }
    const port = Number(url.port || (url.protocol === 'https:' ? 443 : 80));
    if (![80, 443].includes(port)) throw new Error('Unsupported proxy port');
    return { url, port };
}

export async function createPublicProxy({
    maxRequests = 300,
    maxBytes = 40_000_000,
    timeoutMs = 15_000,
    lookup,
} = {}) {
    let requests = 0;
    let bytes = 0;
    const sockets = new Set();
    const server = http.createServer(async (request, response) => {
        try {
            if (++requests > maxRequests)
                throw new Error('Request limit reached');
            if (
                Number(request.headers['content-length'] ?? 0) >
                maxBytes - bytes
            ) {
                throw new Error('Byte limit reached');
            }
            const { url, port } = parseTarget(request.url, 'http:');
            const address = await resolvePublicHost(url.hostname, lookup);
            const headers = { ...request.headers, host: url.host };
            delete headers['proxy-authorization'];
            delete headers['proxy-connection'];
            const upstream = http.request(
                {
                    host: address,
                    family: net.isIP(address),
                    port,
                    method: request.method,
                    path: url.pathname + url.search,
                    headers,
                    timeout: timeoutMs,
                },
                (incoming) => {
                    response.writeHead(incoming.statusCode, incoming.headers);
                    incoming.on('data', (chunk) => {
                        bytes += chunk.length;
                        if (bytes > maxBytes)
                            incoming.destroy(new Error('Byte limit reached'));
                    });
                    incoming.pipe(response);
                },
            );
            upstream.on('timeout', () =>
                upstream.destroy(new Error('Network timeout')),
            );
            upstream.on('error', () => response.destroy());
            request.on('data', (chunk) => {
                bytes += chunk.length;
                if (bytes > maxBytes) {
                    request.destroy();
                    upstream.destroy();
                }
            });
            request.pipe(upstream);
        } catch {
            response.writeHead(403).end();
        }
    });
    server.on('connect', async (request, socket, head) => {
        try {
            if (++requests > maxRequests)
                throw new Error('Request limit reached');
            const { url, port } = parseTarget(
                `https://${request.url}`,
                'https:',
            );
            const address = await resolvePublicHost(url.hostname, lookup);
            const upstream = net.connect({
                host: address,
                port,
                family: net.isIP(address),
            });
            upstream.setTimeout(timeoutMs);
            socket.setTimeout(timeoutMs);
            sockets.add(socket);
            sockets.add(upstream);
            const count = (chunk) => {
                bytes += chunk.length;
                if (bytes > maxBytes) {
                    socket.destroy();
                    upstream.destroy();
                }
            };
            socket.on('data', count);
            upstream.on('data', count);
            socket.on('close', () => sockets.delete(socket));
            upstream.on('close', () => sockets.delete(upstream));
            socket.on('timeout', () => socket.destroy());
            upstream.on('timeout', () => upstream.destroy());
            upstream.on('error', () => socket.destroy());
            socket.on('error', () => upstream.destroy());
            upstream.once('connect', () => {
                socket.write('HTTP/1.1 200 Connection Established\r\n\r\n');
                if (head.length) upstream.write(head);
                socket.pipe(upstream);
                upstream.pipe(socket);
            });
        } catch {
            socket.end('HTTP/1.1 403 Forbidden\r\n\r\n');
        }
    });
    server.on('upgrade', (_request, socket) =>
        socket.end('HTTP/1.1 403 Forbidden\r\n\r\n'),
    );
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    return {
        url: `http://127.0.0.1:${server.address().port}`,
        stats: () => ({ requests, bytes }),
        close: async () => {
            for (const socket of sockets) socket.destroy();
            server.closeAllConnections();
            await new Promise((resolve) => server.close(resolve));
        },
    };
}
