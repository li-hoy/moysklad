<?php

namespace Lihoy\Moysklad\Components\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\BadResponseException;

class Query extends \Lihoy\Moysklad\Base
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

    public function send(array $requestData = [])
    {
        try {
            $response = $this->client->send($request, ['json' => $requestData]);
        } catch (BadResponseException $exception) {
            $message = "";
            $responseContent = $exception->getResponse()->getBody()->getContents();
            $errors = \json_decode($responseContent)->errors;
            foreach ($errors as $error) {
                $message = $message.' '.$error->error.';';
            }
            throw new \Exception($message);
        }
    }

    protected function delay()
    {
        $delayTime = intval($this->client->delay * 1000000);
        \usleep($delayTime);
    }
}