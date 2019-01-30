<?php

namespace Listen\Restapi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Contracts\Config\Repository;

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
     * Restapi constructor.
     *
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    public function __construct(Repository $config)
    {
        $this->config = $config;

        $this->secret         = $this->config->get('restapi.secret');
        $this->requestTimeout = $this->config->get('restapi.request_timeout');
        $this->connectTimeout = $this->config->get('restapi.connect_timeout');

        $this->client = new Client(
            [
                'timeout'         => $this->requestTimeout,
                'connect_timeout' => $this->connectTimeout,
                'http_errors'     => true,
            ]);

        $this->callbackConstruct();
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param $params
     * @param $secret
     *
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
     *
     * @param $params
     * @param $secret
     *
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
     *
     * @param        $module
     * @param        $uri
     * @param        $params
     * @param array  $headers
     * @param string $action
     *
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
     *
     * @param        $module
     * @param        $uri
     * @param        $params
     * @param array  $headers
     * @param string $action
     *
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
     *
     * @param        $module
     * @param        $uri
     * @param        $params
     * @param array  $headers
     * @param string $action
     *
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
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param       $module
     * @param       $uri
     * @param       $params
     * @param array $headers
     * @param       $action
     *
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
     *
     * @param $module
     * @param $params
     * @param $response
     * @param $uri
     *
     * @return bool|mixed
     */
    protected function getResponse($module, $params, $response, $uri)
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
     *
     * @param $apis
     *
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
     *
     * @param $module
     *
     * @return mixed
     */
    protected function getBaseUri($module)
    {
        return $this->config->get('restapi.' . $module . '.base_uri');
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     *
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
     *
     * @param $request
     *
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
