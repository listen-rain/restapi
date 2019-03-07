<?php

namespace Listen\Restapi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use Illuminate\Contracts\Config\Repository;
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
            $response = $this->client->get($this->getBaseUri($module) . $uri, $options);

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
            $response = $this->client->post($this->getBaseUri($module) . $uri, $options);

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
            $response = $this->client->post($this->getBaseUri($module) . $uri, $options);

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

        $promise = $this->client->getAsync($this->getBaseUri($module) . $uri, $options);
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

        $promise = $this->client->postAsync($this->getBaseUri($module) . $uri, $options);
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
     * @param       $module
     * @param       $uri
     * @param       $params
     * @param array $headers
     * @param       $action
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function makeRequest($module, $uri, $action)
    {
        $base_uri = $this->getBaseUri($module);
        return new Request($action, $base_uri . $uri);
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
     * @param $apis
     * @return array
     * @throws \Throwable
     */
    public function mget($apis)
    {
        if (!is_array($apis)) {
            return [];
        }

        $promises = [];
        foreach ($apis as $k => $api) {
            if (!isset($api['module']) || !isset($api['uri'])) {
                continue;
            }
            $module   = $api['module'];
            $secret   = $this->config->get('restapi.' . $module . '.secret');
            $params   = $this->addSign($api['params'], $secret);
            $base_uri = $this->getBaseUri($module);
            $headers  = empty($api['headers']) ? [] : $api['headers'];
            $method   = empty($api['method']) ? 'POST' : $api['method'];
            $request  = new Request($method, $base_uri . $api['uri']);
            if ($method == 'GET') {
                $option = ['query' => $params];
            } elseif ($method == 'POST') {
                $option = ['form_params' => $params];
            }

            $option['headers'] = $headers;
            $promises[$k]      = $this->client
                ->sendAsync($request, $option)
                ->then(
                    null,
                    function (RequestException $e) {

                    });
        }

        $results = \GuzzleHttp\Promise\unwrap($promises);
        $return  = [];
        foreach ($apis as $k => $api) {
            $return[$k] = $this->getResponse($api['module'], $api['params'], $results[$k], $api['uri']);
        }

        return $return;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @param $module
     * @return mixed
     */
    protected function getBaseUri($module)
    {
        return $this->config->get('restapi.' . $module . '.base_uri');
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
}
