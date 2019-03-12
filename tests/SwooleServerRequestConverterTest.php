<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Tests;

use Ilex\SwoolePsr7\SwooleServerRequestConverter;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use ReflectionClass;
use Swoole\Http\Request;

class SwooleServerRequestConverterTest extends TestCase
{
    public function testConstruct()
    {
        $factory = new Psr17Factory();
        $testClass = new SwooleServerRequestConverter(
            $factory,
            $factory,
            $factory,
            $factory
        );
        $reflection = new ReflectionClass($testClass);

        $property = $reflection->getProperty('serverRequestFactory');
        $property->setAccessible(true);
        $serverRequestFactory = $property->getValue($testClass);
        self::assertInstanceOf(ServerRequestFactoryInterface::class, $serverRequestFactory);

        $property = $reflection->getProperty('uriFactory');
        $property->setAccessible(true);
        $uriFactory = $property->getValue($testClass);
        self::assertInstanceOf(UriFactoryInterface::class, $uriFactory);

        $property = $reflection->getProperty('uploadedFileFactory');
        $property->setAccessible(true);
        $uploadedFileFactory = $property->getValue($testClass);
        self::assertInstanceOf(UploadedFileFactoryInterface::class, $uploadedFileFactory);

        $property = $reflection->getProperty('streamFactory');
        $property->setAccessible(true);
        $streamFactory = $property->getValue($testClass);
        self::assertInstanceOf(StreamFactoryInterface::class, $streamFactory);
    }

    public function testCreateFromSwoole()
    {
        $factory = new Psr17Factory();
        $testClass = new SwooleServerRequestConverter(
            $factory,
            $factory,
            $factory,
            $factory
        );

        $swooleRequest = $this->createMock(Request::class);
        $swooleRequest->server = [
            'path_info' => '/',
            'remote_port' => 45314,
            'request_method' => 'POST',
            'REQUEST_TIME' => time(),
            'request_uri' => '/some/path',
            'server_port' => 9501,
            'server_protocol' => 'HTTP/2',
        ];
        $swooleRequest->get = [
            'foo' => 'bar',
        ];
        $swooleRequest->post = [
            'bar' => 'baz',
        ];
        $swooleRequest->cookie = [
            'yummy_cookie' => 'choco',
            'tasty_cookie' => 'strawberry',
        ];
        $swooleRequest->files = [
            [
                'tmp_name' => __FILE__,
                'size' => filesize(__FILE__),
                'error' => UPLOAD_ERR_OK,
            ],
        ];
        $swooleRequest->header = [
            'Accept' => 'application/*+json',
            'Content-Type' => 'application/json',
            'Cookie' => 'yummy_cookie=choco; tasty_cookie=strawberry',
            'host' => 'localhost:9501',
        ];
        $swooleRequest->method('rawContent')->willReturn('this is the content');

        $request = $testClass->createFromSwoole($swooleRequest);

        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertEquals('2', $request->getProtocolVersion());
        $this->assertEquals('POST', $request->getMethod());
        $this->assertTrue($request->hasHeader('Accept'));
        $this->assertEquals('application/*+json', $request->getHeaderLine('Accept'));
        $this->assertTrue($request->hasHeader('Content-Type'));
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertTrue($request->hasHeader('Host'));
        $this->assertEquals('localhost:9501', $request->getHeaderLine('Host'));
        $this->assertTrue($request->hasHeader('Cookie'));
        $this->assertEquals('yummy_cookie=choco; tasty_cookie=strawberry', $request->getHeaderLine('Cookie'));
        $this->assertEquals(['foo' => 'bar'], $request->getQueryParams());
        $this->assertEquals(['bar' => 'baz'], $request->getParsedBody());
        $this->assertEquals(
            ['yummy_cookie' => 'choco', 'tasty_cookie' => 'strawberry'],
            $request->getCookieParams()
        );
        $uri = $request->getUri();
        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals('localhost', $uri->getHost());
        $this->assertEquals(9501, $uri->getPort());
        $this->assertEquals('/some/path', $uri->getPath());
        $uploadedFiles = $request->getUploadedFiles();
        $this->assertCount(1, $uploadedFiles);
        /** @var UploadedFileInterface $uploadedFile */
        $uploadedFile = array_shift($uploadedFiles);
        $this->assertInstanceOf(UploadedFileInterface::class, $uploadedFile);
        $this->assertEquals(filesize(__FILE__), $uploadedFile->getSize());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->getError());
        $body = $request->getBody();
        $this->assertInstanceOf(StreamInterface::class, $body);
        $this->assertEquals('this is the content', (string) $body);
    }
}
