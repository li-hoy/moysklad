<?php

namespace Lihoy\Moysklad\Components\Http;

use GuzzleHttp\Client as HttpClient;
use Lihoy\Moysklad\Client as MoyskladClient;
use Lihoy\Moysklad\Components\Http\Query;

class Connection extends \Lihoy\Moysklad\Base
{
    const
        DELAY = 500,
        TIMEOUT = 60.0,
        POST_DATA_TYPE = 'json';

    protected
        $token,
        $requestOptions,
        $httpClient;

    public function __construct($login, $pass, $options = [])
    {
        $this->token = base64_encode($login.':'.$pass);
        $this->requestOptions = [
            'headers' => [
                'Authorization' => "Basic $this->token",
            ],
            'delay' => $options['delay'] ?? static::DELAY,
            'timeout' => $options['timeout'] ?? static::TIMEOUT
        ];
        $this->httpClient = new HttpClient($this->requestOptions);
    }

    public function get($url)
    {
        return $this->query('GET', $url, []);
    }

    public function post($url, array $data)
    {
        return $this->query('POST', $url, $data);
    }

    public function put(string $url, array $data = [])
    {
        return $this->query('PUT', $url, $data);
    }

    public function delete(string $url, array $data = [])
    {
        return $this->query('DELETE', $url, $data);
    }

    public function query(string $method, string $url, array $data = [])
    {
        $query = new Query($this->httpClient, $method, $url);
        $response = $query->send($data)->getBody()->getContents();
        return \json_decode($response);
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getRequestOptions()
    {
        return $this->requestOptions;
    }

    public function setRequestOption(string $key, $value)
    {
        if (is_array($value)) {
            foreach ($value as $headerName=>$headerValue){
                $this->requestOptions[$key][$headerName] = $headerValue;
            }
        } else {
            if (in_array($key, ['headers'])) {
                throw new \Exception("Wrong argument '$key' type, array expected.");
            }
            $this->requestOptions[$key] = $value;
        }
        if (is_null($value)) {
            unset($this->requestOptions[$key]);
        }
        $this->httpClient = new HttpClient($this->requestOptions);
        return $this;
    }

    public function setDelay(float $delay)
    {
        $delay = intval($delay * 1000);
        $this->setRequestOption('delay', $delay);
    }

}
