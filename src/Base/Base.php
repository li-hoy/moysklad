<?php

namespace Lihoy\Moysklad;

class Base
{
    protected static function getClassShortName()
    {
        return substr(static::class, strrpos(static::class, '\\') + 1);
	}
}
