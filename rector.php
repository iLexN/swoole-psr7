<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::CODING_STYLE,
        SetList::PHP_80,
        SetList::PHP_81,
        SetList::PSR_4,
        SetList::EARLY_RETURN,
        SetList::NAMING,
    ]);


    $rectorConfig->skip([
        Rector\Php80\Rector\FunctionLike\UnionTypesRector::class
    ]);

};
