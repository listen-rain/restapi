<?php

namespace Listen\Restapi;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use http\Url;
use Illuminate\Contracts\Config\Repository;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use phpDocumentor\Reflection\Types\Integer;
use Psr\Http\Message\ResponseInterface;

class Restapi
{
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
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param array $params
     * @param       $secret
     *
     * @return array
     */
    protected function addSign(array $params, $secret)
    : array
    {
        if ($secret && !isset($params['sign'])) {
            $params['sign'] = $this->getSign($params, $secret);
        }

        return $params;
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param string $module
     * @param string $uri
     * @param string $action
     *
     * @return object
     */
    protected function makeRequest(string $module, string $uri, string $action)
    : Request
    {
        $base_uri = $this->getBaseUri($module);

        return new Request($action, $base_uri . $uri);
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param string $module
     * @param array  $params
     * @param string $response
     * @param string $uri
     *
     * @return bool|mixed
     */
    protected function getResponse(string $module, array $params, Response $response, string $uri)
    {
        if (!$response) {
            $this->addlog($module, $uri, $params, 'RESQUST_ERROR', 404);
            return false;
        }

        $code = $response->getStatusCode();
        if ($code != 200) {
            $this->addlog($module, $uri, $params, '', $code);
            return false;
        }

        $result = json_decode(strval($response->getBody()), true);
        if (!$result) {
            $this->addlog($module, $uri, $params, $stringBody, $code);
            return false;
        }

        $this->addlog($module, $uri, $params, $result, $code);
        return $result;
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param array  $params
     * @param string $secret
     *
     * @return string
     */
    protected function getSign(array $params, string $secret)
    : string
    {
        ksort($params);
        $paramStr = http_build_query($params, null, ini_get('arg_separator.output'), PHP_QUERY_RFC3986);
        return md5(md5($paramStr) . $secret);
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param string $module
     * @param string $uri
     * @param array  $params
     * @param array  $headers
     * @param string $action
     *
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get(string $module, string $uri, array $params, array $headers = [], string $action = 'GET')
    {
        $options = [
            'query'   => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers' => $headers
        ];

        $result = $this->client->send($this->makeRequest($module, $uri, $action), $options);
        return $this->getResponse($module, $params, $result, $uri);
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param string $module
     * @param string $uri
     * @param array  $params
     * @param array  $headers
     * @param string $action
     *
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function post(string $module, string $uri, array $params, array $headers = [], string $action = 'POST')
    {
        $options = [
            'form_params' => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers'     => $headers
        ];

        $result = $this->client->send($this->makeRequest($module, $uri, $action), $options);

        return $this->getResponse($module, $params, $result, $uri);
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param string $module
     * @param string $uri
     * @param array  $params
     * @param array  $headers
     * @param string $action
     *
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function multipart(string $module, string $uri, array $params, array $headers = [], string $action = 'POST')
    {
        $options = [
            'multipart' => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers'   => $headers
        ];

        $result = $this->client->send($this->makeRequest($module, $uri, $action), $options);
        return $this->getResponse($module, $params, $result, $uri);
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param array $apis
     *
     * @return array
     * @throws \Throwable
     */
    public function mget(array $apis)
    : array
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
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param string $module
     *
     * @return string
     */
    protected function getBaseUri(string $module)
    : string
    {
        return $this->config->get('restapi.' . $module . '.base_uri');
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param string $module
     * @param string $uri
     * @param array  $request
     * @param array  $response
     * @param int    $code
     */
    public function addlog(string $module, string $uri, array $request, array $response, int $code)
    {
        $logger      = new Logger($this->config->get('restapi.log_channel'));
        $file_name   = $this->config->get('restapi.log_file');
        $eventRotate = new RotatingFileHandler($file_name, Logger::INFO);
        $eventRotate->setFormatter(new LineFormatter("[%datetime%] [%level_name%] %channel% - %message% %extra%\n"));
        $logger->pushHandler($eventRotate);
        $logger->pushProcessor(function ($record) use ($request, $uri, $response, $code) {
            $record['extra'] = [
                'uri'      => $uri,
                'request'  => $request,
                'response' => $response,
                'code'     => $code
            ];

            return $record;
        });

        $logger->addInfo($module);
    }

    /**
     * @date   2019/1/4
     * @author <zhufengwei@100tal.com>
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    public function checkServer(\Illuminate\Http\Request $request)
    : bool
    {
        $inputs = $request->all();
        $path   = $request->path();
        $path   = str_replace('.', '_', $path);
        ltrim('/', $path);
        $path = '/' . $path;
        unset($inputs[$path]);

        if (!isset($inputs['sign'])) {
            return false;
        }

        $sign = $inputs['sign'];
        unset($inputs['sign']);

        return $sign == $this->getSign($inputs, $this->secret);
    }
}
