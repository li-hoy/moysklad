<?php

namespace Lihoy\Moysklad;

use Exception;
use Lihoy\Moysklad\Base;
use Lihoy\Moysklad\Components\Http\Connection;
use Lihoy\Moysklad\Entity;

class Client extends Base
{
    public const
        BASE_URI = "https://online.moysklad.ru/api/remap/1.2",
        ENTITY_URI = "/entity",
        HOOK_URI = "/entity/webhook",
        METADATA_URI = "/metadata",
        ENTITIES_QUERY_LIMIT_MAX = 1000,
        EVENTS_QUERY_LIMIT_MAX = 100;

    protected
        $connection,
        $metadata,
        $entitiesQueryLimitMax,
        $eventsQueryLimitMax;

    /**
     * @param string $login
     * @param string $password
     */
    public function __construct(
        string $login,
        string $password
    ) {
        $this->entitiesQueryLimitMax = self::ENTITIES_QUERY_LIMIT_MAX;
        $this->eventsQueryLimitMax = self::EVENTS_QUERY_LIMIT_MAX;
        $this->connection = new Connection($login, $password);
        $this->metadata = null;
    }

    /**
     * @param mixed entityData
     * @return Entity
     */
    public function createEntity(
        $entityType
    ): Entity {
        return new Entity($entityType, $this);
    }

    /**
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @param string $entityType
     * @param string $id
     * @param string|null $expand
     * @return Entity
     */
    public function getEntityById(
        string $entityType,
        string $id,
        ?string $expand = null
    ): Entity {
        $href = static::BASE_URI . static::ENTITY_URI . "/" . $entityType . '/' . $id;
        return $this->getEntityByHref($href, $expand);
    }

    /**
     * @param string $href
     * @param string|null $expand
     * @return Entity
     */
    public function getEntityByHref(
        string $href,
        ?string $expand = null
    ): Entity {
        if ($expand) {
            $href = $href . '?expand=' . $expand;
        }
        $entityData = $this->connection->get($href);
        return new Entity($entityData, $this);
    }

    /**
     * Get entities list with filtering
     *
     * @param string $entityType
     * @param array $filterList
     * @param int $limit
     * @param int $offset
     * @param array $paramList
     * @return array
     */
    public function getEntities(
        string $entityType,
        array $filterList = [],
        int $limit = null,
        int $offset = null,
        array $paramsList = []
    ): array {
        return $this->getCollection(
            'entity/' . $entityType,
            $filterList,
            $limit,
            $offset,
            $paramsList
        );
    }

    /**
     * @param array $hrefsList
     * @param string|null $expand
     * @return array
     */
    public function getEntitiesByHref(
        array $hrefsList,
        ?string $expand = null
    ): array {
        $entitiesList = [];
        foreach ($hrefsList as $href) {
            $entitiesList[] = $this->getEntityByHref($href, $expand);
        }
        return $entitiesList;
    }

    /**
     * @param string $entityType
     * @param array $idsList
     * @param string|null $expand
     * @return array
     */
    public function getEntitiesById(
        string $entityType,
        array $idsList,
        ?string $expand = null
    ): array {
        $entityList = [];
        foreach ($idsList as $id) {
            $entityList[] = $this->getEntityByHref($entityType, $id, $expand);
        }
        return $entityList;
    }

    /**
     * Get stock
     *
     * groupBy values: product, variant, consignment
     * groupBy default value: variant
     *
     * @param bool $byStore
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $groupBy
     * @param array $filtersList
     * @return array
     */
    public function getStock(
        bool $byStore = false,
        ?int $limit = null,
        ?int $offset = null,
        ?string $groupBy = null,
        array $filtersList = []
    ): array {
        $endPoint = 'report/stock/' . ($byStore ? 'bystore' : 'all');
        return $this->getCollection(
            $endPoint,
            $filtersList,
            $limit,
            $offset,
            ['groupBy' => $groupBy]
        );
    }

    /**
     * @param bool $byStore
     * @param string|null $stockType
     * @param bool $zeroLines
     * @param array $filtersList
     * @return array
     */
    public function getCurrentStock(
        bool $byStore = false,
        ?string $stockType = null,
        bool $zeroLines = false,
        array $filtersList = []
    ): array {
        if (\is_null($stockType)) {
            $stockType = 'stock';
        }
        if (false === \in_array($stockType, ['stock', 'freeStock', 'quantity'])) {
            throw new Exception("Wrong stockType value $stockType.");
        }
        $endPoint = 'report/stock/' . ($byStore ? 'bystore' : 'all');
        $endPoint = $endPoint . '/current';
        $endPoint = $endPoint . "?stockType={$stockType}";
        if ($zeroLines) {
            $endPoint = $endPoint . '&include=zeroLines';
        }
        if ($filtersList) {
            $filterURI = "filter=";
            for ($i = 0; $i < count($filtersList); $i++) {
                $filter = $filtersList[$i];
                $filterURI = $filterURI.$filter[0].$filter[1].$filter[2];
                if ($i === (count($filtersList) - 1)) {
                    break;
                }
                $filterURI = $filterURI . ';';
            }
            $endPoint = $endPoint . "&" . $filterURI;
        }
        return $this->connection->get( static::BASE_URI . '/' . $endPoint);
    }

    /**
     * @param string $uid
     * @return Entity
     */
    public function getEmployeeByUid(
        string $uid
    ): Entity {
        $employeeList = $this->getEntities('employee', [['uid', '=', $uid]]);
        if (empty($employeeList)) {
            throw new Exception("Employee with $uid doesn`t exist.");
        }
        return $employeeList[0];
    }

    /**
     * @param string $auditHref
     * @param string $entityType
     * @param string $eventType
     * @param string|null $uid
     * @return Entity
     */
    public function getAuditEvent(
        string $auditHref,
        string $entityType,
        string $eventType,
        ?string $uid = null
    ): Entity {
        $eventType = mb_strtolower($eventType);
        $entityType = mb_strtolower($entityType);
        $eventList = $this->connection->get($auditHref . '/events')->rows;
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
            throw new Exception("Events filtering error.");
        }
        if (empty($filteredEventList[0])) {
            throw new Exception("No event matching parameters: $entityType, $eventType, $uid, $auditHref");
        }
        return $filteredEventList[0];
    }

    /**
     * filter operators =, !=, <, >, <=, >=
     *
     * @param array $list
     * @param array $filter
     * @return array
     */
    public function filter(
        array $list,
        array $filter
    ): array {
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

    /**
     * @param string|null $entityType
     * @return object
     */
    public function getMetadata(
        ?string $entityType = null
    ): object {
        if (is_null($this->metadata)) {
            $this->metadata = array_map(
                function ($entityMetadata) {
                    return new Entity($entityMetadata);
                },
                (array) $this->connection->get(
                    static::BASE_URI . static::ENTITY_URI . static::METADATA_URI
                )
            );
        }
        if (is_null($entityType)) {
            return $this->metadata;
        }
        if (false === isset($this->metadata[$entityType])) {
            throw new Exception("No {$entityType} metadata");
        }
        return $this->metadata[$entityType];
    }

    /**
     * @param array|string $subscriptionList
     * @param string $url
     * @return array
     */
    public function addWebhooks(
        $subscriptionList,
        string $url
    ): array {
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
            $filtered = array_values($filtered);
            if ($filtered) {
                continue;
            }
            $responses[] = $this->connection->post(
                static::BASE_URI . static::HOOK_URI,
                [
                    'url' => $url,
                    'action' => mb_strtoupper($action),
                    'entityType' => $entityType
                ]
            );
        }
        return $responses;
    }

    /**
     * @param string $id
     * @return object
     */
    public function getWebhook(string $id): object
    {
        return $this->connection->get(static::BASE_URI . static::HOOK_URI . "/{$id}");
    }

    /**
     * @param array $filter
     * @return array
     */
    public function getWebhooks(array $filter = []): array
    {
        $webhookList = $this->connection->get(static::BASE_URI . static::HOOK_URI)->rows;
        if (empty($filter)) {
            return $webhookList;
        }
        return $this->filter($webhookList, $filter);
    }

    /**
     * @param array|string $subscriptionList
     * @param string $url
     * @return array
     */
    public function deleteWebhooks($subscriptionList, string $url): array
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
     *
     * @param string $endPoint
     * @param array $filterList
     * @param int $limit
     * @param int $offset
     * @param array $paramsList
     * @return array
     */
    public function getCollection(
        string $endPoint,
        array $filterList = [],
        int $limit = null,
        int $offset = null,
        array $paramsList = []
    ): array {
        $queryLimit = $this->entitiesQueryLimitMax;
        if ($limit && $queryLimit > $limit) {
            $queryLimit = $limit;
        }
        if ($queryLimit <= 0) {
            return [];
        }
        $href_base = static::BASE_URI . "/" . $endPoint . "?limit=" . $queryLimit;
        foreach ($paramsList as $paramName=>$paramValue) {
            if (is_null($paramValue)) {
                continue;
            }
            $href_base = $href_base . '&' . $paramName . '=' . $paramValue;
        }
        if ($filterList) {
            $filterURI = "filter=";
            for ($i = 0; $i < count($filterList); $i++) {
                $filter = $filterList[$i];
                $filterURI = $filterURI . $filter[0] . $filter[1] . $filter[2];
                if ($i === (count($filterList) - 1)) {
                    break;
                }
                $filterURI = $filterURI . ';';
            }
            $href_base = $href_base . "&" . $filterURI;
        }
        $offset = $offset ? $offset : 0;
        $list = [];
        do {
            $href = $href_base . "&offset=" . $offset;
            $response = $this->connection->get($href);
            $entityDataList = $response->rows;
            if (is_null($limit)) {
                $limit = $response->meta->size - $offset;
            }
            $remainder = empty($remainder)
                ? $limit - count($entityDataList)
                : $remainder - count($entityDataList);
            foreach ($entityDataList as $entityData) {
                $list[] = new Entity($entityData, $this);
            }
            $offset = $offset + $queryLimit;
            if ($remainder < $queryLimit) {
                $queryLimit = $remainder;
            }
        } while (count($list) < $limit && count($list) === $queryLimit);
        return $list;
    }

    /**
     * @param string $type
     * @param string $id
     * @param bool $metadata
     * @return object
     */
    public function createLink(
        string $type,
        string $id,
        bool $metadata = false
    ): object {
        $link = (object) [
            'meta' => (object) [
                'href' => static::BASE_URI . static::ENTITY_URI . "/{$type}/$id",
                'type' => $type,
                'mediaType' => "application/json"
            ]
        ];
        if ($metadata === true) {
            $link->meta->metadataHref =
                static::BASE_URI . static::ENTITY_URI . "/{$type}/metadata";
        }
        return $link;
    }

    /**
     * @param int|null $newValue
     * @return void
     */
    public function setEntitiesQueryLimitMax(
        ?int $newValue
    ): void {
        $this->entitiesQueryLimitMax = is_null($newValue)
            ? self::ENTITIES_QUERY_LIMIT_MAX
            : $newValue;
    }

    /**
     * @return int
     */
    public function getEntitiesQueryLimitMax(): int
    {
        return $this->entitiesQueryLimitMax;
    }

    /**
     * @param int|null $newValue
     * @return void
     */
    public function setEventsQueryLimitMax(
        ?int $newValue
    ): void {
        $this->eventsQueryLimitMax = is_null($newValue)
            ? self::EVENTS_QUERY_LIMIT_MAX
            : $newValue;
    }

    /**
     * @return int
     */
    public function getEventsQueryLimitMax(): int
    {
        return $this->eventsQueryLimitMax;
    }
}
