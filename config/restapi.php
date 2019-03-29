<?php

return [
    // 请求超时时间
    'request_timeout' => 5,
    // 连接超时时间
    'connect_timeout' => 5,
    // secret
    'secret'          => '',
    // 并发请求数
    'concurrency'     => 5,
    // 请求接口的log文件地址
    'log_file'        => storage_path('logs/restapi.log'),
    // 日志频道
    'log_channel'     => 'restapi',
    // 日志模式
    'log_mode'        => 'single'
];
