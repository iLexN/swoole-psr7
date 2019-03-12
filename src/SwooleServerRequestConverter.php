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
     * @var \Psr\Http\Message\ServerRequestFactoryInterface
     */
    private $serverRequestFactory;

    /**
     * @var \Psr\Http\Message\UriFactoryInterface
     */
    private $uriFactory;

    /**
     * @var \Psr\Http\Message\UploadedFileFactoryInterface
     */
    private $uploadedFileFactory;

    /**
     * @var \Psr\Http\Message\StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * SwooleServerRequestConverter constructor.
     *
     * @param \Psr\Http\Message\ServerRequestFactoryInterface $serverRequestFactory
     * @param \Psr\Http\Message\UriFactoryInterface $uriFactory
     * @param \Psr\Http\Message\UploadedFileFactoryInterface $uploadedFileFactory
     * @param \Psr\Http\Message\StreamFactoryInterface $streamFactory
     */
    public function __construct(
        ServerRequestFactoryInterface $serverRequestFactory,
        UriFactoryInterface $uriFactory,
        UploadedFileFactoryInterface $uploadedFileFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->serverRequestFactory = $serverRequestFactory;
        $this->uriFactory = $uriFactory;
        $this->uploadedFileFactory = $uploadedFileFactory;
        $this->streamFactory = $streamFactory;
    }

    public function createFromSwoole(
        Request $swooleRequest
    ): ServerRequestInterface {
        $server = $swooleRequest->server;
        $method = $server['request_method'] ?? 'GET';
        $headers = $swooleRequest->header ?? [];
        $uri = $this->parseUri($swooleRequest);
        dump($server);
        $serverRequest = $this->serverRequestFactory->createServerRequest(
            $method,
            $uri,
            array_change_key_case($server, CASE_UPPER)
        );

        $serverRequest = $this->addHeaders($headers, $serverRequest);

        return $serverRequest
            ->withProtocolVersion($this->parseProtocol($server))
            ->withCookieParams($swooleRequest->cookie ?? [])
            ->withQueryParams($swooleRequest->get ?? [])
            ->withParsedBody($swooleRequest->post ?? [])
            ->withBody($this->streamFactory->createStream($swooleRequest->rawcontent() ?: ''))
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
            $server['server_protocol']
        ) : $defaultProtocol;
        if (is_string($protocol)) {
            return $protocol;
        }
        return $defaultProtocol;
    }

    /**
     * Get a Uri populated with values from $swooleRequest->server.
     *
     * @param \Swoole\Http\Request $swooleRequest
     *
     * @return \Psr\Http\Message\UriInterface
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
