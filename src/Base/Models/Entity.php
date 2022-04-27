<?php

namespace Lihoy\Moysklad\Base\Models;

class Entity extends \Lihoy\Moysklad\Base
{
    protected
        $readonly = [
            'id',
            'href',
            'meta'
        ],
        $hidden = [];

    public function __construct($entity)
    {
        foreach ($entity as $fieldName=>$fieldValue) {
            $this->$fieldName = $fieldValue;
        }
    }

    public function __set($fieldName, $fieldValue)
    {
        if (false === in_array($this->readonly)) {
            throw new \Exception(
                "Trying to assign a value to a read-only field"
            );
            $this->$fieldName = $fieldValue;
        }
    }

    public function __get($fieldName)
    {
        if (in_array($fieldName, $this->hidden)) {
            throw new \Exception(
                "Trying to get the value of a hidden field"
            );
        }
        if (false === isset($this->$fieldName)) {
            if (isset($this->meta->$fieldName)) {
                return $this->meta->$fieldName;
            }
            if ($fieldName === 'id') {
                return $this->parseId();
            }
            return $this->fieldName;
        }
        throw new \Exception(
            "Trying to get a non-existent field."
        );
    }

    protected function parseId()
    {
        $uriParts = explode('/', $this->meta->href);
        return end($uriParts);
    }
}
