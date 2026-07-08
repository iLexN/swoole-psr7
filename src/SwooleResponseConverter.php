<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7;

use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookies;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;

final readonly class SwooleResponseConverter
{

    /**
     * @see https://www.swoole.co.uk/docs/modules/swoole-http-server/methods-properties#swoole-http-response-write
     */
    public const int CHUNK_SIZE = 2097152;

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
    }

    private function emitStatusCode(ResponseInterface $response): void
    {
        $this->response->status($response->getStatusCode());
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->withoutHeader(SetCookies::SET_COOKIE_HEADER)
                     ->getHeaders() as $name => $values) {
            $name = ucwords($name, '-');
            $this->response->header($name, implode(', ', $values));
        }
    }

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
                $this->extractSameSiteValue($setCookie->getSameSite()),
            );
        }
    }

    /**
     * Extract SameSite value from FigCookies SameSite object.
     *
     * @param SameSite|string|null $sameSite The SameSite object, string, or null
     * @return string The SameSite value (e.g., "Lax")
     */
    private function extractSameSiteValue(SameSite|string|null $sameSite): string
    {
        if ($sameSite instanceof SameSite) {
            $sameSiteString = $sameSite->asString();
            $prefix = 'SameSite=';
            if (str_starts_with($sameSiteString, $prefix)) {
                return substr($sameSiteString, strlen($prefix));
            }

            return $sameSiteString;
        }

        if (is_string($sameSite)) {
            return $sameSite;
        }

        return '';
    }

    private function emitBody(ResponseInterface $response): void
    {
        $stream = $response->getBody();

        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            if (! $stream->isReadable()) {
                $this->response->end((string) $stream);
                return;
            }

            if ($stream->getSize() !== null && $stream->getSize() <= self::CHUNK_SIZE) {
                $this->response->end($stream->getContents());
                return;
            }

            while (!$stream->eof()) {
                $this->response->write($stream->read(self::CHUNK_SIZE));
            }
        } catch (\Exception $exception) {
            $stream->close();
            $this->response->end('');
        }
    }
}
