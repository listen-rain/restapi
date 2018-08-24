###海边项目调用接口插件
##简介
这是一个可以简单实现接口调用和接口验证的插件，适合于laravl框架。

##安装
如你的框架里面 vertor/haibian/rest-api-service-provider 已有这个插件，直接使用就好。

如果你使用时框架还没有这个插件：

	1.请更新最新的框架项目，将vertor/haibian/rest-api-service-provider文件夹拷贝到你使用框架的相应位置。
	
	2。修改composer.json文件 在 autoload psr-4 里面加上"Haibian\\Restapi\\": "vendor/haibian/rest-api-service-provider/src"
```json
	"autoload": {
        "classmap": [
            "database"
        ],
        "psr-4": {
            "App\\": "app/",
            "Haibian\\Restapi\\": "vendor/haibian/rest-api-service-provider/src"
        }
    },
``` 
	3.运行 composer dump-autoload 从新生成autoload的文件
	
	4.在config里面添加 restapi.php 配置文件
```php
return [

	 //passport 的 配置文件
    'passport' => [
        'secret' => 'OhG4yrqtoH422mYQ58WFpIURZxCQRbJl',
        'test_base_uri'   => 'http://test.passport.haibian.com',
        'base_uri'  => 'http://passport.haibian.com'
    ],

	//项目加密的secret
    'secret' => 'OhG4yrqtoH422mYQ58WFpIURZxCQRbJl'

];
```
	4.修改config/app.php
		在providers数组里面添加
			Haibian\Restapi\RestapiServiceProvider::class,
		在aliases数组添加
			'Restapi' => Haibian\Restapi\Facades\Restapi::class,
##使用
	1.如果你提供接口只用在你需要验证的接口上添加
		\Haibian\Restapi\Middleware\RestapiBeforeRequest::class 中间件即可
	
	2.如果你是调用接口提供两个方法
		(1) Restapi::get($module,$uri,$params) 单个接口调用
		试例：
```php
$respone = Restapi::get('passport','/v1/server/login',
            [
                'product' => 'ss',
                'mobile' => '07000010526447',
                'password' => '25d55ad283aa400af464c76d713c07ad',
                'terminal' => '1',
            ]
        );
```
		(2) Restapi::get($module,$uri,$params) 批量异步调用
```php
$respone = Restapi::mget(
            [
                [
                    'module' => 'passport',
                    'uri' => '/v1/server/login',
                    'params' => [
                        'product' => 'ss',
                        'mobile' => '07000010526447',
                        'password' => '25d55ad283aa400af464c76d713c07ad',
                        'terminal' => '1',
                    ]
                ],
                [
                    'module' => 'passport',
                    'uri' => '/v1/server/login',
                    'params' => [
                        'product' => 'ss',
                        'mobile' => '07000010526447',
                        'password' => '25d55ad283aa400af464c76d713c07ad',
                        'terminal' => '1',
                    ]
                ],
            ]
        );
```
##其他
如有bug或者没说清楚的及时联系
