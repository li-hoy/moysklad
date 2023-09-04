<?php

namespace Lihoy\Moysklad\Exceptions;

use Exception;

class NotSupported extends Exception
{

    /**
     * 
     * @return string
     */
    public function __toString(): string
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
    
}
