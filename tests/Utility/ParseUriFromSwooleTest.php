<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Tests\Utility;

use Ilex\SwoolePsr7\Tests\SwooleRequestFactory;
use Ilex\SwoolePsr7\Utility\ParseUriFromSwoole;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use ReflectionClass;

class ParseUriFromSwooleTest extends TestCase
{
    public function testConstruct(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUriFromSwoole($factory);
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty('uri');
        $uri = $property->getValue($object);

        self::assertInstanceOf(UriInterface::class, $uri);
    }

    public function testInvoke(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();

        $uri = $testObject($request);

        self::assertEquals('/hello/aaaaa', $uri->getPath());
        self::assertEquals('swoole.loc', $uri->getHost());
        self::assertEquals('a=b&c=d', $uri->getQuery());
        self::assertEquals('http', $uri->getScheme());
    }

    public function testInvokeWithHttps(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['https'] = 'on';

        $uri = $testObject($request);

        self::assertEquals('https', $uri->getScheme());
    }

    public function testInvokeWithHttpsOff(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['https'] = 'off';

        $uri = $testObject($request);

        self::assertEquals('http', $uri->getScheme());
    }

    public function testInvokeWithServerName(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        unset($request->server['http_host']);
        $request->server['server_name'] = 'example.com';

        $uri = $testObject($request);

        self::assertEquals('example.com', $uri->getHost());
    }

    public function testInvokeWithServerAddr(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        unset($request->server['http_host']);
        unset($request->server['server_name']);
        $request->server['server_addr'] = '192.168.1.1';

        $uri = $testObject($request);

        self::assertEquals('192.168.1.1', $uri->getHost());
    }

    public function testInvokeWithHeaderHost(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        unset($request->server['http_host']);
        unset($request->server['server_name']);
        unset($request->server['server_addr']);
        $request->header['host'] = 'example.com';

        $uri = $testObject($request);

        self::assertEquals('example.com', $uri->getHost());
    }

    public function testInvokeWithIPv6(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['http_host'] = '[::1]:8080';

        $uri = $testObject($request);

        self::assertEquals('[::1]', $uri->getHost());
        self::assertEquals(8080, $uri->getPort());
    }

    public function testInvokeWithIPv6NoPort(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['http_host'] = '[::1]';

        $uri = $testObject($request);

        self::assertEquals('[::1]', $uri->getHost());
    }

    public function testInvokeWithHeaderIPv6(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        unset($request->server['http_host']);
        $request->header['host'] = '[2001:db8::1]:443';

        $uri = $testObject($request);

        self::assertEquals('[2001:db8::1]', $uri->getHost());
        self::assertEquals(443, $uri->getPort());
    }

    public function testInvokeWithQueryStringInRequestUri(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['request_uri'] = '/path?foo=bar&baz=qux';

        $uri = $testObject($request);

        self::assertEquals('/path', $uri->getPath());
        self::assertEquals('foo=bar&baz=qux', $uri->getQuery());
    }

    public function testInvokeWithQueryStringFallback(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['request_uri'] = '/path';
        $request->server['query_string'] = 'fallback=query';

        $uri = $testObject($request);

        self::assertEquals('/path', $uri->getPath());
        self::assertEquals('fallback=query', $uri->getQuery());
    }

    public function testInvokeWithNoQueryString(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['request_uri'] = '/path';
        unset($request->server['query_string']);

        $uri = $testObject($request);

        self::assertEquals('/path', $uri->getPath());
        self::assertEquals('', $uri->getQuery());
    }

    public function testInvokeWithDefaultPort(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['http_host'] = 'example.com:8080';
        unset($request->server['server_port']);

        $uri = $testObject($request);

        self::assertEquals('example.com', $uri->getHost());
        self::assertEquals(8080, $uri->getPort());
    }

    public function testInvokeWithHttpsDefaultPort(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['https'] = 'on';
        $request->server['http_host'] = 'example.com:443';
        unset($request->server['server_port']);

        $uri = $testObject($request);

        self::assertEquals('example.com', $uri->getHost());
        self::assertNull($uri->getPort());
    }

    public function testInvokeWithServerPortFallback(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['http_host'] = 'example.com';
        $request->server['server_port'] = 8080;

        $uri = $testObject($request);

        self::assertEquals('example.com', $uri->getHost());
        self::assertEquals(8080, $uri->getPort());
    }

    public function testInvokeWithHeaderDefaultPort(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        unset($request->server['http_host']);
        $request->header['host'] = 'example.com:80';
        $request->server['server_port'] = 8080;

        $uri = $testObject($request);

        self::assertEquals('example.com', $uri->getHost());
        self::assertEquals(8080, $uri->getPort());
    }

    public function testInvokeWithMalformedHost(): void
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();
        $request->server['http_host'] = ':8080';

        $uri = $testObject($request);

        self::assertEquals('', $uri->getHost());
    }
}
