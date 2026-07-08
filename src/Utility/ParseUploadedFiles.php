<?php

declare(strict_types=1);

namespace Ilex\SwoolePsr7\Utility;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;

final readonly class ParseUploadedFiles
{

    public function __construct(
        private UploadedFileFactoryInterface $uploadedFileFactory,
        private StreamFactoryInterface $streamFactory
    ) {
    }

    /**
     * Parse a non-normalized, i.e. $_FILES super global, tree of uploaded file
     * data.
     *
     * @param array<int|string, mixed> $uploadedFiles The non-normalized tree of uploaded file
     *     data.
     *
     * @return array<int|string, mixed> A normalized tree of UploadedFile instances.
     */
    public function parseUploadedFiles(array $uploadedFiles): array
    {
        $parsed = [];
        foreach ($uploadedFiles as $field => $uploadedFile) {
            /** @var array<int|string, mixed> $uploadedFile */
            if (!isset($uploadedFile['error'])) {
                $parsed[$field] = $this->parseUploadedFiles($uploadedFile);

                continue;
            }

            if (!is_array($uploadedFile['error'])) {
                /** @var string $tmpName */
                $tmpName = $uploadedFile['tmp_name'];
                /** @var int $error */
                $error = $uploadedFile['error'];
                /** @var int|string|null $rawSize */
                $rawSize = $uploadedFile['size'] ?? null;
                /** @var string|null $rawName */
                $rawName = $uploadedFile['name'] ?? null;
                /** @var string|null $rawType */
                $rawType = $uploadedFile['type'] ?? null;


                $parsed[$field] = $this->uploadedFileFactory->createUploadedFile(
                    $this->streamFactory->createStream($tmpName),
                    $rawSize !== null ? (int) $rawSize : null,
                    $error,
                    $rawName,
                    $rawType
                );
            } else {
                /** @var array<int|string, mixed> $errorArr */
                $errorArr = $uploadedFile['error'];

                $nameArr = $uploadedFile['name'] ?? [];
                $typeArr = $uploadedFile['type'] ?? [];
                $tmpNameArr = $uploadedFile['tmp_name'] ?? [];
                $sizeArr = $uploadedFile['size'] ?? [];
                if (!is_array($nameArr)) {
                    continue;
                }

                if (!is_array($typeArr)) {
                    continue;
                }

                if (!is_array($tmpNameArr)) {
                    continue;
                }

                if (!is_array($sizeArr)) {
                    continue;
                }

                $keys = array_keys($errorArr);
                $subArray = [];
                foreach ($keys as $key) {
                    if (!array_key_exists($key, $nameArr)) {
                        continue;
                    }

                    if (!array_key_exists($key, $typeArr)) {
                        continue;
                    }

                    if (!array_key_exists($key, $tmpNameArr)) {
                        continue;
                    }

                    if (!array_key_exists($key, $sizeArr)) {
                        continue;
                    }

                    $subArray[$key]['name'] = is_string($nameArr[$key]) ? $nameArr[$key] : '';
                    $subArray[$key]['type'] = is_string($typeArr[$key]) ? $typeArr[$key] : '';
                    $subArray[$key]['tmp_name'] = is_string($tmpNameArr[$key]) ? $tmpNameArr[$key] : '';
                    $subArray[$key]['error'] = is_int($errorArr[$key]) ? $errorArr[$key] : UPLOAD_ERR_NO_FILE;
                    $subArray[$key]['size'] = is_int($sizeArr[$key]) || is_string($sizeArr[$key]) ? (int)$sizeArr[$key] : 0;
                }

                $parsed[$field] = $this->parseUploadedFiles($subArray);
            }
        }

        return $parsed;
    }
}
