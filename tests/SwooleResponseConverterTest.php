<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Tests;

use Ilex\SwoolePsr7\SwooleResponseConverter;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
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

        $this->swooleResponse
            ->expects($this->exactly(2))
            ->method('header')
            ->withConsecutive([
                $this->equalTo('Content-Type'),
                $this->equalTo('text/plain'),
            ], [
                $this->equalTo('Content-Length'),
                $this->equalTo('256'),
            ]);

        $this->emitter->convertFromPsr7Response($response);

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
        $this->swooleResponse
            ->expects($this->exactly(3))
            ->method('cookie')
            ->withConsecutive([
                $this->equalTo('foo'),
                $this->equalTo('bar'),
                $this->equalTo(0),
                $this->equalTo('/'),
                $this->equalTo(''),
                $this->equalTo(false),
                $this->equalTo(false),
                $this->equalTo(''),
            ], [
                $this->equalTo('bar'),
                $this->equalTo('baz'),
                $this->equalTo(0),
                $this->equalTo('/'),
                $this->equalTo(''),
                $this->equalTo(false),
                $this->equalTo(false),
                $this->equalTo(''),
            ],[

                $this->equalTo('baz'),
                $this->equalTo('qux'),
                $this->equalTo(1623233894),
                $this->equalTo('/'),
                $this->equalTo('somecompany.co.uk'),
                $this->equalTo(true),
                $this->equalTo(true),
                $this->equalTo('None'),
            ]);


        $this->emitter->convertFromPsr7Response($response);
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
}
