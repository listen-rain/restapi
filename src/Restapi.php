<?php

namespace Listen\Restapi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Contracts\Config\Repository;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Restapi
{
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

    protected function addSign($params, $secret)
    {
        if ($secret && !isset($params['sign'])) {
            $params['sign'] = $this->getSign($params, $secret);
        }

        return $params;
    }

    protected function getSign($params, $secret)
    {
        ksort($params);
        $paramStr = http_build_query($params, null, ini_get('arg_separator.output'), PHP_QUERY_RFC3986);
        return md5(md5($paramStr) . $secret);
    }

    public function get($module, $uri, $params, $headers = [], $action = 'GET')
    {
        $options = [
            'query'   => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers' => $headers
        ];

        $result = $this->client->send(
            $this->makeRequest($module, $uri, $params, $headers, $action),
            $options
        );

        return $this->getResponse($module, $params, $result, $uri);
    }

    public function post($module, $uri, $params, $headers = [], $action = 'POST')
    {
        $options = [
            'form_params' => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers'     => $headers
        ];

        $result = $this->client->send(
            $this->makeRequest($module, $uri, $params, $headers, $action),
            $options
        );

        return $this->getResponse($module, $params, $result, $uri);
    }

    public function multipart($module, $uri, $params, $headers = [], $action = 'POST')
    {
        $options = [
            'multipart' => $this->addSign($params, $this->config->get('restapi.' . $module . '.secret')),
            'headers'   => $headers
        ];

        $result = $this->client->send(
            $this->makeRequest($module, $uri, $params, $headers, $action),
            $options
        );

        return $this->getResponse($module, $params, $result, $uri);
    }

    protected function makeRequest($module, $uri, $params, $headers = [], $action)
    {
        $base_uri = $this->getBaseUri($module);
        return new Request($action, $base_uri . $uri);
    }

    protected function getResponse($module, $params, $response, $uri)
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

        $stringBody = strval($response->getBody());
        $result     = json_decode($stringBody, true);

        if (!$result) {
            $this->addlog($module, $uri, $params, $stringBody, $code);
            return false;
        }

        $this->addlog($module, $uri, $params, $result, $code);
        return $result;
    }

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

    protected function getBaseUri($module)
    {
        return $this->config->get('restapi.' . $module . '.base_uri');
    }

    public function addlog($module, $uri, $request, $response, $code)
    {
        $logger    = new Logger($this->config->get('restapi.log_channel'));
        $file_name = $this->config->get('restapi.log_file');

        try {
            $logger->pushHandler(new StreamHandler($file_name, Logger::INFO, false));
        } catch (\Exception $e) {
            $logger->info('pushHandlerError', $e->getMessage());
        }

        $logger->pushProcessor(function ($record) use ($request, $uri, $response, $code) {
            $record['extra'] = [
                'uri'      => $uri,
                'request'  => $request,
                'response' => $response,
                'code'     => $code
            ];

            return $record;
        });

        $logger->addInfo(self::BASENAME . $module);
    }

    public function checkServer($request)
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
