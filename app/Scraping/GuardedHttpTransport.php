<?php

declare(strict_types=1);

namespace App\Scraping;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Bounded HTTP fetch with public DNS pinning and explicit redirects.
 */
final class GuardedHttpTransport
{
    /**
     * @var list<int>
     */
    private const TRANSIENT_STATUSES = [408, 429, 500, 502, 503, 504];

    /**
     * @param array<string, string> $headers
     *
     * @return array{body:string,url:string,requests:int,bytes:int}
     */
    public function get(string $url, array $headers, int $maxBytes, int $maxSeconds, int $requestBudget): array
    {
        $requests = 0;
        $bytes = 0;
        $start = \microtime(true);
        $originalOrigin = $this->origin($url);
        $redirects = 0;

        while (true) {
            $parts = $this->resolvePublicUrl($url);
            if ($headers !== [] && $originalOrigin !== $this->origin($url)) {
                throw new InvalidArgumentException('Authenticated requests cannot redirect to another origin.');
            }
            $redirect = false;
            for ($attempt = 0; $attempt < 3; ++$attempt) {
                if ($requests >= $requestBudget) { throw new RuntimeException('Request limit reached.'); }
                $seconds = $maxSeconds - (int) \floor(\microtime(true) - $start);
                if ($seconds < 1) { throw new RuntimeException('Time limit reached.'); }
                ++$requests;
                try {
                    $response = Http::withHeaders($headers)
                        ->withOptions([
                            'allow_redirects' => false,
                            'proxy' => '',
                            'curl' => [\CURLOPT_RESOLVE => [$parts['host'] . ':' . $parts['port'] . ':' . $parts['address']]],
                            'progress' => static function (int $downloadTotal, int $downloaded) use ($maxBytes, $bytes): void {
                                if ($downloadTotal > $maxBytes - $bytes || $downloaded > $maxBytes - $bytes) {
                                    throw new RuntimeException('Byte limit reached.');
                                }
                            },
                        ])
                        ->timeout($seconds)
                        ->connectTimeout(\min(10, $seconds))
                        ->get($url);
                } catch (Throwable $exception) {
                    if ($attempt < 2 && $exception->getMessage() !== 'Byte limit reached.') { \usleep(100_000 * ($attempt + 1));

                        continue; }
                    throw $exception;
                }
                if (\in_array($response->status(), self::TRANSIENT_STATUSES, true) && $attempt < 2) { \usleep(100_000 * ($attempt + 1));

                    continue; }
                if ($response->status() >= 300 && $response->status() < 400) {
                    $location = $response->header('Location');
                    if ($location === '') { throw new RuntimeException('Redirect has no location.'); }
                    if (++$redirects > 5) { throw new RuntimeException('Too many redirects.'); }
                    $url = $this->absoluteUrl($url, $location);
                    $redirect = true;
                    break;
                }
                if (\in_array($response->status(), [401, 403], true)) { throw new RuntimeException('auth_expired'); }
                if (!$response->successful()) { throw new RuntimeException('Source returned HTTP ' . $response->status() . '.'); }
                $body = $response->body();
                $bytes += \mb_strlen($body, '8bit');
                if ($bytes > $maxBytes) { throw new RuntimeException('Byte limit reached.'); }

                return ['body' => $body, 'url' => $url, 'requests' => $requests, 'bytes' => $bytes];
            }
            if (!$redirect) { throw new RuntimeException('Source retries exhausted.'); }
        }
    }

    /**
     * Resolve a relative link against its current page.
     */
    public function absoluteUrl(string $base, string $next): string
    {
        if (\preg_match('~^https?://~i', $next) === 1) { return $next; }
        $parts = \parse_url($base);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) { throw new InvalidArgumentException('Invalid base URL.'); }
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (\str_starts_with($next, '//')) { return $parts['scheme'] . ':' . $next; }
        if (\str_starts_with($next, '/')) { return $origin . $next; }
        if (\str_starts_with($next, '?')) { return $origin . ($parts['path'] ?? '/') . $next; }
        $directory = \preg_replace('~/[^/]*$~', '/', $parts['path'] ?? '/');

        return $origin . $directory . $next;
    }

    /**
     * Compute an exact scheme, host, and port authority.
     */
    public function origin(string $url): string
    {
        $parts = \parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) { throw new InvalidArgumentException('Invalid URL.'); }

        return \mb_strtolower($parts['scheme']) . '://' . \mb_strtolower($parts['host']) . ':' . ($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80));
    }

    /**
     * @return array{host:string,port:int,address:string}
     */
    private function resolvePublicUrl(string $url): array
    {
        if (!\defined('CURLOPT_RESOLVE')) { throw new RuntimeException('Pinned HTTP transport requires cURL.'); }
        (new NetworkGuard())->assertPublicHttpUrl($url);
        $parts = \parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) { throw new InvalidArgumentException('Invalid URL.'); }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) { throw new InvalidArgumentException('URL credentials and fragments are forbidden.'); }
        $host = $parts['host'];
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        if ($port < 1) { throw new InvalidArgumentException('Invalid URL port.'); }
        $resolved = \gethostbynamel($host);
        $addresses = \filter_var($host, \FILTER_VALIDATE_IP) !== false ? [$host] : ($resolved === false ? [] : $resolved);
        $address = $addresses[0] ?? null;
        if ($address === null) {
            $records = \dns_get_record($host, \DNS_AAAA);
            $address = \is_array($records) ? ($records[0]['ipv6'] ?? null) : null;
        }
        if (!\is_string($address) || \filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) === false) { throw new InvalidArgumentException('No public address.'); }

        return ['host' => $host, 'port' => $port, 'address' => $address];
    }
}
