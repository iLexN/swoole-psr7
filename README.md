# SwoolePsr7

[![Latest Stable Version](https://poser.pugx.org/ilexn/swoole-convent-psr7/v/stable)](https://packagist.org/packages/ilexn/swoole-convent-psr7)
[![Total Downloads](https://poser.pugx.org/ilexn/swoole-convent-psr7/downloads)](https://packagist.org/packages/ilexn/swoole-convent-psr7)

![CI Check](https://github.com/iLexN/swoole-psr7/workflows/CI%20Check/badge.svg)
[![Coverage Status](https://coveralls.io/repos/github/iLexN/swoole-psr7/badge.svg?branch=master)](https://coveralls.io/github/iLexN/swoole-psr7?branch=master)

Use any PSR 17 Factory to convent to PSR 7 Response/Request.

note: not production use yet. Just for leaning.

## Install

Via Composer

``` bash
$ composer require ilexn/swoole-convent-psr7
```

## Usage
use Slim 4 and Nyholm\Psr7\Factory\Psr17Factory as example
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

