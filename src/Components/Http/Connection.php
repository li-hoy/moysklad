<?php

namespace Lihoy\Moysklad\Components\Http;

use Exception;
use GuzzleHttp\Client as HttpClient;
use Lihoy\Moysklad\Base;
use Lihoy\Moysklad\Components\Http\Query;

class Connection extends Base
{

    /**
     * 
     * @var int
     */
    public const DELAY = 500;

    /**
     * 
     * @var float
     */
    public const TIMEOUT = 30.0;

    /**
     * 
     * @var string
     */
    public const POST_DATA_TYPE = 'json';

    /**
     * 
     * @var string
     */
    protected $token;

    /**
     * 
     * @var array<string, mixed>
     */
    protected $request_options;

    /**
     * 
     * @var HttpClient
     */
    protected $http_client;

    /**
     *
     * @param string $login
     * @param string $pass
     * @param array<string, mixed> $options
     */
    public function __construct(string $login, string $pass, array $options = [])
    {
        $this->token = base64_encode($login . ':' . $pass);

        $this->request_options = [
            'headers' => [
                'Authorization' => "Basic $this->token",
            ],
            'delay' => $options['delay'] ?? static::DELAY,
            'timeout' => $options['timeout'] ?? static::TIMEOUT,
        ];

        $this->http_client = new HttpClient($this->request_options);
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
     * @param array<string, mixed> $data
     * @param boolean $is_json
     * @return mixed
     */
    public function query(string $method, string $url, array $data = [], bool $is_json = true)
    {
        $query = new Query($this->http_client, $method, $url);

        $response = $query->send($data);

        $body = $response->getBody();

        $content = $body->getContents();

        if (!$is_json) {
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
        return (string) $this->token;
    }

    /**
     *
     * @return array<string, mixed>
     */
    public function getRequestOptions(): array
    {
        return (array) $this->request_options;
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
            foreach ($value as $header_name => $header_value){
                $this->request_options[$key][$header_name] = $header_value;
            }
        } 

        if ($key === 'headers' && !is_array($value)) {
            throw new Exception("Wrong argument '$key' type, array expected.");
        }

        if (!is_array($value)) {
            $this->request_options[$key] = $value;
        }

        if (is_null($value)) {
            unset($this->request_options[$key]);
        }

        $this->http_client = new HttpClient($this->request_options);

        return $this;
    }

    /**
     *
     * @param float $delay Delay in seconds
     * @return $this
     */
    public function setDelay(float $delay): self
    {
        $delay = intval($delay * 1000);

        $this->setRequestOption('delay', $delay);

        return $this;
    }

}
