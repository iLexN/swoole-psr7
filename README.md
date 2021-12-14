# SwoolePsr7

[![Latest Stable Version](https://poser.pugx.org/ilexn/swoole-convert-psr7/v/stable)](https://packagist.org/packages/ilexn/swoole-convert-psr7)
[![Total Downloads](https://poser.pugx.org/ilexn/swoole-convert-psr7/downloads)](https://packagist.org/packages/ilexn/swoole-convert-psr7)

![CI Check](https://github.com/iLexN/swoole-psr7/workflows/CI%20Check/badge.svg)
[![Coverage Status](https://coveralls.io/repos/github/iLexN/swoole-psr7/badge.svg?branch=main)](https://coveralls.io/github/iLexN/swoole-psr7?branch=main)

Use any PSR 17 Factory to convert to PSR 7 Response/Request.

**Note: not production use yet. Just for leaning.**

**Start from 0.5.0, CI test also include swoole and openswoole**

## Install

Via Composer

``` bash
$ composer require ilexn/swoole-convert-psr7
```

## Upgrade from old package
Remove the old package
``` bash
$ composer remove ilexn/swoole-convent-psr7
```
Install the new package
``` bash
$ composer require ilexn/swoole-convert-psr7
```
Two package using the same namespace, no other code need to change.

## Usage
use [Slim 4](https://github.com/slimphp/Slim) and [Nyholm/psr7](https://github.com/Nyholm/psr7) as example.
``` php
<?php
declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;
use Swoole\Http\Request;

include 'vendor/autoload.php';

$http = new swoole_http_server('0.0.0.0', 9501);
$psr17Factory = new Psr17Factory();


$serverRequestFactory = new \Ilex\SwoolePsr7\SwooleServerRequestConverter(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory
);

$app = new Slim\App($psr17Factory);
$app->get('/hello/{name}', function ($request, ResponseInterface $response, $args) {
    //dump($args);
    $response->getBody()->write("Hello, " . $args['name']);
    return $response->withHeader('X-Powered-By','ilexn');
});

$http->on('start', function ($server) {
    echo "Swoole http server is started at http://127.0.0.1:9501\n";
});

$http->on('request',
    function (Request $request, Response $response) use ($serverRequestFactory , $app
    ) {
        $psr7Request = $serverRequestFactory->createFromSwoole($request);
        $psr7Response = $app->handle($psr7Request);
        $converter = new \Ilex\SwoolePsr7\SwooleResponseConverter($response);
        $converter->send($psr7Response);
    });

$http->start();

```

## Reference
- https://github.com/slimphp/Slim/tree/4.x
- https://github.com/slimphp/Slim-Psr7
- https://github.com/swoft-cloud/swoft-http-message
- https://github.com/zendframework/zend-expressive-swoole

