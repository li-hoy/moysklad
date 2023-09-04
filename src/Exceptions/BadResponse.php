<?php

namespace Lihoy\Moysklad\Exceptions;

use Exception;
use GuzzleHttp\Exception\BadResponseException as GuzzleBadResponseException;

class BadResponse extends Exception
{

    /**
     * 
     * @param GuzzleBadResponseException $exception
     * @param int $code
     * @param Throwable $previous
     */
    public function __construct(GuzzleBadResponseException $exception) {
        $message = "";

        $response_content = $exception->getResponse()
            ->getBody()
            ->getContents();

        $errors = json_decode($response_content)->errors;

        foreach ($errors as $error) {
            $message = $message . ' ' . $error->error . ';';
        }

        parent::__construct($message, $exception->getCode(), $exception->getPrevious());
    }

    /**
     * 
     * @return string
     */
    public function __toString(): string
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
    
}
