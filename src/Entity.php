<?php

namespace Lihoy\Moysklad;

use Lihoy\Moysklad\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\BadResponseException;

class Entity extends \Lihoy\Moysklad\Base
{
    protected
        $attributes = [],
        $client = null,
        $changed = null,
        $readonly = [],
        $required = ['meta'],
        $type = null;

    public function __construct(
        $entityData = null,
        Client $client = null
    ) {
        $this->client = $client;
        if ($entityData && (is_array($entityData) || is_object($entityData))) {
            foreach ($entityData as $fieldName=>$fieldValue) {
                $this->attributes[$fieldName] = $fieldValue;
            }
            foreach ($this->required as $fieldName) {
                if (false === isset($this->attributes[$fieldName])) {
                    throw new \Exception("Missing a required field $fieldName");
                }
            }
            $this->type = $this->attributes['meta']->type;
        }
        if (is_string($entityData)) {
            $this->type = $entityData;
        }
        $this->changed = [];
        // foreach ($this as $fieldName=>$fieldValue) {
        //     if (is_null($this->$fieldName)) {
        //         throw new \Exception("Сonstruction not completed.");
        //     }
        // }
    }

    public function __set($fieldName, $fieldValue)
    {
        if (isset($this->$fieldName) && is_null($this->$fieldName)) {
            $this->$fieldName = $fieldValue;
            return;
        }
        if (
            in_array($fieldName, $this->readonly)
            && false === is_null($this->changed)
        ) {
            throw new \Exception(
                "Trying to set a value to a read-only field."
            );
        }
        $this->attributes[$fieldName] = $fieldValue;
        if (false === is_null($this->changed)) {
            $this->changed[] = $fieldName;
        }
    }

    public function __get($fieldName)
    {
        if (isset($this->$fieldName)) {
            return $this->$fieldName;
        }
        if (isset($this->attributes[$fieldName])) {
            return $this->attributes[$fieldName];
        }
        if (isset($this->attributes['meta'])) {
            if ($fieldName === 'id') {
                return $this->parseId();
            }
            if (isset($this->attributes['meta']->$fieldName)) {
                return $this->attributes['meta']->$fieldName;
            }
        }
        $additionalFeldList = $this->attributes['attributes'] ?? [];
        foreach ($additionalFeldList as $additionalFeld) {
            if ($additionalFeld->name === $fieldName) {
                return $additionalFeld;
            }
        }
        throw new \Exception(
            "Trying to get a non-existent field '{$fieldName}' value."
        );
    }

    public function af(
        string $name,
        $value = null
    ) {
        $additionalFeldList = isset($this->attributes['attributes'])
            ? $this->attributes['attributes']
            : [];
        foreach ($additionalFeldList as &$additionalFeld) {
            if ($additionalFeld->name === $name) {
                if (false === is_null($value)) {
                    $additionalFeld->value = $value;
                    return true;
                }
                return new static($additionalFeld);
            }
        }
        throw new \Exception(
            "Attempt to contact non-existent additional field '{$name}' value."
        );
    }

    protected function parseId()
    {
        $uriParts = explode('/', $this->attributes['meta']->href);
        return end($uriParts);
    }

    public function map($fieldNameList)
    {
        $out = (object) [];
        foreach ($fieldNameList as $fieldName) {
            if (isset($this->$fieldName)) {
                $out->$fieldName = $this->$fieldName;
            }
        }
        return $out;
    }

    /**
     *  by default ms api return 25 events - max = 100
     */
    public function getEvents(object $entity, int $limit = null, int $offset = null)
    {
        $queryLimit = 100;
        if ($limit && $limit < $queryLimit) {
            $queryLimit = $limit;
        }
        $offset = is_null($offset) ? 0 : $offset;
        $totalCount = 0;
        $eventList = [];
        do {
            $href = $entity->meta->href."/audit?limit=".$queryLimit."&offest=".$offset;
            $response = $this->client->connection->get($href);
            $rows = $response->rows;
            if (is_null($limit)) {
                $limit = $response->meta->size - $offset;
            }
            $eventList = array_merge($eventList, $rows);
            $totalCount = $totalCount + count($rows);
            $offset = $offset + count($rows);
            $remainder = empty($remainder)
                ? $limit - count($rows)
                : $remainder - count($rows);
            $queryLimit = $queryLimit <= $remainder ? $queryLimit : $remainder;
            if (($remainder + $queryLimit) > $limit) {
                $queryLimit = $limit - $totalCount;
            }
        } while ($totalCount < $limit);
        return $eventList;
    }

    public function getLinkedEntities(
        string $searchType,
        ?int $recursive = null,
        int $limit = 10,
        $expand = null,
        $entity = null
    ) {
        if (is_null($entity)) {
            $entity = $this;
        }
        $resultLinkedEntityList = [];
        foreach($entity->attributes as $linkedEnitiesType=>$linkedEntityList) {
            if (in_array($linkedEnitiesType, ['attributes', 'positions'])) {
                continue;
            }
            if (!is_array($linkedEntityList)) {
                continue;
            }
            if (empty($linkedEntityList)) {
                continue;
            }
            $entityType = $linkedEntityList[0]->meta->type ?? null;
            if (is_null($entityType)) {
                continue;
            }
            if ($entityType === $searchType) {
                $resultLinkedEntityList = array_merge(
                    $resultLinkedEntityList,
                    $linkedEntityList
                );
            }
            if (false === is_null($recursive) && $recursive > 0) {
                $recurciveLinkedEntityList = [];
                $filterList = [];
                foreach ($linkedEntityList as $l_entity) {
                    $filterList[] = ['id', '=', $this->parseId($l_entity)];
                    $limit = $limit - 1;
                    if (!$limit) {
                        break;
                    }
                }
                $linkedEntityList = $this->client->getEntities($entityType, $filterList, $expand);
                foreach ($linkedEntityList as $linkedEntity) {
                    $recurciveLinkedEntityList = array_merge(
                        $recurciveLinkedEntityList,
                        call_user_func_array(
                            [$this, __FUNCTION__],
                            [$searchType, $recursive - 1, $limit, $expand, $linkedEntity]
                        )
                    );
                }
                $resultLinkedEntityList = array_merge(
                    $resultLinkedEntityList,
                    $recurciveLinkedEntityList
                );
            }
        }
        return $resultLinkedEntityList;
    }

    public function getStates(
        string $entityType,
        array $filter = []
    ) {
        $stateList = $this->client->getMetadata($entityType)->states;
        if (empty($filter)) {
            return $stateList;
        }
        return $this->client->filter($stateList, $filter);
    }

    public function getState(
        string $entityType,
        string $fieldValue,
        string $fieldName = 'id'
    ) {
        $stateList = $this->getStates($entityType, [[$fieldName, '=', $fieldValue]]);
        if (empty($stateList)) {
            throw new \Exception("State with $fieldName = $fieldValue doesn`t exist");
        }
        return $stateList[0];
    }

    public function save()
    {
        $requestData = [];
        foreach ($this->changed as $fieldName) {
            $requestData[$fieldName] = $this->attributes[$fieldName];
        }
        if (empty($requestData)) {
            return false;
        }
        if (isset($this->attributes['meta'])) {
            $method = 'PUT';
            $href = $this->href;
        } else {
            $method = 'POST';
            $href = Client::BASE_URI.Client::ENTITY_URI.'/'.$this->type;
        }
        $response = $this->client->getConnection()->query($method, $href, $requestData);
        return new static($response);
    }
}
