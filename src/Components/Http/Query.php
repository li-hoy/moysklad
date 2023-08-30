<?php

namespace Lihoy\Moysklad\Components\Http;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use Lihoy\Moysklad\Base;
use Psr\Http\Message\ResponseInterface;

class Query extends Base
{
    
    /**
     * 
     * @var Client
     */
    protected $client;

    /**
     * 
     * @var Request
     */
    protected $request;

    /**
     * 
     * @param Client $client
     * @param string $method
     * @param string $url
     */
    public function __construct(Client $client, string $method, string $url)
    {
        $this->client = $client;

        $this->request = new Request($method, $url);
    }

    /**
     *
     * @param array<string, mixed> $request_data
     * @return ResponseInterface
     * @throws Exception
     */
    public function send(array $request_data = []): ResponseInterface
    {
        try {
            return $this->client
                ->send($this->request, ['json' => $request_data]);
        } catch (BadResponseException $exception) {
            $message = "";

            $response_content = $exception->getResponse()
                ->getBody()
                ->getContents();

            $errors = \json_decode($response_content)->errors;

            foreach ($errors as $error) {
                $message = $message . ' ' . $error->error . ';';
            }

            throw new Exception($message);
        }
    }

    /**
     *
     * @return void
     */
    protected function delay(): void
    {
        $delayTime = intval($this->client->delay * 1000000);

        \usleep($delayTime);
    }

}
