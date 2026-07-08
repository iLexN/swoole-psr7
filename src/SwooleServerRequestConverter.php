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

final readonly class SwooleServerRequestConverter
{

    public function __construct(
        private ServerRequestFactoryInterface $serverRequestFactory,
        private UriFactoryInterface $uriFactory,
        private UploadedFileFactoryInterface $uploadedFileFactory,
        private StreamFactoryInterface $streamFactory
    ) {
    }

    public function createFromSwoole(
        Request $swooleRequest
    ): ServerRequestInterface {
        /** @var array<string, string> $server */
        $server = $swooleRequest->server ?? [];
        $method = $server['request_method'] ?? 'GET';
        /** @var array<string, string> $headers */
        $headers = $swooleRequest->header ?? [];
        $uri = $this->parseUri($swooleRequest);

        $serverRequest = $this->serverRequestFactory->createServerRequest(
            $method,
            $uri,
            array_change_key_case($server, CASE_UPPER)
        );

        $serverRequest = $this->addHeaders($headers, $serverRequest);

        // OpenSwoole returns bool|string, Swoole returns string from rawContent()
        $content = is_string($swooleRequest->rawContent()) ? $swooleRequest->rawContent() : '';
        $stream = $this->streamFactory->createStream($content);
        $stream->rewind();

        /** @var array<string, string> $cookie */
        $cookie = $swooleRequest->cookie ?? [];
        /** @var array<string, string> $queryParams */
        $queryParams = $swooleRequest->get ?? [];
        /** @var array<string, string> $parsedBody */
        $parsedBody = $swooleRequest->post ?? [];
        /** @var array<string, mixed> $files */
        $files = $swooleRequest->files ?? [];

        return $serverRequest
            ->withProtocolVersion($this->parseProtocol($server))
            ->withCookieParams($cookie)
            ->withQueryParams($queryParams)
            ->withParsedBody($parsedBody)
            ->withBody($stream)
            ->withUploadedFiles($this->parseUploadedFiles($files));
    }

    /**
     * @param array<string, string> $headers
     */
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

    /**
     * @param array<string, string> $server
     */
    private function parseProtocol(array $server): string
    {
        $defaultProtocol = '1.1';
        return isset($server['server_protocol']) ? str_replace(
            'HTTP/',
            '',
            $server['server_protocol']
        ) : $defaultProtocol;
    }

    private function parseUri(Request $swooleRequest): UriInterface
    {
        return (new ParseUriFromSwoole($this->uriFactory))($swooleRequest);
    }

    /**
     * Parse a non-normalized, i.e. $_FILES superglobal, tree of uploaded file
     * data.
     *
     * @param array<int|string, mixed> $uploadedFiles The non-normalized tree of uploaded file
     *     data.
     *
     * @return array<int|string, mixed> A normalized tree of UploadedFile instances.
     */
    private function parseUploadedFiles(array $uploadedFiles): array
    {
        return new ParseUploadedFiles(
            $this->uploadedFileFactory,
            $this->streamFactory
        )->parseUploadedFiles($uploadedFiles);
    }
}
