<?php

namespace Listen\Restapi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use Illuminate\Contracts\Config\Repository;
use Listen\Restapi\Exceptions\RestapiException;
use phpDocumentor\Reflection\Types\Array_;
use Psr\Http\Message\ResponseInterface;

class Restapi
{
    use CallbackTrait {
        CallbackTrait::__construct as private callbackConstruct;
    }

    const BASENAME = 'restapi.';

    /**
     * @var array
     */
    protected $exceptionCallbacks = [];

    /**
     * @var \Listen\LogCollector\Logger
     */
    protected static $logger;

    /**
     * @var string
     */
    protected $signKeyName = 'sign';

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var mixed
     */
    protected $secret;

    /**
     * Restapi constructor.
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    public function __construct(Repository $config)
    {
        $this->config = $config;

        $this->secret = $this->config->get('restapi.secret');
        $this->client = new Client([
                                       'timeout'         => $this->config->get('restapi.request_timeout'),
                                       'connect_timeout' => $this->config->get('restapi.connect_timeout'),
                                       'http_errors'     => true,
                                   ]);

        $this->callbackConstruct();
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param $params
     * @param $secret
     * @return mixed
     */
    protected function addSign($params, $secret)
    {
        if ($secret && !isset($params[$this->signKeyName])) {
            $params[$this->signKeyName] = $this->getSign($params, $secret);
        }

        return $params;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param $params
     * @param $secret
     * @return string
     */
    protected function getSign($params, $secret)
    {
        ksort($params);
        $paramStr = http_build_query($params, null, ini_get('arg_separator.output'), PHP_QUERY_RFC3986);
        return md5(md5($paramStr) . $secret);
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param        $module
     * @param        $uri
     * @param        $params
     * @param array  $headers
     * @param string $action
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get($module, $uri, $params, $headers = [])
    {
        $options = [
            'query'   => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers' => $headers
        ];

        try {
            $response = $this->client->get($this->checkUri($module, $uri), $options);

            return $this->getResponse($module, $params, $response, $uri);
        } catch (RequestException $e) {
            $this->applyCallback($module, $e->getMessage(), $e->getCode(), compact('uri', 'params'));
        }
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param        $module
     * @param        $uri
     * @param        $params
     * @param array  $headers
     * @param string $action
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function post($module, $uri, $params, $headers = [])
    {
        $options = [
            'form_params' => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers'     => $headers
        ];

        try {
            $response = $this->client->post($this->checkUri($module, $uri), $options);

            return $this->getResponse($module, $params, $response, $uri);
        } catch (RequestException $e) {
            $this->applyCallback($module, $e->getMessage(), $e->getCode(), compact('uri', 'params'));
        }
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param        $module
     * @param        $uri
     * @param        $params
     * @param array  $headers
     * @param string $action
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function multipart($module, $uri, $params, $headers = [])
    {
        $options = [
            'multipart' => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers'   => $headers
        ];

        try {
            $response = $this->client->post($this->checkUri($module, $uri), $options);

            return $this->getResponse($module, $params, $response, $uri);
        } catch (RequestException $e) {
            $this->applyCallback($module, $e->getMessage(), $e->getCode(), compact('uri', 'params'));
        }
    }

    /**
     * @date   2019/3/7
     * @author <zhufengwei@aliyun.com>
     * @param               $module
     * @param               $uri
     * @param               $params
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param array         $headers
     */
    public function getAsync($module, $uri, $params, Callable $onFulfilled = null, Callable $onRejected = null, $headers = [])
    {
        $options = [
            'query'   => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers' => $headers
        ];

        $promise = $this->client->getAsync($this->checkUri($module, $uri), $options);
        $this->afterRequest($promise, $module, $uri, $params, $onFulfilled, $onRejected);
    }

    /**
     * @date   2019/3/7
     * @author <zhufengwei@aliyun.com>
     * @param               $module
     * @param               $uri
     * @param               $params
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @param array         $headers
     */
    public function postAsync($module, $uri, $params, Callable $onFulfilled = null, Callable $onRejected = null, $headers = [])
    {
        $options = [
            'form_params' => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers'     => $headers
        ];

        $promise = $this->client->postAsync($this->checkUri($module, $uri), $options);
        $this->afterRequest($promise, $module, $uri, $params, $onFulfilled, $onRejected);
    }

    /**
     * @date   2019/3/7
     * @author <zhufengwei@aliyun.com>
     * @param \GuzzleHttp\Promise\Promise $promise
     * @param                             $module
     * @param                             $uri
     * @param                             $params
     * @param callable|null               $onFulfilled
     * @param callable|null               $onRejected
     */
    protected function afterRequest(Promise $promise, $module, $uri, $params, Callable $onFulfilled = null, Callable $onRejected = null)
    {
        $promise->then(
            function (ResponseInterface $response) use ($onFulfilled, $module, $params, $uri) {
                $onFulfilled($response, $module, $params, $uri);
            },
            function (RequestException $e) use ($onRejected, $module, $params, $uri) {
                $this->applyCallback($module, $e->getMessage(), $e->getCode(), compact('uri', 'params'));
                $onRejected($e, $module, $params, $uri);
            });

        $promise->wait(false);
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param $module
     * @param $params
     * @param $response
     * @param $uri
     * @return bool|mixed
     */
    public function getResponse($module, $params, $response, $uri)
    {
        if (!$response) {
            $this->applyCallback($module, '请求方法不存在！', 404, compact('uri', 'params'));
            return false;
        }

        $code = $response->getStatusCode();
        if ($code != 200) {
            $this->applyCallback($module, '请求出错！', $code, compact('uri', 'params'));
            return false;
        }

        $stringBody = strval($response->getBody());
        $result     = json_decode($stringBody, true);
        if (!$result) {
            $this->applyCallback($module, $stringBody, $code, compact('uri', 'params'));
            return false;
        }

        static::$logger->restapi(compact('module', 'uri', 'params', 'result', 'code'));
        return $result;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param $apis ['module', 'uri', 'method', 'params', 'headers']
     *              Module is Request Name By Custom
     * @return array
     * @throws \Throwable
     */
    public function multiModuleRequest(array $apis): array
    {
        $promises = [];
        foreach ($apis as $key => $api) {
            $uri     = array_get($api, 'uri', '');
            $method  = array_get($api, 'method', 'GET');
            $module  = array_get($api, 'module', '');
            $params  = array_get($api, 'params', []);
            $headers = array_get($api, 'headers', []);
            // 检验模块
            if (!$module) {
                throw new RestapiException('Module Con\'t Be null ! (Module is Request Name By Custom)');
            }
            $this->validMethod($method);
            // 检验URI
            $uri = $this->checkUri($module, $uri);
            // 添加签名
            $secret = array_get($api, 'secret', config('restapi.' . $module . '.secret', ''));
            $params = $this->addSign($api['params'], $secret);
            // 创建请求
            $options           = $this->makeOptions($method, $params);
            $promises[$module] = $this->client->$method($uri, $options);
        }
        // 发送请求
        $results = \GuzzleHttp\Promise\unwrap($promises);
        $outputs = [];
        foreach ($apis as $k => $api) {
            $module           = array_get($api, 'module', 'default');
            $params           = array_get($api, 'params', []);
            $uri              = array_get($api, 'uri', '');
            $uri              = $this->checkUri($module, $uri);
            $outputs[$module] = $this->getResponse($module, $params, $results[$module], $uri);
        }

        return $outputs;
    }

    /**
     * @date   2019/3/29
     * @author <zhufengwei@aliyun.com>
     * @param string        $module
     * @param string        $method
     * @param string        $uri
     * @param array         $params
     * @param array         $headers
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     * @return array
     */
    public function multiRequest(string $method, string $uri, array $params, array $headers = [], Callable $onFulfilled = null, Callable $onRejected = null): array
    {
        $this->validMethod($method, false);
        $uri = $this->checkUri('', $uri);
        if (strtolower($method) === 'post' && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        // 创建请求函数
        $requests = function ($total) use ($uri, $method, $params, $headers) {
            foreach ($params as $param) {
                $body = http_build_query($param);
                $uri  = strtolower($method) !== 'get' ?: $uri . '?' . $body;

                yield new Request($method, $uri, $headers, $body);
            }
        };
        // 初始化响应
        $responses = [
            'fulfilled' => [],
            'rejected'  => []
        ];
        // 设置配置项
        $config = [
            'concurrency' => config('restapi.concurrency'),
            'fulfilled'   => ($onFulfilled ?? function ($response, $index) use (&$responses) {
                    $stringBody               = strval($response->getBody());
                    $responses['fulfilled'][] = json_decode($stringBody, true);
                }),
            'rejected'    => ($onRejected ?? function ($reason, $index) use (&$responses) {
                    $responses['rejected'][] = $reason;
                })
        ];
        // 发送请求
        $pool = new Pool($this->client, $requests(count($params)), $config);
        $pool->promise()->wait();

        return $responses;
    }

    /**
     * @date   2019/3/23
     * @author <zhufengwei@aliyun.com>
     * @param string $module
     * @return string
     */
    protected function getBaseUri(string $module): string
    {
        return $this->uriTrim($this->config->get('restapi.' . $module . '.base_uri', ''));
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param string $name
     * @return $this
     */
    public function setSignKeyName(string $name)
    {
        $this->signKeyName = $name;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param $request
     * @return bool
     */
    public function checkServer($request)
    {
        $inputs = $request->all();
        $path   = $request->path();
        $path   = str_replace('.', '_', $path);
        ltrim('/', $path);
        $path = '/' . $path;
        unset($inputs[$path]);

        if (!isset($inputs[$this->signKeyName])) {
            return false;
        }

        $sign = $inputs[$this->signKeyName];
        unset($inputs[$this->signKeyName]);

        return $sign == $this->getSign($inputs, $this->secret);
    }

    /**
     * @date   2019/3/29
     * @author <zhufengwei@aliyun.com>
     * @param string $module
     * @param string $uri
     * @return string
     * @throws \Exception
     */
    public function checkUri(string $module, string $uri): string
    {
        if (strpos($uri, 'http://') === false && strpos($uri, 'https://') === false) {
            if (!$module || !$baseUri = $this->getBaseUri($module)) {
                throw new \Exception('Uri Is Illegal !');
            }

            $uri = $this->checkUri($module, $baseUri . DIRECTORY_SEPARATOR . $this->uriTrim($uri));
        }

        return $uri;
    }

    /**
     * @date   2019/4/4
     * @author <zhufengwei@aliyun.com>
     * @param string $method
     * @param bool   $syncOnly
     * @throws RestapiException
     */
    public function validMethod(string $method, $syncOnly = true)
    {
        $method = strtolower($method);

        if ($syncOnly && !in_array($method, ['getAsync', 'postAsync', 'headAsync', 'putAsync'])) {
            throw new RestapiException('$method is invalid !');
        }

        if (!in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'getAsync', 'postAsync', 'deleteAsync', 'headAsync', 'optionsAsync', 'putAsync', 'patchAsync'])) {
            throw new RestapiException('$method is invalid !');
        }
    }

    public function makeOptions(string $method, array $params): array
    {
        // 针对表单请求
        if (isset($params['multipart'])) {
            return $params;
        }

        $options = [];
        switch ($method) {
            case 'post':
                $options['form_params'] = $params;
                return $options;
            case 'postAsync':
                $options['form_params'] = $params;
                return $options;
            case 'get':
                $options['query'] = $params;
                return $options;
            case 'getAsync':
                $options['query'] = $params;
                return $options;
            case 'put':
                $options['json'] = $params;
                return $options;
            case 'putAsync':
                $options['json'] = $params;
                return $options;
            default:
                return $options;
        }
    }

    /**
     * @date   2019/4/4
     * @author <zhufengwei@aliyun.com>
     * @param string $str
     * @return string
     */
    public function uriTrim(string $str)
    {
        return trim(trim($str), '/');
    }
}
