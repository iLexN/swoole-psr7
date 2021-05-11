<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Utility;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

final class ParseUploadedFiles
{
    public function __construct(private UploadedFileFactoryInterface $uploadedFileFactory, private StreamFactoryInterface $streamFactory)
    {
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
                foreach ($k as $singleK) {
                    $subArray[$singleK]['name'] = $uploadedFile['name'][$singleK];
                    $subArray[$singleK]['type'] = $uploadedFile['type'][$singleK];
                    $subArray[$singleK]['tmp_name'] = $uploadedFile['tmp_name'][$singleK];
                    $subArray[$singleK]['error'] = $uploadedFile['error'][$singleK];
                    $subArray[$singleK]['size'] = $uploadedFile['size'][$singleK];
                    $parsed[$field] = $this->parseUploadedFiles($subArray);
                }
            }
        }
        return $parsed;
    }
}
