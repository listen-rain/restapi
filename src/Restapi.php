<?php

namespace Listen\Restapi;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Contracts\Config\Repository;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;

class Restapi
{
    protected $config;

    protected $secret;

    protected $client;

    const BASENAME = 'restapi.';

    public function __construct(Repository $config)
    {
        $this->config = $config;
        $this->secret = $config->get('restapi.secret');
        $this->client();
    }

    private function client()
    {
        $this->client = new Client(
            [
                'timeout'         => $this->config->get('restapi.request_timeout'),
                'connect_timeout' => $this->config->get('restapi.connect_timeout'),
                'http_errors'     => true,
            ]);
    }

    private function addSign(array $params, $secret)
    {
        if (!isset($params['sign'])) {
            $params['sign'] = $this->getSign($params, $secret);
        }

        return $params;
    }

    private function getSign(array $params, $secret)
    {
        ksort($params);
        $paramStr = http_build_query($params, null, ini_get('arg_separator.output'), PHP_QUERY_RFC3986);
        return md5(md5($paramStr) . $secret);
    }

    public function get($module, $uri, $params, $headers = [], $method = 'POST')
    {
        $res = $this->mget([compact('module', 'uri', 'params', 'headers', 'method')]);
        return $res[0];
    }

    private function getResponse($module, $params, $response, $uri)
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


    public function mget(array $apis)
    {
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
            $promises[$k]      = $this->client->sendAsync($request, $option)
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

    private function getBaseUri($module)
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
        $path   = str_replace('.', '_', $request->path());
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

