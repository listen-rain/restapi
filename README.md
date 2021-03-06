## Restapi For Laravel5

> 🚀`Laravel Restapi` is a package for http request, base from guzzle/http 

[![Latest Stable Version](https://poser.pugx.org/listen/restapi/v/stable)](https://packagist.org/packages/listen/restapi)
[![Total Downloads](https://poser.pugx.org/listen/restapi/downloads)](https://packagist.org/packages/listen/restapi)
[![Latest Unstable Version](https://poser.pugx.org/listen/restapi/v/unstable)](https://packagist.org/packages/listen/restapi)
[![License](https://poser.pugx.org/listen/restapi/license)](https://github.com/listen-rain/restapi/blob/master/LICENSE)
[![Monthly Downloads](https://poser.pugx.org/listen/restapi/d/monthly)](https://packagist.org/packages/listen/restapi)
[![Daily Downloads](https://poser.pugx.org/listen/restapi/d/daily)](https://packagist.org/packages/listen/restapi)
[![composer.lock](https://poser.pugx.org/listen/restapi/composerlock)](https://packagist.org/packages/listen/restapi)

##  How To Usage

install use the composer 
```
composer require listen/restapi
```

update config/app.php
```
'providers' => [
    ......
    Listen\Restapi\RestapiServiceProvider::class,
],
    
'aliases' => [
    ......
    'Restapi'      => Listen\Restapi\Facades\Restapi::class,
],
```

publish config
```sh
php artisan vendor:publish --provider='Listen\Restapi\RestapiServiceProvider'

# The config file restapi.php while in config drictory 
```

configure
```php
return [
    'request_timeout' => 5,
  
    'connect_timeout' => 5,
    
    'secret'          => '',
    
    'concurrency'     => 5,
    
    'log_file'        => storage_path('logs/restapi.log'),
    
    'log_channel'     => 'restapi',
    
    'log_mode'        => 'single',

    '<MODULE-NAME>' => [
        'secret'   => env('RESTAPI_<MODULE-NAME>_KEY', ''),
        'base_uri' => env('RESTAPI_<MODULE-NAME>_URL', 'http://local.application.com'),
    ],
];
```

single request example
```php
# GET
Restapi::get($moduleName, $uri, $params, $headers);

# POST
Restapi::post($moduleName, $uri, $params, $headers);

# GETASYNC
Restapi::getAsync($moduleName, $uri, ['name' => 'listen'], function ($response, $module, $params, $uri) {
    dd($response);
}, function ($e, $module, $params, $uri) {
    dd($e->getMessage());
});

# POSTASYNC
Restapi::postAsync($moduleName, $uri, ['name' => 'listen'], function ($response, $module, $params, $uri) {
    dd($response);
}, function ($e, $module, $params, $uri) {
    dd($e->getMessage());
});
        
```

multi request example
```php
# An interface is requested multiple times
$params = [
    [
        'user_id' => 1,
        'user_name' => 'new name'
    ],
    [
        'user_id' => 2,
        'user_name' => 'new name2'
    ]
];

$responses = \Restapi::multiRequest('post', 'http://test.local/user', $params, ['Content-Type' => 'application/x-www-form-urlencoded']);
dd($responses);

# Multiple interfaces are requested concurrently
$apis = [
    [
        'module' => 'user',
        'method' => 'postAsync',
        'params' => ['key' => 'value'],
        'uri'    => 'http://test.local/user'
    ],
    [
        'module' => 'book',
        'method' => 'postAsync',
        'params' => ['key' => 'value'],
        'uri'    => 'http://test.local/book'
    ],
];
$result = \Restapi::multiModuleRequest($apis);
dd($result);
```
