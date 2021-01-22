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
    use \Prophecy\PhpUnit\ProphecyTrait;
    /**
     * @var SwooleResponseConverter
     */
    private SwooleResponseConverter $emitter;

    private $swooleResponse;

    public function setUp(): void
    {
        $this->swooleResponse = $this->prophesize(SwooleHttpResponse::class);
        $this->emitter = new SwooleResponseConverter($this->swooleResponse->reveal());
    }

    public function testEmit(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');
        $this->emitter->send($response);
        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Type', 'text/plain')
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->end()
            ->shouldHaveBeenCalled();
    }

    public function testMultipleHeaders(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Length', '256');
        $this->emitter->convertFromPsr7Response($response);
        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Type', 'text/plain')
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Length', '256')
            ->shouldHaveBeenCalled();
    }

    public function testMultipleSetCookieHeaders(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz')
            ->withAddedHeader(
                'Set-Cookie',
                'baz=qux; Domain=somecompany.co.uk; Path=/; Expires=Wed, 09 Jun 2021 10:18:14 GMT; Secure; HttpOnly'
            );
        $this->emitter->convertFromPsr7Response($response);
        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Set-Cookie', Argument::any())
            ->shouldNotBeCalled();
        $this->swooleResponse
            ->cookie('foo', 'bar', 0, '/', '', false, false)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->cookie('bar', 'baz', 0, '/', '', false, false)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->cookie(
                'baz',
                'qux',
                1623233894,
                '/',
                'somecompany.co.uk',
                true,
                true
            )
            ->shouldHaveBeenCalled();
    }

    public function testEmitWithBigContentBody(): void
    {
        $content = base64_encode(\random_bytes(SwooleResponseConverter::CHUNK_SIZE)); // CHUNK_SIZE * 1.33333
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write($content);
        $this->emitter->send($response);
        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Type', 'text/plain')
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->write(substr($content, 0, SwooleResponseConverter::CHUNK_SIZE))
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->write(substr($content, SwooleResponseConverter::CHUNK_SIZE))
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->end()
            ->shouldHaveBeenCalled();
    }
}
