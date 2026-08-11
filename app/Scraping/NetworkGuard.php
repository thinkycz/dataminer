<?php

declare(strict_types=1);

namespace App\Scraping;

use InvalidArgumentException;

class NetworkGuard
{
    /**
     * Assert that a URL targets a public HTTP(S) host.
     */
    public function assertPublicHttpUrl(string $url): void
    {
        $parts = \parse_url($url);

        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('The target URL is invalid.');
        }

        $scheme = \mb_strtolower($parts['scheme']);
        $host = \mb_strtolower($parts['host']);

        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only HTTP(S) targets are allowed.');
        }

        if ($host === 'localhost' || \str_ends_with($host, '.localhost') || $host === 'metadata.google.internal') {
            throw new InvalidArgumentException('Local and metadata targets are blocked.');
        }

        $ipv4 = \gethostbynamel($host);
        $addresses = \filter_var($host, \FILTER_VALIDATE_IP) !== false
            ? [$host]
            : [...($ipv4 === false ? [] : $ipv4), ...$this->resolveIpv6($host)];

        if ($addresses === []) {
            throw new InvalidArgumentException('The target host could not be resolved.');
        }

        foreach (\array_unique($addresses) as $address) {
            if (\filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('Private, reserved, and link-local targets are blocked.');
            }
        }
    }

    /**
     * Resolve IPv6 addresses when DNS support is available.
     *
     * @return array<int, string>
     */
    private function resolveIpv6(string $host): array
    {
        $records = \dns_get_record($host, \DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return \array_values(\array_filter(\array_map(
            static fn(array $record): string|null => isset($record['ipv6']) && \is_string($record['ipv6']) ? $record['ipv6'] : null,
            $records,
        ), static fn(string|null $address): bool => $address !== null));
    }
}
