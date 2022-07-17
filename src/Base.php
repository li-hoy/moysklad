<?php

namespace Lihoy\Moysklad;

abstract class Base
{
    /**
     * @return string
     */
    protected static function getClassShortName()
    {
        return substr(static::class, strrpos(static::class, '\\') + 1);
	}
}
