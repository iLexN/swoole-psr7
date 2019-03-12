<?php
declare(strict_types=1);

namespace Ilex\SwoolePsr7\Tests;

use Swoole\Http\Request;

class SwooleRequestFactory
{

    public static function create(): Request
    {
        $request = new Request();
        $request->server = [
            "query_string" => "a=b&c=d",
            "request_method" => "GET",
            "request_uri" => "/hello/aaaaa",
            "path_info" => "/hello/aaaaa",
            "request_time" => 1552371700,
            "request_time_float" => 1552371700.793,
            "server_port" => 9501,
            "remote_port" => 33158,
            "remote_addr" => "172.16.238.11",
            "master_time" => 1552371700,
            "server_protocol" => "HTTP/1.0",
        ];

        $request->header = [
            "host" => "swoole.loc",
            "x-real-ip" => "172.16.238.1",
            "connection" => "close",
            "cache-control" => "no-cache",
            "accept" => "*/*",
            "accept-encoding" => "gzip, deflate",
        ];

        return $request;
    }


}