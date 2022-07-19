<?php

namespace Lihoy\Moysklad\Components\Http;

use Exception;
use GuzzleHttp\Client as HttpClient;
use Lihoy\Moysklad\Base;
use Lihoy\Moysklad\Components\Http\Query;

class Connection extends Base
{
    const
        DELAY = 500,
        TIMEOUT = 30.0,
        POST_DATA_TYPE = 'json';

    protected
        $token,
        $requestOptions,
        $httpClient;

    /**
     *
     * @param string $login
     * @param string $pass
     * @param array $options
     */
    public function __construct(string $login, string $pass, array $options = [])
    {
        $this->token = base64_encode($login . ':' . $pass);
        $this->requestOptions = [
            'headers' => [
                'Authorization' => "Basic $this->token",
            ],
            'delay' => $options['delay'] ?? static::DELAY,
            'timeout' => $options['timeout'] ?? static::TIMEOUT
        ];
        $this->httpClient = new HttpClient($this->requestOptions);
    }

    /**
     *
     * @param string $url
     * @return mixed
     */
    public function get(string $url)
    {
        return $this->query('GET', $url, []);
    }

    /**
     *
     * @param string $url
     * @param array $data
     * @return mixed
     */
    public function post(string $url, array $data)
    {
        return $this->query('POST', $url, $data);
    }

    /**
     *
     * @param string $url
     * @param array $data
     * @return mixed
     */
    public function put(string $url, array $data = [])
    {
        return $this->query('PUT', $url, $data);
    }

    /**
     *
     * @param string $url
     * @param array $data
     * @return mixed
     */
    public function delete(string $url, array $data = [])
    {
        return $this->query('DELETE', $url, $data);
    }

    /**
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param boolean $json
     * @return mixed
     */
    public function query(string $method, string $url, array $data = [], bool $json = true)
    {
        $query = new Query($this->httpClient, $method, $url);
        $response = $query->send($data);
        $body = $response->getBody();
        $content = $body->getContents();
        if (false === $json) {
            return $response;
        }
        return \json_decode($content);
    }

    /**
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     *
     * @return array
     */
    public function getRequestOptions(): array
    {
        return $this->requestOptions;
    }

    /**
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setRequestOption(string $key, $value): self
    {
        if (is_array($value)) {
            foreach ($value as $headerName=>$headerValue){
                $this->requestOptions[$key][$headerName] = $headerValue;
            }
        } else {
            if (in_array($key, ['headers'])) {
                throw new Exception("Wrong argument '$key' type, array expected.");
            }
            $this->requestOptions[$key] = $value;
        }
        if (is_null($value)) {
            unset($this->requestOptions[$key]);
        }
        $this->httpClient = new HttpClient($this->requestOptions);
        return $this;
    }

    /**
     *
     * @param float $delay
     * @return void
     */
    public function setDelay(float $delay): void
    {
        $delay = intval($delay * 1000);
        $this->setRequestOption('delay', $delay);
    }
}
