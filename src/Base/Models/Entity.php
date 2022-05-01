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
        $hidden = ['client'];

    public function __construct($client, $entityData)
    {
        $this->client = $client;
        foreach ($entityData as $fieldName=>$fieldValue) {
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
            $response = $this->httpClient->get($href)->getBody()->getContents();
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
        object $entity,
        string $searchType,
        ?int $recursive = null,
        int $limit = 10,
        $expand = null
    ) {
        $resultLinkedEntityList = [];
        foreach($entity as $linkedEnitiesType=>$linkedEntityList) {
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
            if ($recursive) {
                $recurciveLinkedEntityList = [];
                $filterList = [];
                foreach ($linkedEntityList as $l_entity) {
                    $filterList[] = ['id', '=', $this->getId($l_entity)];
                    $limit = $limit - 1;
                    if (!$limit) {
                        break;
                    }
                }
                $linkedEntityList = $this->getEntities($entityType, $filterList, $expand);
                foreach ($linkedEntityList as $linkedEntity) {
                    $recurciveLinkedEntityList = array_merge(
                        $recurciveLinkedEntityList,
                        call_user_func_array(
                            [$this, __FUNCTION__],
                            [$linkedEntity, $searchType, $recursive - 1]
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
            throw new Exception("State with $fieldName = $fieldValue doesn`t exist");
        }
        return $stateList[0];
    }

}
