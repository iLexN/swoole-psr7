<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Utility;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request;

final class ParseUriFromSwoole
{
    /**
     * @var \Psr\Http\Message\UriInterface
     */
    private $uri;

    public function __construct(UriFactoryInterface $factory)
    {
        $this->uri = $factory->createUri();
    }

    public function __invoke(Request $swooleRequest): UriInterface
    {
        $server = $swooleRequest->server;
        $header = $swooleRequest->header;

        $this->parseScheme($server);
        $this->parseHostAndPort($server, $header);
        $this->parseQuery($server);

        return $this->uri;
    }

    private function parseScheme(array $server): void
    {
        $this->uri = $this->uri->withScheme(isset($server['https']) && $server['https'] !== 'off' ? 'https' : 'http');
    }

    private function parseHostAndPort(array $server, array $header): void
    {
        if (isset($server['http_host'])) {
            $this->parseServerHttpHost($server);
        } elseif (isset($server['server_name'])) {
            $this->uri = $this->uri->withHost($server['server_name']);
        } elseif (isset($server['server_addr'])) {
            $this->uri = $this->uri->withHost($server['server_addr']);
        } elseif (isset($header['host'])) {
            $this->parseHeaderHost($header);
        }
        if (isset($server['server_port']) && $this->uri->getPort() !== null) {
            $this->uri = $this->uri->withPort($server['server_port']);
        }
    }

    private function parseServerHttpHost(array $server): void
    {
        $hostHeaderParts = explode(':', $server['http_host']);
        $this->uri = $this->uri->withHost($hostHeaderParts[0]);
        if (isset($hostHeaderParts[1])) {
            $this->uri = $this->uri->withPort((int)$hostHeaderParts[1]);
        }
    }

    private function parseHeaderHost(array $header): void
    {
        if (\strpos($header['host'], ':') !== false) {
            [$host, $port] = explode(':', $header['host'], 2);
            if ($port !== '80') {
                $this->uri = $this->uri->withPort((int)$port);
            }
        } else {
            $host = $header['host'];
        }
        $this->uri = $this->uri->withHost($host);
    }

    private function parseQuery(array $server): void
    {
        $hasQuery = false;
        if (isset($server['request_uri'])) {
            $requestUriParts = explode('?', $server['request_uri']);
            $this->uri = $this->uri->withPath($requestUriParts[0]);
            if (isset($requestUriParts[1])) {
                $hasQuery = true;
                $this->uri = $this->uri->withQuery($requestUriParts[1]);
            }
        }
        if (!$hasQuery && isset($server['query_string'])) {
            $this->uri = $this->uri->withQuery($server['query_string']);
        }
    }
}
