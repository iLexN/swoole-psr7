<?php
declare(strict_types=1);

namespace Ilex\SwoolePsr7\Utility;

use Ilex\SwoolePsr7\Tests\SwooleRequestFactory;
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
        $property->setAccessible(true);
        $uri = $property->getValue($object);

        self::assertInstanceOf(UriInterface::class, $uri);
    }

    public function testInvoke()
    {
        $factory = new Psr17Factory();
        $testObject = new ParseUriFromSwoole($factory);

        $request = SwooleRequestFactory::create();

        $uri = $testObject($request);

        self::assertEquals('/hello/aaaaa', $uri->getPath());
        self::assertEquals('swoole.loc', $uri->getHost());
        self::assertEquals('a=b&c=d',$uri->getQuery());
        self::assertEquals('http',$uri->getScheme());
    }
}
