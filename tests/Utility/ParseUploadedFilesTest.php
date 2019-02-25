<?php

declare(strict_types=1);

namespace Ilex\SwooleServer\Tests\Utility;

use Ilex\SwooleServer\Utility\ParseUploadedFiles;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;
use ReflectionClass;

class ParseUploadedFilesTest extends TestCase
{
    public function testConstruct(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty('uploadedFileFactory');
        $property->setAccessible(true);
        $uploadedFileFactory = $property->getValue($object);

        $property2 = $reflection->getProperty('streamFactory');
        $property2->setAccessible(true);
        $streamFactory = $property2->getValue($object);

        self::assertEquals($uploadedFileFactory, $factory);
        self::assertEquals($streamFactory, $factory);
    }

    public function testParseUploadedFiles(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => [
                    0 => 'foo.jpg',
                    1 => 'bar.jpg',
                ],
                'type' => [
                    0 => 'image/jpeg',
                    1 => 'image/jpeg',
                ],
                'tmp_name' => [
                    0 => '/tmp/phpUA3XUw',
                    1 => '/tmp/phpXUFS0x',
                ],
                'error' => [
                    0 => 0,
                    1 => 0,
                ],
                'size' => [
                    0 => 358708,
                    1 => 236162,
                ],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertInstanceOf(
            UploadedFileInterface::class,
            $uploadedFiles['uploaded_file'][0]
        );
        self::assertInstanceOf(
            UploadedFileInterface::class,
            $uploadedFiles['uploaded_file'][1]
        );

        self::assertCount(1, $uploadedFiles);
    }
}
