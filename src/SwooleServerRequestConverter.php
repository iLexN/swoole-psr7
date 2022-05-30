<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7;

use Ilex\SwoolePsr7\Utility\ParseUploadedFiles;
use Ilex\SwoolePsr7\Utility\ParseUriFromSwoole;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request;

final class SwooleServerRequestConverter
{

    /**
     * SwooleServerRequestConverter constructor.
     */
    public function __construct(
        private readonly ServerRequestFactoryInterface $serverRequestFactory,
        private readonly UriFactoryInterface $uriFactory,
        private readonly UploadedFileFactoryInterface $uploadedFileFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {
    }

    public function createFromSwoole(
        Request $swooleRequest
    ): ServerRequestInterface {
        $server = $swooleRequest->server;
        $method = $server['request_method'] ?? 'GET';
        $headers = $swooleRequest->header ?? [];
        $uri = $this->parseUri($swooleRequest);

        $serverRequest = $this->serverRequestFactory->createServerRequest(
            $method,
            $uri,
            array_change_key_case($server, CASE_UPPER)
        );

        $serverRequest = $this->addHeaders($headers, $serverRequest);

        // openswoole $swooleRequest->rawContent return bool|string
        // swoole $swooleRequest->rawContent return string
        /** @var string $content */
        $content = is_string($swooleRequest->rawContent()) ? $swooleRequest->rawContent() : '';
        $stream = $this->streamFactory->createStream($content);
        $stream->rewind();

        return $serverRequest
            ->withProtocolVersion($this->parseProtocol($server))
            ->withCookieParams($swooleRequest->cookie ?? [])
            ->withQueryParams($swooleRequest->get ?? [])
            ->withParsedBody($swooleRequest->post ?? [])
            ->withBody($stream)
            ->withUploadedFiles($this->parseUploadedFiles($swooleRequest->files ?? []));
    }

    private function addHeaders(
        array $headers,
        ServerRequestInterface $serverRequest
    ): ServerRequestInterface {
        foreach ($headers as $name => $value) {
            if ($serverRequest->hasHeader($name)) {
                continue;
            }

            $serverRequest = $serverRequest->withAddedHeader($name, $value);
        }

        return $serverRequest;
    }

    private function parseProtocol(array $server): string
    {
        $defaultProtocol = '1.1';
        $protocol = isset($server['server_protocol']) ? \str_replace(
            'HTTP/',
            '',
            (string) $server['server_protocol']
        ) : $defaultProtocol;
        if (is_string($protocol)) {
            return $protocol;
        }

        return $defaultProtocol;
    }

    /**
     * Get a Uri populated with values from $swooleRequest->server.
     *
     *
     * @throws \InvalidArgumentException
     */
    private function parseUri(Request $swooleRequest): UriInterface
    {
        return (new ParseUriFromSwoole($this->uriFactory))($swooleRequest);
    }

    /**
     * Parse a non-normalized, i.e. $_FILES superglobal, tree of uploaded file
     * data.
     *
     * @param array $uploadedFiles The non-normalized tree of uploaded file
     *     data.
     *
     * @return array A normalized tree of UploadedFile instances.
     */
    private function parseUploadedFiles(array $uploadedFiles): array
    {
        return (new ParseUploadedFiles(
            $this->uploadedFileFactory,
            $this->streamFactory
        ))->parseUploadedFiles($uploadedFiles);
    }
}
