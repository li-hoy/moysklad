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
    protected
        $client,
        $request;

    public function __construct(
        Client $client,
        string $method,
        string $url
    ) {
        $this->client = $client;
        $this->request = new Request($method, $url);
    }

    /**
     *
     * @param array $requestData
     * @return ResponseInterface
     */
    public function send(array $requestData = []): ResponseInterface
    {
        try {
            return $this->client->send($this->request, ['json' => $requestData]);
        } catch (BadResponseException $exception) {
            $message = "";
            $responseContent = $exception->getResponse()->getBody()->getContents();
            $errors = \json_decode($responseContent)->errors;
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
