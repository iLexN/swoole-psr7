<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Utility;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request;

final readonly class ParseUriFromSwoole
{
    private UriInterface $uri;

    public function __construct(UriFactoryInterface $uriFactory)
    {
        $this->uri = $uriFactory->createUri();
    }

    public function __invoke(Request $swooleRequest): UriInterface
    {
        /** @var array<string, string> $server */
        $server = $swooleRequest->server;
        /** @var array<string, string> $header */
        $header = $swooleRequest->header;

        $uri = $this->parseScheme($server);
        $uri = $this->parseHostAndPort($uri, $server, $header);

        return $this->parseQuery($uri, $server);
    }

    /**
     * @param array<string, string> $server
     */
    private function parseScheme(array $server): UriInterface
    {
        return $this->uri->withScheme(isset($server['https']) && $server['https'] !== 'off' ? 'https' : 'http');
    }

    /**
     * @param array<string, string> $server
     * @param array<string, string> $header
     */
    private function parseHostAndPort(UriInterface $uri, array $server, array $header): UriInterface
    {
        if (isset($server['http_host'])) {
            $uri = $this->parseServerHttpHost($uri, $server);
        } elseif (isset($server['server_name'])) {
            $uri = $uri->withHost($server['server_name']);
        } elseif (isset($server['server_addr'])) {
            $uri = $uri->withHost($server['server_addr']);
        } elseif (isset($header['host'])) {
            $uri = $this->parseHeaderHost($uri, $header);
        }

        if (!isset($server['server_port'])) {
            return $uri;
        }

        if ($uri->getPort() !== null) {
            return $uri;
        }

        return $uri->withPort((int) $server['server_port']);
    }

    /**
     * @param array<string, string> $server
     */
    private function parseServerHttpHost(UriInterface $uri, array $server): UriInterface
    {
        $hostHeader = $server['http_host'];
        return $this->parseHostWithPort($uri, $hostHeader, false);
    }

    /**
     * @param array<string, string> $header
     */
    private function parseHeaderHost(UriInterface $uri, array $header): UriInterface
    {
        $hostHeader = $header['host'];
        return $this->parseHostWithPort($uri, $hostHeader, true);
    }

    /**
     * @param array<string, string> $server
     */
    private function parseQuery(UriInterface $uri, array $server): UriInterface
    {
        $hasQuery = false;
        if (isset($server['request_uri'])) {
            $requestUriParts = explode('?', $server['request_uri']);
            $uri = $uri->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $uri = $uri->withQuery($requestUriParts[1]);
            }
        }

        if ($hasQuery) {
            return $uri;
        }

        if (!isset($server['query_string'])) {
            return $uri;
        }

        return $uri->withQuery($server['query_string']);
    }

    /**
     * Parse host header with optional port, handling IPv6 addresses.
     *
     * @param UriInterface $uri The URI to modify
     * @param string $hostHeader The host header value (e.g., "example.com:8080" or "[::1]:8080")
     * @param bool $checkDefaultPort Whether to skip setting port if it matches the scheme default
     * @return UriInterface The modified URI
     */
    private function parseHostWithPort(UriInterface $uri, string $hostHeader, bool $checkDefaultPort): UriInterface
    {
        // Handle IPv6 addresses in brackets: [::1]:8080
        if (str_starts_with($hostHeader, '[')) {
            $closingBracket = strpos($hostHeader, ']');
            if ($closingBracket !== false) {
                $host = substr($hostHeader, 0, $closingBracket + 1);
                $portPart = substr($hostHeader, $closingBracket + 1);
                if (str_starts_with($portPart, ':')) {
                    $port = (int) substr($portPart, 1);
                    if ($port > 0 && (!$checkDefaultPort || $port !== $this->getDefaultPort($uri))) {
                        $uri = $uri->withPort($port);
                    }
                }

                return $uri->withHost($host);
            }
        }

        // Handle regular host:port format
        if (str_contains($hostHeader, ':')) {
            [$host, $port] = explode(':', $hostHeader, 2);
            if ($host !== '' && $port !== '') {
                $portInt = (int) $port;
                if (!$checkDefaultPort || $portInt !== $this->getDefaultPort($uri)) {
                    $uri = $uri->withPort($portInt);
                }
            }

            if ($host !== '') {
                $uri = $uri->withHost($host);
            }
        } else {
            $uri = $uri->withHost($hostHeader);
        }

        return $uri;
    }

    /**
     * Get the default port for the current URI scheme.
     *
     * @param UriInterface $uri The URI to check
     * @return int The default port (80 for http, 443 for https)
     */
    private function getDefaultPort(UriInterface $uri): int
    {
        return $uri->getScheme() === 'https' ? 443 : 80;
    }
}
