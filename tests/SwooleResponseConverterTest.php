<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Tests;

use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\Modifier\SameSite;
use Ilex\SwoolePsr7\SwooleResponseConverter;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Response as SwooleHttpResponse;

class SwooleResponseConverterTest extends TestCase
{
    /**
     * @var SwooleResponseConverter
     */
    private SwooleResponseConverter $emitter;

    private $swooleResponse;

    public function setUp(): void
    {
        $this->swooleResponse = $this->createMock(SwooleHttpResponse::class);
        $this->emitter = new SwooleResponseConverter($this->swooleResponse);
    }

    public function testEmit(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(200));

        $this->swooleResponse
            ->expects($this->once())
            ->method('header')
            ->with($this->equalTo('Content-Type'),
                $this->equalTo('text/plain'));

        $this->emitter->send($response);
    }

    public function testMultipleHeaders(): void
    {
        $response = (new Response())
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Length', '256');

        $calls = [];
        $this->swooleResponse
            ->expects($this->exactly(2))
            ->method('header')
            ->willReturnCallback(function (string $name, string $value) use (&$calls) {
                $calls[] = [$name, $value];
                return true;
            });

        $this->emitter->convertFromPsr7Response($response);

        $this->assertEquals(['Content-Type', 'text/plain'], $calls[0]);
        $this->assertEquals(['Content-Length', '256'], $calls[1]);
    }

    public function testMultipleSetCookieHeaders(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz')
            ->withAddedHeader(
                'Set-Cookie',
                'baz=qux; Domain=somecompany.co.uk; Path=/; Expires=Wed, 09 Jun 2021 10:18:14 GMT; Secure; HttpOnly; SameSite=None'
            );

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(200));

        $calls = [];
        $this->swooleResponse
            ->expects($this->exactly(3))
            ->method('cookie')
            ->willReturnCallback(function (string $name, string $value, int $expires, string $path, string $domain, bool $secure, bool $httpOnly, string $sameSite) use (&$calls) {
                $calls[] = [$name, $value, $expires, $path, $domain, $secure, $httpOnly, $sameSite];
                return true;
            });

        $this->emitter->convertFromPsr7Response($response);

        $this->assertEquals(['foo', 'bar', 0, '/', '', false, false, ''], $calls[0]);
        $this->assertEquals(['bar', 'baz', 0, '/', '', false, false, ''], $calls[1]);
        $this->assertEquals(['baz', 'qux', 1623233894, '/', 'somecompany.co.uk', true, true, 'None'], $calls[2]);
    }

    public function testEmitWithBigContentBody(): void
    {
        $content = base64_encode(\random_bytes(SwooleResponseConverter::CHUNK_SIZE)); // CHUNK_SIZE * 1.33333
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write($content);

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(200));

        $this->swooleResponse
            ->expects($this->once())
            ->method('header')
            ->with($this->equalTo('Content-Type'),
                $this->equalTo('text/plain'));

        $this->swooleResponse
            ->method('write');

        $this->emitter->send($response);
    }

    public function testEmitWithSmallContentBody(): void
    {
        $content = 'small content';
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write($content);

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(200));

        $this->swooleResponse
            ->expects($this->once())
            ->method('header')
            ->with($this->equalTo('Content-Type'),
                $this->equalTo('text/plain'));

        $this->swooleResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->equalTo($content));

        $this->emitter->send($response);
    }

    public function testEmitWithNonSeekableStream(): void
    {
        $stream = $this->createStub(Stream::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('getSize')->willReturn(null);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, true);
        $stream->method('read')->willReturn('content');

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(200));

        $this->swooleResponse
            ->expects($this->once())
            ->method('write')
            ->with('content');

        $this->emitter->send($response);
    }

    public function testEmitWithNonReadableStream(): void
    {
        $stream = $this->createStub(Stream::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('isReadable')->willReturn(false);
        $stream->method('__toString')->willReturn('stream content');

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(200));

        $this->swooleResponse
            ->expects($this->once())
            ->method('end')
            ->with('stream content');

        $this->emitter->send($response);
    }

    public function testEmitWithStreamException(): void
    {
        $stream = $this->createStub(Stream::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('rewind')->willThrowException(new \RuntimeException('Stream error'));
        $stream->method('close');

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(200));

        $this->swooleResponse
            ->expects($this->once())
            ->method('end')
            ->with('');

        $this->emitter->send($response);
    }

    public function testEmitWithNullSizeStream(): void
    {
        $stream = $this->createStub(Stream::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('getSize')->willReturn(null);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, true);
        $stream->method('read')->willReturn('chunk');

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(200));

        $this->swooleResponse
            ->expects($this->once())
            ->method('write')
            ->with('chunk');

        $this->emitter->send($response);
    }

    public function testEmitWithFigCookiesSameSite(): void
    {
        $setCookie = SetCookie::create('test')
            ->withValue('value')
            ->withSameSite(SameSite::lax());

        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', (string) $setCookie);

        $calls = [];
        $this->swooleResponse
            ->expects($this->once())
            ->method('cookie')
            ->willReturnCallback(function (string $name, string $value, int $expires, string $path, string $domain, bool $secure, bool $httpOnly, string $sameSite) use (&$calls) {
                $calls[] = [$name, $value, $expires, $path, $domain, $secure, $httpOnly, $sameSite];
                return true;
            });

        $this->emitter->convertFromPsr7Response($response);

        $this->assertEquals('test', $calls[0][0]);
        $this->assertEquals('value', $calls[0][1]);
        $this->assertEquals('Lax', $calls[0][7]);
    }

    public function testEmitWithStringSameSite(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'test=value; SameSite=Strict');

        $calls = [];
        $this->swooleResponse
            ->expects($this->once())
            ->method('cookie')
            ->willReturnCallback(function (string $name, string $value, int $expires, string $path, string $domain, bool $secure, bool $httpOnly, string $sameSite) use (&$calls) {
                $calls[] = [$name, $value, $expires, $path, $domain, $secure, $httpOnly, $sameSite];
                return true;
            });

        $this->emitter->convertFromPsr7Response($response);

        $this->assertEquals('Strict', $calls[0][7]);
    }

    public function testEmitWithNullSameSite(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'test=value');

        $calls = [];
        $this->swooleResponse
            ->expects($this->once())
            ->method('cookie')
            ->willReturnCallback(function (string $name, string $value, int $expires, string $path, string $domain, bool $secure, bool $httpOnly, string $sameSite) use (&$calls) {
                $calls[] = [$name, $value, $expires, $path, $domain, $secure, $httpOnly, $sameSite];
                return true;
            });

        $this->emitter->convertFromPsr7Response($response);

        $this->assertEquals('', $calls[0][7]);
    }

    public function testEmitWithEmptyBody(): void
    {
        $response = (new Response())
            ->withStatus(204);

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(204));

        $this->swooleResponse
            ->expects($this->once())
            ->method('end');

        $this->emitter->send($response);
    }

    public function testEmitWithReadableStreamSmallSize(): void
    {
        $stream = $this->createStub(Stream::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('getSize')->willReturn(100);
        $stream->method('getContents')->willReturn('small stream content');

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        $this->swooleResponse
            ->expects($this->once())
            ->method('status')
            ->with($this->equalTo(200));

        $this->swooleResponse
            ->expects($this->once())
            ->method('end')
            ->with('small stream content');

        $this->emitter->send($response);
    }
}
