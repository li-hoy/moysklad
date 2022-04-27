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
            if (isset($entity->$fieldName)) {
                $out->$fieldName = $this->$fieldName;
            }
        }
        return $out;
    }

     /**
     *  by default ms return 25 events - max = 100
     */
    public function getEvents(int $limit = null)
    {
        $uri = $this->meta->href."/audit";
        if (false === is_null($limit)) {
            $uri = $uri."?limit={$limit}";
        }
        return $this->client->query($uri)->get()->parseJson()->rows;
    }

    public function getEmployeeByUid(string $uid)
    {
        $employeeList = $this->client->getEntities('employee', [['uid', '=', $uid]]);
        if (empty($employeeList)) {
            throw new Exception("Employee with $uid doesn`t exist.");
        }
        $employee = $employeeList[0];
        return $employee;
    }

    public function getLinkedEntities(
        string $searchType,
        ?int $recursive = null,
        int $limit = 10
    ) {
        $resultLinkedEntityList = [];
        foreach($this as $linkedEnitiesType=>$linkedEntityList) {
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
                $linkedEntityList = $this->getEntities($entityType, $filterList);
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
