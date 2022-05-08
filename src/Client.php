<?php

namespace Lihoy\Moysklad;

use Lihoy\Moysklad\Components\Http\Connection;
use Lihoy\Moysklad\Entity;

class Client extends \Lihoy\Moysklad\Base
{
    protected $connection;

    const
        BASE_URI = "https://online.moysklad.ru/api/remap/1.2",
        ENTITY_URI = "/entity",
        HOOK_URI = "/entity/webhook",
        METADATA_URI = "/metadata";

    public function __construct($login, $pass)
    {
        $this->connection = new Connection($login, $pass);
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getEntityById(
        string $entityType,
        string $id,
        ?string $expand = null
    ) {
        $href = static::BASE_URI.static::ENTITY_URI."/".$entityType.'/'.$id;
        return $this->getEntityByHref($href, $expand);
    }

    public function getEntityByHref(
        string $href,
        ?string $expand = null
    ) {
        if ($expand) {
            $href = $href.'?expand='.$expand;
        }
        $entityData = $this->connection->get($href);
        return new Entity($this, $entityData);
    }

    /**
     * Get entities list with filtering
     */
    public function getEntities(
        string $entityType,
        array $filterList = [],
        int $limit = null,
        int $offset = null,
        string $expand = null
    ) {
        return $this->getCollection(
            'entity/'.$entityType,
            $filterList,
            $limit,
            $offset,
            ['expand' => $expand]
        );
    }

    /**
     * Get entities list with filtering
     * 
     * groupBy values: product, variant, consignment
     * groupBy default value: variant
     */
    public function getStock(
        bool $byStore = false,
        bool $current = false,
        array $filterList = [],
        int $limit = null,
        int $offset = null,
        ?string $groupBy = null
    ) {
        $endPoint = 'report/stock/'.($byStore ? 'bystore' : 'all');
        if ($current) {
            $endPoint = $endPoint.'/current';
            return $this->connection->get( static::BASE_URI.'/'.$endPoint);
        }
        return $this->getCollection(
            $endPoint,
            $filterList,
            $limit,
            $offset,
            ['groupBy' => $groupBy]
        );
    }

    public function getEmployeeByUid(string $uid)
    {
        $employeeList = $this->getEntities('employee', [['uid', '=', $uid]]);
        if (empty($employeeList)) {
            throw new Exception("Employee with $uid doesn`t exist.");
        }
        return $employeeList[0];
    }

    public function getAuditEvent($auditHref, $entityType, $eventType, $uid = null)
    {
        $eventType = mb_strtolower($eventType);
        $entityType = mb_strtolower($entityType);
        $eventList = $this->connection->get($auditHref.'/events')->rows;
        $filteredEventList = array_values(array_filter(
            $eventList,
            function($event)use($entityType, $eventType, $uid) {
                return
                    $event->eventType === $eventType
                    && $event->entityType === $entityType
                    && (is_null($uid) || $event->uid === $uid);
            }
        ));
        if (count($filteredEventList) > 1) {
            throw new \Exception("Events filtering error.");
        }
        if (empty($filteredEventList[0])) {
            throw new \Exception("No event matching parameters: $entityType, $eventType, $uid, $auditHref");
        }
        return $filteredEventList[0];
    }

    /**
     * filter operators =, !=, <, >, <=, >=
     */
    public function filter(
        array $list,
        array $filter
    ) {
        foreach ($list as $key=>$value) {
            $filtered = false;
            foreach ($filter as $rule) {
                $fieldKey = $rule[0];
                $op = $rule[1];
                $fieldValue = $rule[2];
                $filtered = false;
                if ($op === '=' && $fieldValue != $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '!=' && $fieldValue == $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '<' && $fieldValue >= $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '>' && $fieldValue <= $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '<=' && $fieldValue > $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '>=' && $fieldValue < $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
            }
            if ($filtered) {
                unset($list[$key]);
            }
        }
        return array_values($list);
    }

    public function getMetadata(string $entityType)
    {
        return $this->connection->get(
            static::BASE_URI.static::ENTITY_URI."/{$entityType}".static::METADATA_URI
        );
    }

    public function addWebhooks($subscriptionList, string $url)
    {
        if (is_string($subscriptionList)) {
            $subscriptionList = [$subscriptionList];
        }
        $actualSubscriptionList = $this->getWebhooks([['url', '=', $url]]);
        $responses = [];
        foreach ($subscriptionList as $subscription) {
            $subscriptionParts = explode('.', $subscription);
            $entityType = $subscriptionParts[0];
            $action = $subscriptionParts[1];
            $filtered = array_filter(
                $actualSubscriptionList,
                function($hook)use($entityType, $action) {
                    return $hook->entityType === $entityType && $hook->action === mb_strtoupper($action);
                }
            );
            $filtered  = array_values($filtered);
            if ($filtered) {
                continue;
            }
            $responses[] = $this->connection->post(
                static::BASE_URI.static::HOOK_URI,
                [
                    'url' => $url,
                    'action' => mb_strtoupper($action),
                    'entityType' => $entityType
                ]
            );
        }
        return $responses;
    }

    public function getWebhook(string $id)
    {
        return $this->connection->get(static::BASE_URI.static::HOOK_URI."/{$id}");
    }

    public function getWebhooks(array $filter = [])
    {
        $webhookList = $this->connection->get(static::BASE_URI.static::HOOK_URI)->rows;
        if (empty($filter)) {
            return $webhookList;
        }
        return $this->filter($webhookList, $filter);
    }

    public function deleteWebhooks($subscriptionList, string $url)
    {
        if (is_string($subscriptionList)) {
            $subscriptionList = [$subscriptionList];
        }
        $actualSubscriptionList = $this->getWebhooks([['url', '=', $url]]);
        $responses = [];
        foreach ($subscriptionList as $subscription) {
            $subscriptionParts = explode('.', $subscription);
            $entityType = $subscriptionParts[0];
            $action = $subscriptionParts[1];
            $filtered = array_values(array_filter($actualSubscriptionList, function ($hook)use($entityType, $action) {
                return $hook->entityType === $entityType && $hook->action === mb_strtoupper($action);
            }));
            $isSigned = empty($filtered) ? false : true;
            if ($isSigned) {
                $hook = $filtered[0];
                $responses[] = $this->connection->delete($hook->meta->href);
            }
        }
        return $responses;
    }

    /**
     * Get entities list with filtering
     * 
     * filterList example: [['sum', '>' '100'], ['sum', '<' '200']]
     * opaerators: ['=', '>', '<', '>=', '<=', '!=', '~', '~=', '=~']
     */
    public function getCollection(
        string $endPoint,
        array $filterList = [],
        int $limit = null,
        int $offset = null,
        array $paramList = []
    ) {
        $queryLimit = 1000;
        if ($limit && $queryLimit > $limit) {
            $queryLimit = $limit;
        }
        if ($queryLimit <= 0) {
            return [];
        }
        $href_base = static::BASE_URI."/".$endPoint."?limit=".$queryLimit;
        foreach ($paramList as $paramName=>$paramValue) {
            if (is_null($paramValue)) {
                continue;
            }
            $href_base = $href_base.'&'.$paramName.'='.$paramValue;
        }
        if ($filterList) {
            $filterURI = "filter=";
            for ($i = 0; $i < count($filterList); $i++) {
                $filter = $filterList[$i];
                $filterURI = $filterURI.$filter[0].$filter[1].$filter[2];
                if ($i === (count($filterList) - 1)) {
                    break;
                }
                $filterURI = $filterURI.';';
            }
            $href_base = $href_base."&".$filterURI;
        }
        $offset = $offset ? $offset : 0;
        $list = [];
        do {
            $href = $href_base."&offset=".$offset;
            $response = $this->connection->get($href);
            $entityDataList = $response->rows;
            if (is_null($limit)) {
                $limit = $response->meta->size - $offset;
            }
            $remainder = empty($remainder)
                ? $limit - count($entityDataList)
                : $remainder - count($entityDataList);
            foreach ($entityDataList as $entityData) {
                $list[] = new Entity($this, $entityData);
            }
            $offset = $offset + $queryLimit;
            if ($remainder < $queryLimit) {
                $queryLimit = $remainder;
            }
        } while (count($list) < $limit && count($list) === $queryLimit);
        return $list;
    }
}
