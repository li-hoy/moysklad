<?php

namespace Lihoy\Moysklad;

abstract class Base
{
    /**
     * @return string
     */
    protected static function getClassShortName(): string
    {
        return substr(static::class, strrpos(static::class, '\\') + 1);
    }
}
