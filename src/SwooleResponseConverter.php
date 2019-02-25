<?php

declare(strict_types=1);

namespace Ilex\SwooleServer;

use Dflydev\FigCookies\SetCookies;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;

final class SwooleResponseConverter
{
    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-http-server/methods-properties#swoole-http-response-write
     */
    public const CHUNK_SIZE = 2097152; // 2 MB

    /**
     * @var \Swoole\Http\Response
     */
    private $swooleResponse;

    /**
     * SwooleResponseConverter constructor.
     *
     * @param \Swoole\Http\Response $response
     */
    public function __construct(Response $response)
    {
        $this->swooleResponse = $response;
    }

    public function convertFromPsr7Response(ResponseInterface $response): void
    {
        $this->emitStatusCode($response);
        $this->emitHeaders($response);
        $this->emitCookies($response);
        $this->emitBody($response);
    }

    public function send(ResponseInterface $response): void
    {
        $this->convertFromPsr7Response($response);
        $this->swooleResponse->end();
    }

    /**
     * Emit the status code
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    private function emitStatusCode(ResponseInterface $response): void
    {
        $this->swooleResponse->status($response->getStatusCode());
    }

    /**
     * Emit the headers
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->withoutHeader(SetCookies::SET_COOKIE_HEADER)
                     ->getHeaders() as $name => $values) {
            $name = ucwords($name, '-');
            $this->swooleResponse->header($name, implode(', ', $values));
        }
    }

    /**
     * Emit the cookies
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    private function emitCookies(ResponseInterface $response): void
    {
        foreach (SetCookies::fromResponse($response)->getAll() as $cookie) {
            $this->swooleResponse->cookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpires(),
                $cookie->getPath() ?: '/',
                $cookie->getDomain() ?: '',
                $cookie->getSecure(),
                $cookie->getHttpOnly()
            );
        }
    }

    /**
     * Emit the message body.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();
        $body->rewind();
        while (!$body->eof()) {
            $this->swooleResponse->write($body->read(static::CHUNK_SIZE));
        }
        //$this->swooleResponse->end();
    }
}
