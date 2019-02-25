<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Utility;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

final class ParseUploadedFiles
{
    /**
     * @var \Psr\Http\Message\UploadedFileFactoryInterface
     */
    private $uploadedFileFactory;

    /**
     * @var \Psr\Http\Message\StreamFactoryInterface
     */
    private $streamFactory;

    public function __construct(UploadedFileFactoryInterface $uploadedFileFactory, StreamFactoryInterface $streamFactory)
    {
        $this->uploadedFileFactory = $uploadedFileFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Parse a non-normalized, i.e. $_FILES super global, tree of uploaded file
     * data.
     *
     * @param array $uploadedFiles The non-normalized tree of uploaded file
     *     data.
     *
     * @return array A normalized tree of UploadedFile instances.
     */
    public function parseUploadedFiles(array $uploadedFiles): array
    {
        $parsed = [];
        foreach ($uploadedFiles as $field => $uploadedFile) {
            if (!isset($uploadedFile['error'])) {
                if (is_array($uploadedFile)) {
                    $parsed[$field] = $this->parseUploadedFiles($uploadedFile);
                }
                continue;
            }
            $parsed[$field] = [];
            if (!is_array($uploadedFile['error'])) {
                $parsed[$field] = $this->uploadedFileFactory->createUploadedFile(
                    $this->streamFactory->createStream($uploadedFile['tmp_name']),
                    $uploadedFile['size'] ?? null,
                    $uploadedFile['error'],
                    $uploadedFile['name'] ?? null,
                    $uploadedFile['type'] ?? null
                );
            } else {
                $subArray = [];
                $k = array_keys($uploadedFile['error']);
                foreach ($k as $fileIdx) {
                    $subArray[$fileIdx]['name'] = $uploadedFile['name'][$fileIdx];
                    $subArray[$fileIdx]['type'] = $uploadedFile['type'][$fileIdx];
                    $subArray[$fileIdx]['tmp_name'] = $uploadedFile['tmp_name'][$fileIdx];
                    $subArray[$fileIdx]['error'] = $uploadedFile['error'][$fileIdx];
                    $subArray[$fileIdx]['size'] = $uploadedFile['size'][$fileIdx];
                    $parsed[$field] = $this->parseUploadedFiles($subArray);
                }
            }
        }
        return $parsed;
    }
}
