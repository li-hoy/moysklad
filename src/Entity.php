<?php

namespace Lihoy\Moysklad;

use Lihoy\Moysklad\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\BadResponseException;

class Entity extends \Lihoy\Moysklad\Base
{
    protected
        $additional_felds = [],
        $attributes = [],
        $client = null,
        $changed = null,
        $metadata = null,
        $readonly = [],
        $required = [],
        $system = ['meta'],
        $type = null;

    public function __construct(
        $entityData = null,
        Client $client = null
    ) {
        $this->client = $client;
        if (is_object($entityData)) {
            if (false === isset($entityData->meta)) {
                throw new \Exception("'meta' field is required");
            }
            foreach ($entityData as $fieldName=>$fieldValue) {
                $this->attributes[$fieldName] = $this->updateField($fieldValue);
            }
            $this->type = $this->attributes['meta']->type ?? null;
        }
        if (is_string($entityData)) {
            $this->type = $entityData;
        }
        $this->changed = [];
    }

    public function __set($fieldName, $fieldValue)
    {
        if (
            in_array($fieldName, $this->readonly)
            && false === is_null($this->changed)
        ) {
            throw new \Exception(
                "Trying to set a value to a read-only field."
            );
        }
        if (false === is_null($this->changed)) {
            $this->changed[] = $fieldName;
        }
        $this->attributes[$fieldName] = $this->updateField($fieldValue);
    }

    public function __get($fieldName)
    {
        if (isset($this->attributes[$fieldName])) {
            return $this->attributes[$fieldName];
        }
        if ($fieldName === 'id') {
            return $this->parseId();
        }
        $additionalFeldList = [];
        if (
            isset($this->attributes['attributes'])
            && is_array($this->attributes['attributes'])
        ) {
            $additionalFeldList = $this->attributes['attributes'];
        }
        foreach ($additionalFeldList as $additionalFeld) {
            if ($additionalFeld->name === $fieldName) {
                return $additionalFeld;
            }
        }
        throw new \Exception(
            "Trying to get a non-existent field '{$fieldName}' value."
        );
    }
    
    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }

    protected function updateData(object $data)
    {
        foreach ($data as $fieldName=>$fieldValue) {
            $this->attributes[$fieldName] = $this->updateField($fieldValue);
        }
        return true;
    }

    protected function updateField($field)
    {
        if (is_array($field)) {
            foreach ($field as &$subField) {
                $subField = $this->updateField($subField);
            }
            return $field;
        }
        if (is_object($field)) {
            foreach ($field as $subFieldName=>$subFieldValue) {
                $field->$subFieldName =
                    $this->updateField($subFieldValue);
            }
            if (
                isset($field->meta)
                && false === is_a($field, static::class)
            ) {
                $field = new static($field);
            }
        }
        return $field;
    }

    public function af(
        ?string $name = null,
        $value = null
    ) {
        // get
        if (is_null($value)) {
            $additionalFeldList = isset($this->attributes['attributes'])
                ? $this->attributes['attributes']
                : [];
            // get all
            if (is_null($name)) {
                return $additionalFeldList;
            }
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

        // set
    }

    public function getAdditionalFelds()
    {
        if (empty($this->additional_felds)) {
            $this->additional_felds = $this->client->getEntitiesByHref(
                $this->getMetadata()->attributes->href
            );
        }
        return $this->additional_felds;
    }

    public function getMetadata() {
        if (is_null($this->metadata)) {
            $this->metadata = $this->client->getMetadata($this->type);
        }
        return $this->metadata;
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
            if (isset($this->attributes[$fieldName])) {
                $out->$fieldName = $this->attributes[$fieldName];
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
        int $limit = 1,
        $expand = null
    ) {
        $resultLinkedEntityList = [];
        foreach($this->getData() as $linkedEnitiesType=>$linkedEntityList) {
            if (in_array($linkedEnitiesType, ['attributes', 'positions', 'files'])) {
                continue;
            }
            if (is_a($linkedEntityList, static::class)) {
                $linkedEntityList = [$linkedEntityList];
            }
            if (false === is_array($linkedEntityList)) {
                continue;
            }
            if (empty($linkedEntityList)) {
                continue;
            }
            $entityType = $linkedEntityList[0]->type ?? null;
            if (is_null($entityType)) {
                continue;
            }
            if ($entityType === $searchType) {
                $resultLinkedEntityList = array_merge(
                    $resultLinkedEntityList,
                    $linkedEntityList
                );
                $limit = $limit - count($linkedEntityList);
                if ($limit <= 0) {
                    return $resultLinkedEntityList;
                }
            }
            if (false === is_null($recursive) && $recursive > 0) {
                $recurciveLinkedEntityList = [];
                $hrefList = [];
                foreach ($linkedEntityList as $l_entity) {
                    if (false === isset($l_entity->meta->href)) {
                        continue;
                    }
                    $hrefList[] = $l_entity->meta->href;
                }
                $linkedEntityList_expanded = $this->client->getEntitiesByHref($hrefList, $expand);
                foreach ($linkedEntityList_expanded as $linkedEntity) {
                    $recurciveLinkedEntityList = array_merge(
                        $recurciveLinkedEntityList,
                        call_user_func_array(
                            [$linkedEntity, __FUNCTION__],
                            [$searchType, $recursive - 1, $limit, $expand]
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
        array $filter = []
    ) {
        $stateList = $this->client->getMetadata($this->type)->states;
        if (empty($filter)) {
            return $stateList;
        }
        return $this->client->filter($stateList, $filter);
    }

    public function getState(
        string $fieldValue,
        string $fieldName = 'name'
    ) {
        $stateList = $this->getStates([[$fieldName, '=', $fieldValue]]);
        if (empty($stateList)) {
            throw new \Exception("State with $fieldName = $fieldValue doesn`t exist");
        }
        return $stateList[0];
    }

    public function save()
    {
        $requestData = [];
        foreach ($this->required as $fieldName) {
            $requestData[$fieldName] =
                $this->getFieldData($this->attributes[$fieldName]);
        }
        foreach ($this->changed as $fieldName) {
            if (in_array($fieldName, $this->required)) {
                continue;
            }
            $requestData[$fieldName] =
                $this->getFieldData($this->attributes[$fieldName]);
        }
        if (empty($requestData)) {
            return false;
        }
        if (isset($this->attributes['meta'])) {
            $requestData['meta'] = $this->attributes['meta'];
            $method = 'PUT';
            $href = $this->href;
        } else {
            $method = 'POST';
            $href = Client::BASE_URI.Client::ENTITY_URI.'/'.$this->type;
        }
        $response = $this->client->getConnection()->query($method, $href, $requestData);
        $this->updateData($response);
        $this->changed = [];
        return $this;
    }

    protected function getData()
    {
        return (object) $this->attributes;
    }

    protected function getFieldData($field)
    {
        $fieldData = $field;
        if (is_array($fieldData)) {
            foreach ($fieldData as &$subField) {
                $subField = $this->getFieldData($subField);
            }
        }
        if (is_object($fieldData)) {
            if (is_a($fieldData, static::class)) {
                $fieldData = $fieldData->getData();
            }
            foreach ($fieldData as $subFieldName=>$subFieldValue) {
                $fieldData->$subFieldName =
                    $this->getFieldData($subFieldValue);
            }
        }
        return $fieldData;
    }
}
