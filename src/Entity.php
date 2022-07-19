<?php

namespace Lihoy\Moysklad;

use Exception;
use Lihoy\Moysklad\Base;
use Lihoy\Moysklad\Client;

class Entity extends Base
{
    protected
        $additional_fields = [],
        $data = [],
        $client = null,
        $changed = null,
        $metadata = null,
        $readonly = [],
        $required = [],
        $system = ['meta'],
        $type = null;

    /**
     * @param array|string $entityData
     * @param Client $client
     */
    public function __construct(
        $entityData = null,
        Client $client = null
    ) {
        $this->client = $client;
        if (is_object($entityData)) {
            if (false === isset($entityData->meta)) {
                throw new Exception("'meta' field is required");
            }
            foreach ($entityData as $fieldName=>$fieldValue) {
                $this->data[$fieldName] = $this->updateField($fieldValue);
            }
            $this->type = $this->data['meta']->type ?? null;
        }
        if (is_string($entityData)) {
            $this->type = $entityData;
        }
        $this->changed = [];
    }

    /**
     *
     * @param string $fieldName
     * @return mixed
     */
    public function __get($fieldName)
    {
        if (array_key_exists($fieldName, $this->data)) {
            return $this->data[$fieldName];
        }
        if ($fieldName === 'id') {
            return $this->parseId();
        }
        $additionalFieldList = [];
        if (
            isset($this->data['attributes'])
            && is_array($this->data['attributes'])
        ) {
            $additionalFieldList = $this->data['attributes'];
        }
        foreach ($additionalFieldList as $additionalField) {
            if (false === isset($additionalField->name)) {
                continue;
            }
            if ($additionalField->name === $fieldName) {
                return $additionalField;
            }
        }
        throw new Exception(
            "Trying to get a non-existent field '{$fieldName}' value."
        );
    }

    /**
     *
     * @param string $fieldName
     * @param mixed $fieldValue
     * @return void
     */
    public function __set($fieldName, $fieldValue): void
    {
        if (
            in_array($fieldName, $this->readonly)
            && false === is_null($this->changed)
        ) {
            throw new Exception(
                "Trying to set a value to a read-only field."
            );
        }
        if (false === is_null($this->changed)) {
            $this->changed[] = $fieldName;
        }
        $this->data[$fieldName] = $this->updateField($fieldValue);
    }

    /**
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * @param object $data
     * @return bool
     */
    protected function updateData(object $data): bool
    {
        $this->data = [];
        foreach ($data as $fieldName=>$fieldValue) {
            $this->data[$fieldName] = $this->updateField($fieldValue);
        }
        return true;
    }

    /**
     * @param array|object $field
     * @return array|object
     */
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
                $field = new static($field, $this->client);
            }
        }
        return $field;
    }

    /**
     * @param string|null $name
     * @param mixed $value
     * @return mixed
     */
    public function af(
        ?string $name = null,
        $value = null
    ) {
        $additionalFieldList = isset($this->data['attributes'])
            ? $this->data['attributes']
            : [];
        // get all
        if (is_null($name)) {
            return $additionalFieldList;
        }
        // get or update
        foreach ($additionalFieldList as &$addField) {
            if ($addField->name === $name) {
                if (false === is_null($value)) {
                    $addField->value = $value;
                    if (false === in_array('attributes', $this->changed)) {
                        $this->changed[] = 'attributes';
                    }
                    return true;
                }
                return $addField;
            }
        }
        $metaAdditionalFieldList = $this->getMetaAdditionalFields();
        $additionalField = null;
        foreach ($metaAdditionalFieldList as $addField) {
            if ($addField->name === $name) {
                $additionalField = $addField;
            }
        }
        if (is_null($additionalField)) {
            throw new Exception(
                "Trying to get non-existent additional field '{$name}' value."
            );
        }
        $additionalField->value = null;
        if (is_null($value)) {
            return $additionalField;
        }
        // set
        $additionalField->value = $value;
        if (false === isset($this->data['attributes'])) {
            $this->data['attributes'] = [];
        }
        $this->data['attributes'][] = $additionalField;
        if (false === in_array('attributes', $this->changed)) {
            $this->changed[] = 'attributes';
        }
        return $additionalField;
    }

    /**
     * @return array
     */
    public function getMetaAdditionalFields(): array
    {
        if (empty($this->additional_fields)) {
            $this->additional_fields = $this->client->getEntities(
                "{$this->type}/metadata/attributes"
            );
        }
        return $this->additional_fields;
    }

    /**
     * @return object
     */
    public function getMetadata(): object
    {
        if (is_null($this->metadata)) {
            $this->metadata = $this->client->getMetadata($this->type);
        }
        return $this->metadata;
    }

    /**
     * @return string
     */
    protected function parseId(): string
    {
        $uriParts = explode('/', $this->data['meta']->href);
        return end($uriParts);
    }

    /**
     *
     * @param array $fieldNamesList
     * @return object
     */
    public function map(array $fieldNamesList): object
    {
        $out = (object) [];
        foreach ($fieldNamesList as $fieldName) {
            if (isset($this->data[$fieldName])) {
                $out->$fieldName = $this->data[$fieldName];
            }
        }
        return $out;
    }

    /**
     *  By default moysklad API return 25 events - max = 100
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getEvents(
        int $limit = null,
        int $offset = null
    ): array {
        $queryLimit = $this->client->getEventsQueryLimitMax();
        if ($limit && $limit < $queryLimit) {
            $queryLimit = $limit;
        }
        $offset = is_null($offset) ? 0 : $offset;
        $totalCount = 0;
        $eventList = [];
        do {
            $href = $this->data['meta']->href . "/audit?limit=" . $queryLimit . "&offest=" . $offset;
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

    /**
     * @param string $searchType
     * @param int|null $recursive
     * @param int $limit
     * @param string|null $expand
     * @return array
     */
    public function getLinkedEntities(
        string $searchType,
        ?int $recursive = null,
        int $limit = 1,
        ?string $expand = null
    ): array {
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

    /**
     * @param string $needle
     * @param string $by
     * @return void
     */
    public function setState(
        string $needle,
        string $by = 'name'
    ): void {
        $this->__set('state', $this->getState($needle, $by));
    }

    /**
     * @param array $filter
     * @return array
     */
    public function getStates(
        array $filter = []
    ) {
        $stateList = $this->client->getMetadata($this->type)->states;
        if (empty($filter)) {
            return $stateList;
        }
        return $this->client->filter($stateList, $filter);
    }

    /**
     * @param string $needle
     * @param string $by
     * @return object
     */
    public function getState(
        string $needle,
        string $by = 'name'
    ) {
        $stateList = $this->getStates([[$by, '=', $needle]]);
        if (empty($stateList)) {
            throw new Exception("State with $by = $needle doesn`t exist");
        }
        return $stateList[0];
    }

    /**
     * @return bool
     */
    public function remove(): bool
    {
        if (false === isset($this->data['meta']->href)) {
            throw new Exception("It is not possible to delete a non-existent entity.");
        }
        $response = $this->client->getConnection()->query('DELETE', $this->data['meta']->href);
        return true;
    }

    /**
     * @return bool|$this
     */
    public function save()
    {
        $requestData = [];
        foreach ($this->required as $fieldName) {
            $requestData[$fieldName] =
                $this->getFieldData($this->data[$fieldName]);
        }
        foreach ($this->changed as $fieldName) {
            if (in_array($fieldName, $this->required)) {
                continue;
            }
            $requestData[$fieldName] =
                $this->getFieldData($this->data[$fieldName]);
        }
        if (empty($requestData)) {
            return false;
        }
        $method = 'POST';
        $href = Client::BASE_URI . Client::ENTITY_URI . '/' . $this->type;
        if (isset($this->data['meta'])) {
            $requestData['meta'] = $this->data['meta'];
            $method = 'PUT';
            $href = $this->data['meta']->href;
        }
        $response = $this->client->getConnection()->query($method, $href, $requestData);
        $this->updateData($response);
        $this->changed = [];
        return $this;
    }

    /**
     * @return object
     */
    protected function getData(): object
    {
        return (object) $this->data;
    }

    /**
     * @param array|object
     * @return mixed
     */
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
