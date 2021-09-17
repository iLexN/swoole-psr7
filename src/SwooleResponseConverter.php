<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7;

use Dflydev\FigCookies\SetCookies;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;

final class SwooleResponseConverter
{
    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-http-server/methods-properties#swoole-http-response-write
     * @var int
     */
    public const CHUNK_SIZE = 2097152;

    /**
     * SwooleResponseConverter constructor.
     */
    public function __construct(private Response $response)
    {
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
        $this->response->end();
    }

    /**
     * Emit the status code
     *
     *
     */
    private function emitStatusCode(ResponseInterface $response): void
    {
        $this->response->status($response->getStatusCode());
    }

    /**
     * Emit the headers
     *
     *
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->withoutHeader(SetCookies::SET_COOKIE_HEADER)
                     ->getHeaders() as $name => $values) {
            $name = ucwords($name, '-');
            $this->response->header($name, implode(', ', $values));
        }
    }

    /**
     * Emit the cookies
     *
     *
     */
    private function emitCookies(ResponseInterface $response): void
    {
        foreach (SetCookies::fromResponse($response)->getAll() as $setCookie) {
            $this->response->cookie(
                $setCookie->getName(),
                $setCookie->getValue(),
                $setCookie->getExpires(),
                $setCookie->getPath() ?? '/',
                $setCookie->getDomain() ?? '',
                $setCookie->getSecure(),
                $setCookie->getHttpOnly(),
                // using substr() because FigCookies returns a string that starts with SameSite=
                $setCookie->getSameSite() === null ? '' : substr($setCookie->getSameSite()->asString(), 9),
            );
        }
    }

    /**
     * Emit the message body.
     *
     *
     */
    private function emitBody(ResponseInterface $response): void
    {
        $stream = $response->getBody();
        $stream->rewind();
        while (!$stream->eof()) {
            $this->response->write($stream->read(static::CHUNK_SIZE));
        }

        //$this->swooleResponse->end();
    }
}
