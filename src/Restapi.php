<?php

namespace Listen\Restapi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Contracts\Config\Repository;
use Listen\LogCollector\LogCollector;
use Listen\Restapi\Exceptions\RestapiException;

class Restapi
{
    const BASENAME = 'restapi.';

    /**
     * @var array
     */
    protected $exceptionCallbacks = [];

    /**
     * @var \Listen\LogCollector\Logger
     */
    protected $logger;

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

        $this->setLogger();
        $this->pushExceptionCallback('logError', function ($module, $message, $code, $otherParams) {
            $this->logger->restapiError(compact('module', 'message', 'code', 'otherParams'));
        });
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     *
     * @return $this
     */
    public function setLogger(string $name = 'restapi')
    {
        $this->logger = app(LogCollector::class)
            ->setBaseInfo('restapi', 'default')
            ->load($name);

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param  string  $name
     * @param \Closure $closure
     *
     * @return $this
     */
    public function pushExceptionCallback(string $name, \Closure $closure)
    {
        $this->exceptionCallbacks[$name] = \Closure::bind($closure, $this);

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     *
     * @return $this
     * @throws \Listen\Restapi\Exceptions\RestapiException
     */
    public function popExceptionCallback(string $name)
    {
        if (!in_array($name, array_keys($this->exceptionCallbacks))) {
            throw new RestapiException('Callback does\'t Exsits !');
        }

        unset($this->exceptionCallbacks[$name]);

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $module
     * @param string $message
     * @param int    $code
     * @param array  $otherParams
     *
     * @return $this
     */
    public function applyCallback(string $module, string $message, int $code, array $otherParams = [])
    {
        foreach ($this->exceptionCallbacks as $callback) {
            $callback($module, $message, $code, $otherParams);
        }

        return $this;
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

        $this->logger->restapi(compact('module', 'uri', 'params', 'result', 'code'));
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
