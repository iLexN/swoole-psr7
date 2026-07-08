<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Tests\Utility;

use Ilex\SwoolePsr7\Utility\ParseUploadedFiles;
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
        $uploadedFileFactory = $property->getValue($object);

        $property2 = $reflection->getProperty('streamFactory');
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

    public function testParseSingleFile(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'tmp_name' => '/tmp/phpUA3XUw',
                'size' => 358708,
                'error' => UPLOAD_ERR_OK,
                'name' => 'foo.jpg',
                'type' => 'image/jpeg',
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertInstanceOf(
            UploadedFileInterface::class,
            $uploadedFiles['uploaded_file']
        );
        self::assertEquals('foo.jpg', $uploadedFiles['uploaded_file']->getClientFilename());
        self::assertEquals('image/jpeg', $uploadedFiles['uploaded_file']->getClientMediaType());
        self::assertEquals(358708, $uploadedFiles['uploaded_file']->getSize());
        self::assertEquals(UPLOAD_ERR_OK, $uploadedFiles['uploaded_file']->getError());
    }

    public function testParseNestedFiles(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'files' => [
                'document' => [
                    'name' => [
                        0 => 'resume.pdf',
                        1 => 'cover.pdf',
                    ],
                    'type' => [
                        0 => 'application/pdf',
                        1 => 'application/pdf',
                    ],
                    'tmp_name' => [
                        0 => '/tmp/php1',
                        1 => '/tmp/php2',
                    ],
                    'error' => [
                        0 => 0,
                        1 => 0,
                    ],
                    'size' => [
                        0 => 1000,
                        1 => 2000,
                    ],
                ],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertInstanceOf(
            UploadedFileInterface::class,
            $uploadedFiles['files']['document'][0]
        );
        self::assertInstanceOf(
            UploadedFileInterface::class,
            $uploadedFiles['files']['document'][1]
        );
    }

    public function testParseFileWithMissingOptionalKeys(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'tmp_name' => '',
                'error' => UPLOAD_ERR_OK,
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertInstanceOf(
            UploadedFileInterface::class,
            $uploadedFiles['uploaded_file']
        );
        self::assertNull($uploadedFiles['uploaded_file']->getClientFilename());
        self::assertNull($uploadedFiles['uploaded_file']->getClientMediaType());
        self::assertEquals(0, $uploadedFiles['uploaded_file']->getSize());
    }

    public function testParseFileWithError(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'tmp_name' => '/tmp/phpUA3XUw',
                'size' => 0,
                'error' => UPLOAD_ERR_NO_FILE,
                'name' => 'foo.jpg',
                'type' => 'image/jpeg',
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertInstanceOf(
            UploadedFileInterface::class,
            $uploadedFiles['uploaded_file']
        );
        self::assertEquals(UPLOAD_ERR_NO_FILE, $uploadedFiles['uploaded_file']->getError());
    }

    public function testParseFileWithInvalidArrayStructure(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => 'not an array',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php1',
                'error' => [0 => 0],
                'size' => [0 => 1000],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertArrayNotHasKey('uploaded_file', $uploadedFiles);
    }

    public function testParseFileWithMissingArrayKeys(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => [0 => 'foo.jpg'],
                'type' => [0 => 'image/jpeg'],
                'tmp_name' => [0 => '/tmp/php1'],
                'error' => [0 => 0],
                'size' => [1 => 1000],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertArrayHasKey('uploaded_file', $uploadedFiles);
        self::assertArrayNotHasKey(0, $uploadedFiles['uploaded_file']);
    }

    public function testParseEmptyArray(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $uploadedFiles = $object->parseUploadedFiles([]);
        self::assertEquals([], $uploadedFiles);
    }

    public function testParseFileWithStringSize(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => [0 => 'foo.jpg'],
                'type' => [0 => 'image/jpeg'],
                'tmp_name' => [0 => '/tmp/php1'],
                'error' => [0 => 0],
                'size' => [0 => '1000'],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertInstanceOf(
            UploadedFileInterface::class,
            $uploadedFiles['uploaded_file'][0]
        );
        self::assertEquals(1000, $uploadedFiles['uploaded_file'][0]->getSize());
    }

    public function testParseFileWithNameNotArraySkipsEntry(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => 'not an array',
                'type' => [0 => 'image/jpeg'],
                'tmp_name' => [0 => '/tmp/php1'],
                'error' => [0 => 0],
                'size' => [0 => 1000],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertArrayNotHasKey('uploaded_file', $uploadedFiles);
    }

    public function testParseFileWithTypeNotArraySkipsEntry(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => [0 => 'foo.jpg'],
                'type' => 'not an array',
                'tmp_name' => [0 => '/tmp/php1'],
                'error' => [0 => 0],
                'size' => [0 => 1000],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertArrayNotHasKey('uploaded_file', $uploadedFiles);
    }

    public function testParseFileWithTmpNameNotArraySkipsEntry(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => [0 => 'foo.jpg'],
                'type' => [0 => 'image/jpeg'],
                'tmp_name' => 'not an array',
                'error' => [0 => 0],
                'size' => [0 => 1000],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertArrayNotHasKey('uploaded_file', $uploadedFiles);
    }

    public function testParseFileWithSizeNotArraySkipsEntry(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => [0 => 'foo.jpg'],
                'type' => [0 => 'image/jpeg'],
                'tmp_name' => [0 => '/tmp/php1'],
                'error' => [0 => 0],
                'size' => 'not an array',
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertArrayNotHasKey('uploaded_file', $uploadedFiles);
    }

    public function testParseFileWithMissingKeyNameSkipsEntry(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => [1 => 'foo.jpg'],
                'type' => [0 => 'image/jpeg'],
                'tmp_name' => [0 => '/tmp/php1'],
                'error' => [0 => 0],
                'size' => [0 => 1000],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertArrayHasKey('uploaded_file', $uploadedFiles);
        self::assertArrayNotHasKey(0, $uploadedFiles['uploaded_file']);
    }

    public function testParseFileWithMissingKeyTypeSkipsEntry(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => [0 => 'foo.jpg'],
                'type' => [1 => 'image/jpeg'],
                'tmp_name' => [0 => '/tmp/php1'],
                'error' => [0 => 0],
                'size' => [0 => 1000],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertArrayHasKey('uploaded_file', $uploadedFiles);
        self::assertArrayNotHasKey(0, $uploadedFiles['uploaded_file']);
    }

    public function testParseFileWithMissingKeyTmpNameSkipsEntry(): void
    {
        $factory = new Psr17Factory();
        $object = new ParseUploadedFiles($factory, $factory);

        $files = [
            'uploaded_file' => [
                'name' => [0 => 'foo.jpg'],
                'type' => [0 => 'image/jpeg'],
                'tmp_name' => [1 => '/tmp/php1'],
                'error' => [0 => 0],
                'size' => [0 => 1000],
            ],
        ];

        $uploadedFiles = $object->parseUploadedFiles($files);
        self::assertArrayHasKey('uploaded_file', $uploadedFiles);
        self::assertArrayNotHasKey(0, $uploadedFiles['uploaded_file']);
    }
}
