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

example
```php
# GET
Restapi::get($modelName, $uri, $params, $headers);

# POST
Restapi::get($modelName, $uri, $params, $headers);
```
