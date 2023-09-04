<?php

namespace Lihoy\Moysklad\Components\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException as GuzzleBadResponseException;
use GuzzleHttp\Psr7\Request;
use Lihoy\Moysklad\Base;
use Lihoy\Moysklad\Exceptions\BadResponse as BadResponseException;
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
     * @throws BadResponseException
     */
    public function send(array $request_data = []): ResponseInterface
    {
        try {
            return $this->client
                ->send($this->request, ['json' => $request_data]);
        } catch (GuzzleBadResponseException $exception) {
            throw new BadResponseException($exception);
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
